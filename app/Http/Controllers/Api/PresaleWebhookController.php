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
        // Verify HMAC signature
        $secret    = config('blockchain.webhook_secret', '');
        $signature = $request->header('X-Presale-Signature', '');

        if ($secret && !$this->verifySignature($request->getContent(), $signature, $secret)) {
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

        $buyerAddress = strtolower($event['buyer'] ?? '');
        $dbPhaseId    = (int)($event['db_phase_id'] ?? 0);
        $obxAmount    = $event['obx_allocated'] ?? '0';   // human-readable 18-dec string
        $usdtAmount   = $event['usdt_amount'] ?? '0';     // human-readable 6-dec string
        $bonusObx     = $event['bonus_obx'] ?? '0';
        $contractPhaseIndex = (int)($event['contract_phase_index'] ?? 0);

        if (!$buyerAddress || !$dbPhaseId || bccomp($obxAmount, '0') <= 0) {
            Log::warning('PresaleWebhook: invalid event data', $event);
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

                // Store contract_phase_index if not already set
                if ($phase->contract_phase_index === null) {
                    $phase->update([
                        'contract_phase_index' => $contractPhaseIndex,
                        'contract_synced'      => true,
                    ]);
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

    // ─── HMAC verification ────────────────────────────────────────────────

    private function verifySignature(string $body, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $body, $secret);
        // Support both bare hex and "sha256=<hex>" prefix formats
        $incoming = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        return hash_equals($expected, $incoming);
    }
}
