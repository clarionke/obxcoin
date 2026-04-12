<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use App\Model\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            'user_id'     => 'required|integer|exists:users,id',
            'campaign_id' => 'required|integer|exists:airdrop_campaigns,id',
            'tx_hash'     => 'required|string|size:66',
            'obx_amount'  => 'required|string',
        ]);

        $unlock = AirdropUnlock::where('user_id', $request->user_id)
            ->where('campaign_id', $request->campaign_id)
            ->firstOrFail();

        $unlock->update([
            'tx_hash'      => $request->tx_hash,
            'obx_released' => $request->obx_amount,
            'unlocked_at'  => now(),
            'status'       => 'confirmed',
        ]);

        // Credit user's internal OBX wallet balance
        $wallet = Wallet::where('user_id', $request->user_id)
            ->where('coin_type', DEFAULT_COIN_TYPE)
            ->first();

        if ($wallet) {
            $wallet->increment('balance', (float) $request->obx_amount);
        }

        return response()->json(['success' => true]);
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
