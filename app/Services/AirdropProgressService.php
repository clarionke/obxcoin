<?php

namespace App\Services;

use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
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
                'isMilestoneToday' => false,
                'congratsMessage' => __('No active airdrop campaign right now. Stay tuned!'),
                'congratsTone' => 'neutral',
            ];
        }

        $today = Carbon::today();
        $streakDays = max(1, (int) ($campaign->streak_days ?? 5));
        $bonusAmount = (string) ($campaign->streak_bonus_amount ?? '0');

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
            'isMilestoneToday' => $isMilestoneToday,
            'congratsMessage' => $congratsMessage,
            'congratsTone' => $congratsTone,
        ];
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
