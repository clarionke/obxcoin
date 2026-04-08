<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\BuyCoinHistory;
use App\Model\IcoPhase;
use App\Model\Wallet;
use App\Services\BlockchainService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PresaleWebhookController
 *
 * Two entry points:
 *
 * 1. POST /api/presale/webhook
 *    Called by an external event relay (e.g. Alchemy Notify, Moralis Stream,
 *    or a self-hosted cron) with the raw TokensPurchased event.
 *    Secured by a shared HMAC secret (PRESALE_WEBHOOK_SECRET in .env).
 *
 * 2. POST /api/presale/sync-events (cron-callable, protected by API key)
 *    Polls BSCScan for new TokensPurchased events and processes them.
 *
 * When a purchase event is processed:
 *   - A BuyCoinHistory record is created (type = ONCHAIN_USDT, status = SUCCESS)
 *   - The user's default OBX wallet balance is incremented
 *   - The IcoPhase.contract_phase_index is recorded if not already set
 */
class PresaleWebhookController extends Controller
{
    private BlockchainService $blockchain;

    public function __construct(BlockchainService $blockchain)
    {
        $this->blockchain = $blockchain;
    }

    // ─── Webhook endpoint (called by event relay) ─────────────────────────

    public function handleWebhook(Request $request)
    {
        // PRESALE_WEBHOOK_SECRET must be configured. If not set, we refuse all
        // inbound webhook calls to prevent the HMAC bypass vulnerability where
        // an empty $secret would skip signature verification entirely.
        $secret = config('blockchain.webhook_secret', '');
        if (!$secret) {
            Log::critical('PresaleWebhook: PRESALE_WEBHOOK_SECRET is not configured — rejecting request');
            return response()->json(['error' => 'Webhook secret not configured on server'], 500);
        }

        $signature = $request->header('X-Presale-Signature', '');
        if (!$this->verifySignature($request->getContent(), $signature, $secret)) {
            Log::warning('PresaleWebhook: invalid HMAC signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $events = $request->input('events', []);
        if (empty($events)) {
            // Single event payload
            $events = [$request->all()];
        }

        $processed = 0;
        foreach ($events as $event) {
            if ($this->processEvent($event)) {
                $processed++;
            }
        }

        return response()->json(['processed' => $processed]);
    }

    // ─── Cron sync endpoint (polls BSCScan) ───────────────────────────────

    public function syncEvents(Request $request)
    {
        // Simple API key guard
        $key = $request->header('X-Api-Key', '');
        if ($key !== config('blockchain.sync_api_key', '')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get last processed block from DB (stored in admin_settings)
        $lastBlock = (int) settings('presale_last_block', config('blockchain.start_block', 0));

        $events = $this->blockchain->getPurchaseEvents($lastBlock);

        $processed  = 0;
        $maxBlock   = $lastBlock;

        foreach ($events as $event) {
            if ($this->processEvent($event)) {
                $processed++;
            }
            if (isset($event['block_number']) && $event['block_number'] > $maxBlock) {
                $maxBlock = $event['block_number'];
            }
        }

        // Persist the new high-water block
        if ($maxBlock > $lastBlock) {
            \App\Model\AdminSetting::updateOrCreate(
                ['slug' => 'presale_last_block'],
                ['value' => $maxBlock + 1]
            );
        }

        return response()->json(['processed' => $processed, 'last_block' => $maxBlock]);
    }

    // ─── Core event processor ──────────────────────────────────────────────

    private function processEvent(array $event): bool
    {
        $txHash = $event['tx_hash'] ?? null;
        if (!$txHash) return false;

        // Idempotency: skip if already processed
        if (BuyCoinHistory::where('tx_hash', $txHash)->exists()) {
            return false;
        }

        // ── On-chain verification ──────────────────────────────────────────────
        // Cross-check the webhook payload against the actual chain receipt.
        // This prevents a relay from submitting fraudulent purchase amounts.
        $verified = $this->blockchain->verifyPurchaseTransaction($txHash);
        if (!$verified) {
            Log::error("PresaleWebhook: tx $txHash not found on-chain or failed — ignoring");
            return false;
        }

        // Use on-chain values — NEVER trust webhook payload amounts
        $buyerAddress       = strtolower($verified['buyer']);
        $dbPhaseId          = (int)$verified['db_phase_id'];
        $obxAmount          = $verified['obx_allocated'];
        $usdtAmount         = $verified['usdt_amount'];
        $bonusObx           = $verified['bonus_obx'];
        $contractPhaseIndex = (int)$verified['contract_phase_index'];

        if (!$buyerAddress || !$dbPhaseId || bccomp($obxAmount, '0') <= 0) {
            Log::warning('PresaleWebhook: on-chain data invalid after verification', $verified);
            return false;
        }

        // Find the user by their registered wallet address
        // Users must link their BSC wallet in their profile (buyer_wallet column on users table)
        $user = User::whereRaw('LOWER(bsc_wallet) = ?', [$buyerAddress])->first();

        DB::beginTransaction();
        try {
            // Get phase
            $phase = IcoPhase::find($dbPhaseId);

            // Record purchase history
            $history                  = new BuyCoinHistory();
            $history->type            = ONCHAIN_USDT;
            $history->address         = $buyerAddress;
            $history->user_id         = $user?->id ?? 0;
            $history->phase_id        = $dbPhaseId;
            $history->coin            = $obxAmount;
            $history->doller          = $usdtAmount;
            $history->btc             = 0;
            $history->bonus           = $bonusObx;
            $history->fees            = 0;
            $history->requested_amount= $obxAmount;
            $history->referral_bonus  = 0;
            $history->coin_type       = 'USDT';
            $history->tx_hash         = $txHash;
            $history->buyer_wallet    = $buyerAddress;
            $history->status          = STATUS_SUCCESS;
            $history->admin_confirmation = STATUS_SUCCESS;
            $history->save();

            // Update phase tokensSold tracking in DB
            if ($phase) {
                BuyCoinHistory::where('phase_id', $phase->id)
                    ->where('status', STATUS_SUCCESS)
                    ->sum('coin'); // just for reference; contract is the source of truth

                // Store contract_phase_index if not already set and clear pending tx
                if ($phase->contract_phase_index === null) {
                    $phase->update([
                        'contract_phase_index' => $contractPhaseIndex,
                        'contract_synced'      => true,
                        'pending_onchain_tx'   => null,
                    ]);
                } elseif ($phase->pending_onchain_tx) {
                    // Phase already had an index; clear the pending update tx
                    $phase->update(['pending_onchain_tx' => null]);
                }
            }

            // Credit OBX to user's default wallet (if user found)
            if ($user) {
                $wallet = Wallet::where([
                    'user_id'    => $user->id,
                    'coin_type'  => DEFAULT_COIN_TYPE,
                    'is_primary' => 1,
                ])->first();

                if ($wallet) {
                    $wallet->increment('balance', $obxAmount);
                } else {
                    Log::warning("PresaleWebhook: no primary OBX wallet for user #{$user->id}");
                }
            } else {
                Log::warning("PresaleWebhook: buyer $buyerAddress has no matching user account");
            }

            DB::commit();
            Log::info("PresaleWebhook: processed purchase tx=$txHash buyer=$buyerAddress obx=$obxAmount");
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PresaleWebhook: DB error for tx=' . $txHash . ': ' . $e->getMessage());
            return false;
        }
    }

    // ─── Public read: live on-chain phase data ────────────────────────────

    /**
     * GET /api/presale/phase-info/{index}
     *
     * Returns live on-chain data for the given phase index.
     * Used by the buy page to display remaining tokens, reserve, and active phase.
     * No authentication required — all data is public on-chain.
     */
    public function phaseInfo(int $index)
    {
        $remaining  = $this->blockchain->getRemainingTokens($index);
        $obxReserve = $this->blockchain->getObxReserve();
        $totalPhases= $this->blockchain->getTotalPhases();
        $activePh   = $this->blockchain->getActivePhaseIndex();

        return response()->json([
            'phase_index'    => $index,
            'remaining_obx'  => $remaining,
            'obx_reserve'    => $obxReserve,
            'total_phases'   => $totalPhases,
            'active_phase'   => $activePh,
        ]);
    }

    /**
     * GET /api/presale/phase-info/{index}/preview/{usdt}
     *
     * Preview how many OBX tokens a given USDT amount would purchase in a phase.
     * {usdt} is the human amount, e.g. "100" for 100 USDT.
     * No authentication required.
     */
    public function previewPurchase(int $index, string $usdt)
    {
        // Validate: must be a positive decimal number
        if (!is_numeric($usdt) || bccomp($usdt, '0', 6) <= 0) {
            return response()->json(['error' => 'Invalid USDT amount'], 422);
        }

        $preview = $this->blockchain->previewPurchase($index, $usdt);

        return response()->json([
            'phase_index' => $index,
            'usdt_amount' => $usdt,
            'base_obx'    => $preview['baseObx'],
            'bonus_obx'   => $preview['bonusObx'],
            'total_obx'   => $preview['totalObx'],
        ]);
    }

    // ─── HMAC verification ────────────────────────────────────────────────

    private function verifySignature(string $body, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $body, $secret);
        // Support both bare hex and "sha256=<hex>" prefix formats
        $incoming = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        return hash_equals($expected, $incoming);
    }
}
