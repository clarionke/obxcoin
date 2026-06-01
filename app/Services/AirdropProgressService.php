<?php

namespace App\Services;

use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\BuyCoinHistory;
use Carbon\Carbon;

class AirdropProgressService
{
    /**
     * Build a normalized streak/progress payload for a user.
     */
    public function buildForUser(int $userId): array
    {
        $campaign = AirdropCampaign::where('is_active', true)
            ->where('end_date', '>', now())
            ->orderByRaw('CASE WHEN start_date <= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('start_date', 'asc')
            ->first();

        if (!$campaign) {
            return [
                'available' => false,
                'campaign' => null,
                'isLive' => false,
                'claimedToday' => false,
                'todayStreak' => 0,
                'remainingStreak' => 0,
                'streakDays' => 0,
                'filledDays' => 0,
                'progressPercent' => 0,
                'streakBonusAmount' => '0',
                'dailyClaimAmount' => '0',
                'claimTierLabel' => '--',
                'claimTierName' => '--',
                'claimTierRequirement' => '--',
                'claimTierRows' => [],
                'hasNextTier' => false,
                'nextTierName' => null,
                'nextTierRequirement' => null,
                'nextTierMinUsd' => null,
                'amountToNextUsd' => 0.0,
                'isMilestoneToday' => false,
                'congratsMessage' => __('No active airdrop campaign right now. Stay tuned!'),
                'congratsTone' => 'neutral',
            ];
        }

        $today = Carbon::today();
        $userTotalPurchasedUsd = $this->getUserTotalPurchasedUsd($userId);
        $claimTierConfig = $campaign->resolveClaimConfigByPurchaseUsd($userTotalPurchasedUsd);
        $streakDays = max(1, (int) ($claimTierConfig['streak_days'] ?? $campaign->streak_days ?? 5));
        $bonusAmount = (string) ($claimTierConfig['streak_bonus_amount'] ?? $campaign->streak_bonus_amount ?? '0');
        $dailyClaimAmount = (string) ($claimTierConfig['daily_claim_amount'] ?? '0');
        $claimTierLabel = (string) ($claimTierConfig['tier_label'] ?? '--');
        $claimTierName = (string) ($claimTierConfig['tier_name'] ?? '--');
        $claimTierRequirement = (string) ($claimTierConfig['tier_requirement'] ?? '--');
        $claimTierRows = (array) ($claimTierConfig['tiers'] ?? []);
        $hasNextTier = !empty($claimTierConfig['has_next_tier']);
        $nextTierName = $claimTierConfig['next_tier_name'] ?? null;
        $nextTierRequirement = $claimTierConfig['next_tier_requirement'] ?? null;
        $nextTierMinUsd = isset($claimTierConfig['next_tier_min_usd']) ? (float) $claimTierConfig['next_tier_min_usd'] : null;
        $amountToNextUsd = isset($claimTierConfig['amount_to_next_usd']) ? (float) $claimTierConfig['amount_to_next_usd'] : 0.0;

        $claimTierRows = array_map(function (array $row, int $index) use ($claimTierConfig, $userTotalPurchasedUsd) {
            $row['is_current'] = ((int) ($claimTierConfig['tier_index'] ?? -1)) === $index;
            $row['is_next'] = ((int) ($claimTierConfig['tier_index'] ?? -1)) + 1 === $index;
            $row['is_locked'] = !$row['is_current'] && $userTotalPurchasedUsd < (float) ($row['min_usd'] ?? 0);
            return $row;
        }, $claimTierRows, array_keys($claimTierRows));

        $claimedToday = AirdropClaim::where('user_id', $userId)
            ->where('campaign_id', (int) $campaign->id)
            ->whereDate('claim_date', $today)
            ->where('is_bonus', false)
            ->exists();

        $todayStreak = $this->getCurrentStreak($userId, (int) $campaign->id, $today);
        $mod = $todayStreak % $streakDays;
        $filledDays = ($mod === 0 && $todayStreak > 0) ? $streakDays : $mod;

        $isMilestoneToday = $claimedToday && $todayStreak > 0 && $mod === 0;
        $remainingStreak = $isMilestoneToday ? 0 : max(0, $streakDays - $mod);

        if ($todayStreak === 0) {
            $remainingStreak = $streakDays;
        }

        $progressPercent = (int) round(($filledDays / $streakDays) * 100);
        $congratsTone = 'info';

        if (!$campaign->isLive()) {
            if (!$campaign->hasStarted()) {
                $congratsMessage = __('Campaign starts :when. Get ready to begin your streak.', [
                    'when' => $campaign->start_date->diffForHumans(),
                ]);
                $congratsTone = 'info';
            } else {
                $congratsMessage = __('Campaign has ended. Great effort on your streak journey.');
                $congratsTone = 'neutral';
            }
        } elseif ($claimedToday) {
            if ($isMilestoneToday) {
                $congratsMessage = __('Congrats! You completed a :days-day streak today and hit your bonus.', [
                    'days' => $streakDays,
                ]);
                $congratsTone = 'success';
            } else {
                $congratsMessage = __('Great job! You claimed today. Only :days day(s) left to your next streak bonus.', [
                    'days' => $remainingStreak,
                ]);
                $congratsTone = 'success';
            }
        } else {
            if ($todayStreak > 0) {
                $congratsMessage = __('You are on a :streak-day streak. Claim today to keep it alive.', [
                    'streak' => $todayStreak,
                ]);
                $congratsTone = 'warning';
            } else {
                $congratsMessage = __('Start today and build your first streak milestone.');
                $congratsTone = 'info';
            }
        }

        return [
            'available' => true,
            'campaign' => $campaign,
            'isLive' => $campaign->isLive(),
            'claimedToday' => $claimedToday,
            'todayStreak' => $todayStreak,
            'remainingStreak' => $remainingStreak,
            'streakDays' => $streakDays,
            'filledDays' => $filledDays,
            'progressPercent' => max(0, min(100, $progressPercent)),
            'streakBonusAmount' => $bonusAmount,
            'dailyClaimAmount' => $dailyClaimAmount,
            'claimTierLabel' => $claimTierLabel,
            'claimTierName' => $claimTierName,
            'claimTierRequirement' => $claimTierRequirement,
            'claimTierRows' => $claimTierRows,
            'hasNextTier' => $hasNextTier,
            'nextTierName' => $nextTierName,
            'nextTierRequirement' => $nextTierRequirement,
            'nextTierMinUsd' => $nextTierMinUsd,
            'amountToNextUsd' => $amountToNextUsd,
            'isMilestoneToday' => $isMilestoneToday,
            'congratsMessage' => $congratsMessage,
            'congratsTone' => $congratsTone,
        ];
    }

    private function getUserTotalPurchasedUsd(int $userId): float
    {
        return (float) BuyCoinHistory::where('user_id', $userId)
            ->where('status', STATUS_SUCCESS)
            ->sum('doller');
    }

    /**
     * Count consecutive non-bonus claim days ending today.
     */
    private function getCurrentStreak(int $userId, int $campaignId, Carbon $today): int
    {
        $streak = 0;
        $day = $today->copy();

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
