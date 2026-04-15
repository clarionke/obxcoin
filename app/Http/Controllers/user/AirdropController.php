<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use App\Model\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AirdropController extends Controller
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    /**
     * Show the user's airdrop dashboard:
     *  - Active / upcoming campaign details
     *  - Today's claim status
     *  - Total accumulated locked OBX
     *  - Unlock status (pending fee reveal, fee shown, already unlocked)
     */
    public function index()
    {
        $userId   = Auth::id();
        $today    = Carbon::today();
        $campaign = AirdropCampaign::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>', now())
            ->orderByDesc('start_date')
            ->first();
        $claimedToday    = false;
        $totalLockedObx  = '0';
        $unlockRecord    = null;
        $pastCampaigns   = [];

        if ($campaign) {
            // Has user claimed today in this campaign?
            $claimedToday = AirdropClaim::where('user_id', $userId)
                ->where('campaign_id', $campaign->id)
                ->whereDate('claim_date', $today)
                ->exists();

            // Total OBX locked (sum of all claims in this campaign for this user)
            $amounts = AirdropClaim::where('user_id', $userId)
                ->where('campaign_id', $campaign->id)
                ->pluck('amount_obx');
            foreach ($amounts as $amt) {
                $totalLockedObx = bcadd($totalLockedObx, (string) $amt, 18);
            }

            // Unlock record for this campaign
            $unlockRecord = AirdropUnlock::where('user_id', $userId)
                ->where('campaign_id', $campaign->id)
                ->first();
        }

        // All ended campaigns where user has unclaimed (non-unlocked) balance
        $pastCampaigns = AirdropCampaign::where('is_active', true)
            ->where('end_date', '<', now())
            ->whereHas('claims', fn($q) => $q->where('user_id', $userId))
            ->whereDoesntHave('unlocks', fn($q) => $q->where('user_id', $userId)->where('status', 'confirmed'))
            ->get();

        $data['title']          = __('My Airdrop');
        $data['menu']           = 'airdrop';
        $data['campaign']       = $campaign;
        $data['claimedToday']   = $claimedToday;
        $data['totalLockedObx'] = $totalLockedObx;
        $data['unlockRecord']   = $unlockRecord;
        $data['pastCampaigns']  = $pastCampaigns;

        $currentStreak     = 0;
        $streakBonusAmount = '0';
        $nextBonusAt       = 0;

        if ($campaign) {
            $currentStreak     = $this->getCurrentStreak($userId, $campaign->id, $today);
            $streakBonusAmount = $campaign->streak_bonus_amount ?? '0';
            $streakDays        = max(1, (int) ($campaign->streak_days ?? 5));
            $mod               = $currentStreak % $streakDays;
            $nextBonusAt       = ($mod === 0 && $currentStreak > 0) ? $streakDays : $streakDays - $mod;
        }

        $data['currentStreak']     = $currentStreak;
        $data['streakBonusAmount'] = $streakBonusAmount;
        $data['nextBonusAt']       = $nextBonusAt;

        return view('user.airdrop.index', $data);
    }

    // ─── Daily claim ──────────────────────────────────────────────────────────

    /**
     * Record the user's daily airdrop claim.
     * Tokens are locked in the system until the campaign ends and the user unlocks.
     */
    public function claim(Request $request)
    {
        $userId   = Auth::id();
        $today    = Carbon::today();
        $campaign = AirdropCampaign::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>', now())
            ->orderByDesc('start_date')
            ->first();

        if (!$campaign) {
            return redirect()->route('user.airdrop')->with('dismiss', __('No active airdrop campaign.'));
        }

        if (!$campaign->isLive()) {
            return redirect()->route('user.airdrop')->with('dismiss', __('Campaign is not currently active.'));
        }

        // Prevent double-claim on same day (regular claims only)
        $alreadyClaimed = AirdropClaim::where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->whereDate('claim_date', $today)
            ->where('is_bonus', false)
            ->exists();

        if ($alreadyClaimed) {
            return redirect()->route('user.airdrop')->with('dismiss', __('You have already claimed your airdrop for today. Come back tomorrow!'));
        }

        // Prevent claiming if user already unlocked this campaign
        $unlocked = AirdropUnlock::where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->where('status', 'confirmed')
            ->exists();

        if ($unlocked) {
            return redirect()->route('user.airdrop')->with('dismiss', __('You have already unlocked your airdrop for this campaign.'));
        }

        try {
            AirdropClaim::create([
                'user_id'     => $userId,
                'campaign_id' => $campaign->id,
                'claim_date'  => $today,
                'amount_obx'  => $campaign->daily_claim_amount,
            ]);

            // Streak gamification — award bonus on every N-day milestone
            $streak       = $this->getCurrentStreak($userId, $campaign->id, $today);
            $streakDays   = max(1, (int) ($campaign->streak_days ?? 5));
            $bonusAmount  = $campaign->streak_bonus_amount ?? '0';
            $bonusAwarded = false;

            if (bccomp((string) $bonusAmount, '0', 18) > 0
                && $streak > 0
                && $streak % $streakDays === 0) {
                AirdropClaim::create([
                    'user_id'     => $userId,
                    'campaign_id' => $campaign->id,
                    'claim_date'  => $today,
                    'amount_obx'  => $bonusAmount,
                    'is_bonus'    => true,
                ]);
                $bonusAwarded = true;
            }

            $message = __(
                'Successfully claimed :amount OBX! Tokens are locked until the campaign ends.',
                ['amount' => number_format((float) $campaign->daily_claim_amount, 2)]
            );

            if ($bonusAwarded) {
                $message .= ' ' . __(
                    ':days-day streak bonus! Extra :bonus OBX added!',
                    ['days' => $streakDays, 'bonus' => number_format((float) $bonusAmount, 2)]
                );
            }

            return redirect()->route('user.airdrop')->with('success', $message);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race-condition guard
            return redirect()->route('user.airdrop')->with('dismiss', __('You have already claimed today.'));
        } catch (\Exception $e) {
            Log::error('Airdrop claim failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return redirect()->route('user.airdrop')->with('dismiss', __('Something went wrong. Please try again.'));
        }
    }

    // ─── Unlock ───────────────────────────────────────────────────────────────

    /**
     * User requests to unlock their airdrop tokens by paying the USDT fee.
     *
     * Flow:
     *  1. Validate campaign ended + fee revealed
     *  2. Compute total locked OBX for this user
     *  3. Create an AirdropUnlock record (status=pending)
     *  4. Redirect user to payment / on-chain unlock flow
     *
     * The actual on-chain transfer happens when the user calls OBXAirdrop.unlock()
     * directly (or via the front-end dApp connector). The Laravel side records
     * the on-chain tx_hash once confirmed.
     */
    public function requestUnlock(Request $request)
    {
        $userId = Auth::id();

        $request->validate(['campaign_id' => 'required|integer|exists:airdrop_campaigns,id']);
        $campaign = AirdropCampaign::findOrFail($request->campaign_id);

        if (!$campaign->hasEnded()) {
            return redirect()->route('user.airdrop')->with('dismiss', __('Campaign has not ended yet.'));
        }

        if (!$campaign->fee_revealed) {
            return redirect()->route('user.airdrop')->with('dismiss', __('The unlock fee has not been revealed yet. Please check back soon.'));
        }

        // Check user has a locked balance
        $amounts = AirdropClaim::where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->pluck('amount_obx');
        $totalLockedObx = '0';
        foreach ($amounts as $amt) {
            $totalLockedObx = bcadd($totalLockedObx, (string) $amt, 18);
        }

        if (bccomp($totalLockedObx, '0', 18) <= 0) {
            return redirect()->route('user.airdrop')->with('dismiss', __('You have no locked airdrop balance for this campaign.'));
        }

        // Check not already unlocked
        $existing = AirdropUnlock::where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'confirmed') {
                return redirect()->route('user.airdrop')->with('dismiss', __('Already unlocked.'));
            }
            // Already pending — redirect to payment
            return redirect()->route('user.airdrop')
                ->with('info', __('Your unlock request is pending on-chain confirmation.'));
        }

        try {
            DB::transaction(function () use ($userId, $campaign, $totalLockedObx) {
                AirdropUnlock::create([
                    'user_id'      => $userId,
                    'campaign_id'  => $campaign->id,
                    'usdt_paid'    => $campaign->unlock_fee_usdt,
                    'obx_released' => $totalLockedObx,
                    'status'       => 'pending',
                ]);
            });

            return redirect()->route('user.airdrop')
                ->with('success', __(
                    'Unlock initiated. Pay :fee USDT on-chain via the OBXAirdrop contract to release your :obx OBX.',
                    [
                        'fee' => number_format((float) $campaign->unlock_fee_usdt, 2),
                        'obx' => number_format((float) $totalLockedObx, 4),
                    ]
                ));
        } catch (\Exception $e) {
            Log::error('Airdrop unlock request failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return redirect()->route('user.airdrop')->with('dismiss', __('Something went wrong. Please try again.'));
        }
    }

    // ─── Confirm unlock (webhook / callback) ─────────────────────────────────

    /**
     * Called by the blockchain listener job when the on-chain OBXAirdrop.unlock()
     * event is confirmed. Marks the user's unlock record as confirmed.
     *
     * This is an internal endpoint — only reachable from the job, not the user.
     */
    public function confirmUnlock(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|integer|exists:airdrop_campaigns,id',
            'tx_hash'     => 'required|string|size:66|regex:/^0x[0-9a-fA-F]{64}$/',
            'wallet_address' => 'required|string|size:42|regex:/^0x[0-9a-fA-F]{40}$/',
        ]);

        $userId = Auth::id();
        $campaign = AirdropCampaign::findOrFail($request->campaign_id);

        if (empty($campaign->contract_address)) {
            return response()->json([
                'success' => false,
                'message' => __('Airdrop contract is not configured for this campaign.'),
            ], 422);
        }

        $unlock = AirdropUnlock::where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->first();

        $claimedAmount = (string) AirdropClaim::where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->sum('amount_obx');

        $totalLockedObx = $unlock ? (string)$unlock->obx_released : $claimedAmount;
        if (bccomp($totalLockedObx, '0', 18) <= 0) {
            $totalLockedObx = $claimedAmount;
        }

        if (bccomp($totalLockedObx, '0', 18) <= 0) {
            return response()->json([
                'success' => false,
                'message' => __('No locked airdrop balance found for this campaign.'),
            ], 422);
        }

        if (!$unlock) {
            $unlock = AirdropUnlock::create([
                'user_id' => $userId,
                'campaign_id' => $campaign->id,
                'usdt_paid' => $campaign->unlock_fee_usdt,
                'obx_released' => $totalLockedObx,
                'status' => 'pending',
            ]);
        }

        if ($unlock->status === 'confirmed') {
            return response()->json([
                'success' => true,
                'message' => __('Unlock already confirmed.'),
            ]);
        }

        $verification = $this->verifyOnchainUnlock(
            strtolower($request->tx_hash),
            strtolower($request->wallet_address),
            strtolower($campaign->contract_address)
        );

        if (!$verification['ok']) {
            return response()->json([
                'success' => false,
                'message' => $verification['message'],
            ], 422);
        }

        $unlock->update([
            'tx_hash'      => $request->tx_hash,
            'obx_released' => $totalLockedObx,
            'unlocked_at'  => now(),
            'status'       => 'confirmed',
        ]);

        // Credit user's internal OBX wallet balance
        $wallet = get_primary_wallet($userId, DEFAULT_COIN_TYPE);

        if ($wallet) {
            $wallet->increment('balance', (float) $totalLockedObx);
        }

        return response()->json(['success' => true]);
    }

    private function verifyOnchainUnlock(string $txHash, string $walletAddress, string $contractAddress): array
    {
        try {
            $rpcUrl = trim((string)(settings('chain_link') ?: config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/')));
            if ($rpcUrl === '') {
                return ['ok' => false, 'message' => __('RPC endpoint is not configured.')];
            }

            $receiptRes = Http::timeout(20)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1,
            ])->json();
            $txRes = Http::timeout(20)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionByHash',
                'params' => [$txHash],
                'id' => 2,
            ])->json();

            $receipt = $receiptRes['result'] ?? null;
            $tx = $txRes['result'] ?? null;
            if (!$receipt || !$tx) {
                return ['ok' => false, 'message' => __('Transaction not found on-chain yet. Please wait and retry.')];
            }

            if (strtolower((string)($receipt['status'] ?? '0x0')) !== '0x1') {
                return ['ok' => false, 'message' => __('Transaction failed on-chain.')];
            }

            if (strtolower((string)($receipt['to'] ?? '')) !== $contractAddress) {
                return ['ok' => false, 'message' => __('Transaction target contract mismatch.')];
            }

            if (strtolower((string)($tx['from'] ?? '')) !== $walletAddress) {
                return ['ok' => false, 'message' => __('Wallet address does not match transaction sender.')];
            }

            $input = strtolower((string)($tx['input'] ?? ''));
            if (!str_starts_with($input, '0xa69df4b5')) {
                return ['ok' => false, 'message' => __('Transaction is not an airdrop unlock call.')];
            }

            return ['ok' => true, 'message' => __('Verified')];
        } catch (\Throwable $e) {
            Log::warning('Airdrop unlock verification failed: ' . $e->getMessage(), ['tx_hash' => $txHash]);
            return ['ok' => false, 'message' => __('Unable to verify on-chain transaction right now.')];
        }
    }

    // ─── Streak helper ────────────────────────────────────────────────────────

    /**
     * Count consecutive days (ending today) the user has claimed in the campaign.
     * Only counts non-bonus claims.
     */
    private function getCurrentStreak(int $userId, int $campaignId, Carbon $today): int
    {
        $streak = 0;
        $day    = $today->copy();

        while ($streak < 365) {
            $exists = AirdropClaim::where('user_id', $userId)
                ->where('campaign_id', $campaignId)
                ->whereDate('claim_date', $day)
                ->where('is_bonus', false)
                ->exists();

            if (!$exists) {
                break;
            }

            $streak++;
            $day->subDay();
        }

        return $streak;
    }
}
