<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use App\Model\BuyCoinHistory;
use App\Services\AirdropProgressService;
use App\Services\NowPaymentsService;
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
        $userId = Auth::id();
        $airdropProgress = app(AirdropProgressService::class)->buildForUser((int) $userId);
        $campaign = $airdropProgress['campaign'];
        $claimedToday = (bool) $airdropProgress['claimedToday'];
        $userTotalPurchasedUsd = $this->getUserTotalPurchasedUsd((int) $userId);
        $userWithdrawFeeUsdt = $campaign
            ? $this->resolveUserWithdrawFee($campaign, $userTotalPurchasedUsd)
            : 0.0;
        $userWithdrawFeeTierLabel = $campaign
            ? $campaign->resolveUnlockFeeTierLabel($userTotalPurchasedUsd)
            : null;
        $totalLockedObx  = '0';
        $unlockRecord    = null;
        $pastCampaigns   = [];

        if ($campaign) {
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
        $data['airdropWithdrawEnabled'] = (int) (settings(AIRDROP_WITHDRAW_ENABLED_SLUG) ?: 0) === 1;
        $data['airdropWithdrawPayCurrency'] = strtolower((string) (settings(AIRDROP_WITHDRAW_PAY_CURRENCY_SLUG) ?: 'usdtbsc'));
        $data['userTotalPurchasedUsd'] = $userTotalPurchasedUsd;
        $data['userWithdrawFeeUsdt'] = $userWithdrawFeeUsdt;
        $data['userWithdrawFeeTierLabel'] = $userWithdrawFeeTierLabel;
        $data['airdropProgress'] = $airdropProgress;

        $currentStreak = (int) ($airdropProgress['todayStreak'] ?? 0);
        $streakBonusAmount = (string) ($airdropProgress['streakBonusAmount'] ?? '0');
        $nextBonusAt = (int) ($airdropProgress['remainingStreak'] ?? 0);
        if ($currentStreak > 0 && $nextBonusAt === 0) {
            $nextBonusAt = max(1, (int) ($airdropProgress['streakDays'] ?? 1));
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
     * Create (or reuse) a NOWPayments order for off-chain airdrop withdrawal.
     */
    public function requestUnlock(Request $request)
    {
        $userId = Auth::id();

        $request->validate(['campaign_id' => 'required|integer|exists:airdrop_campaigns,id']);
        $campaign = AirdropCampaign::findOrFail($request->campaign_id);

        if (!$campaign->hasEnded()) {
            return redirect()->route('user.airdrop')->with('dismiss', __('Campaign has not ended yet.'));
        }

        [$withdrawEnabled, $payCurrency] = $this->getAirdropWithdrawConfig();
        $userTotalPurchasedUsd = $this->getUserTotalPurchasedUsd((int) $userId);
        $withdrawFeeUsdt = $this->resolveUserWithdrawFee($campaign, $userTotalPurchasedUsd);

        if (!$withdrawEnabled) {
            return redirect()->route('user.airdrop')
                ->with('dismiss', __('Airdrop withdrawals are not enabled yet. Please wait for admin activation.'));
        }

        if (!$campaign->fee_revealed) {
            return redirect()->route('user.airdrop')
                ->with('dismiss', __('Airdrop withdrawal fee is hidden for this campaign. Please wait for admin to reveal it.'));
        }

        if ($withdrawFeeUsdt <= 0) {
            return redirect()->route('user.airdrop')
                ->with('dismiss', __('Airdrop withdrawal fee is not configured yet.'));
        }

        if ((int) (settings('nowpayments_enabled') ?? 0) !== 1) {
            return redirect()->route('user.airdrop')
                ->with('dismiss', __('NOWPayments is currently disabled. Please contact support.'));
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

        if ($existing && $existing->status === 'confirmed') {
            return redirect()->route('user.airdrop')->with('dismiss', __('Already withdrawn.'));
        }

        try {
            $unlock = DB::transaction(function () use ($existing, $userId, $campaign, $totalLockedObx, $withdrawFeeUsdt) {
                $record = $existing;

                if (!$record) {
                    $record = AirdropUnlock::create([
                        'user_id' => $userId,
                        'campaign_id' => $campaign->id,
                        'usdt_paid' => number_format($withdrawFeeUsdt, 2, '.', ''),
                        'obx_released' => $totalLockedObx,
                        'status' => 'pending',
                        'nowpayments_payment_status' => 'waiting',
                    ]);
                } else {
                    $record->update([
                        'usdt_paid' => number_format($withdrawFeeUsdt, 2, '.', ''),
                        'obx_released' => $totalLockedObx,
                        'status' => 'pending',
                        'nowpayments_payment_status' => $record->nowpayments_payment_status ?: 'waiting',
                    ]);
                }

                return $record->fresh();
            });

            $hasOpenPayment = !empty($unlock->nowpayments_payment_id)
                && !in_array(strtolower((string) $unlock->nowpayments_payment_status), ['failed', 'expired', 'refunded'], true);

            if ($hasOpenPayment) {
                return redirect()->route('user.airdrop')
                    ->with('info', __('You already have a pending withdrawal payment. Complete it to receive your OBX.'));
            }

            $orderId = 'airdrop_unlock_' . $unlock->id;

            $nowPayments = app(NowPaymentsService::class);
            $npResponse = $nowPayments->createPayment(
                priceAmount: (float) number_format($withdrawFeeUsdt, 2, '.', ''),
                payCurrency: $payCurrency,
                orderId: $orderId,
                ipnCallbackUrl: route('airdrop.nowpayments.ipn'),
                description: "Airdrop Withdraw #{$unlock->id}"
            );

            if (empty($npResponse['payment_id'])) {
                throw new \RuntimeException('NOWPayments did not return payment_id.');
            }

            $unlock->update([
                'nowpayments_payment_id' => (string) $npResponse['payment_id'],
                'nowpayments_order_id' => $orderId,
                'nowpayments_pay_address' => $npResponse['pay_address'] ?? null,
                'nowpayments_pay_amount' => isset($npResponse['pay_amount']) ? (string) $npResponse['pay_amount'] : null,
                'nowpayments_pay_currency' => strtolower((string) ($npResponse['pay_currency'] ?? $payCurrency)),
                'nowpayments_payment_status' => strtolower((string) ($npResponse['payment_status'] ?? 'waiting')),
            ]);

            return redirect()->route('user.airdrop')
                ->with('success', __(
                    'Withdrawal request created. Pay :fee USDT to send :obx OBX to your OBX Wallet.',
                    [
                        'fee' => number_format((float) $withdrawFeeUsdt, 2),
                        'obx' => number_format((float) $totalLockedObx, 4),
                    ]
                ));
        } catch (\RuntimeException $e) {
            Log::error('Airdrop unlock NOWPayments error', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return redirect()->route('user.airdrop')
                ->with('dismiss', __('Payment gateway error. Please try again in a moment.'));
        } catch (\Exception $e) {
            Log::error('Airdrop unlock request failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return redirect()->route('user.airdrop')->with('dismiss', __('Something went wrong. Please try again.'));
        }
    }

    // ─── Confirm unlock (webhook / callback) ─────────────────────────────────

    /**
     * Legacy endpoint kept for backward compatibility.
     */
    public function confirmUnlock(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => __('On-chain unlock is disabled. Please use the off-chain withdrawal payment flow.'),
        ], 410);
    }

    private function getAirdropWithdrawConfig(): array
    {
        $enabled = (int) (settings(AIRDROP_WITHDRAW_ENABLED_SLUG) ?: 0) === 1;

        $payCurrency = strtolower(trim((string) (settings(AIRDROP_WITHDRAW_PAY_CURRENCY_SLUG) ?: 'usdtbsc')));
        if (!preg_match('/^[a-z0-9_]+$/', $payCurrency)) {
            $payCurrency = 'usdtbsc';
        }

        return [$enabled, $payCurrency];
    }

    private function getUserTotalPurchasedUsd(int $userId): float
    {
        return (float) BuyCoinHistory::where('user_id', $userId)
            ->where('status', STATUS_SUCCESS)
            ->sum('doller');
    }

    private function resolveUserWithdrawFee(AirdropCampaign $campaign, float $userTotalPurchasedUsd): float
    {
        $resolved = $campaign->resolveUnlockFeeByPurchaseUsd($userTotalPurchasedUsd);

        return is_numeric($resolved) ? (float) $resolved : 0.0;
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
