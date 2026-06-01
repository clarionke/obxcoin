<?php

namespace App\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AirdropCampaign extends Model
{
    protected $table = 'airdrop_campaigns';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'daily_claim_amount',
        'streak_days',
        'streak_bonus_amount',
        'claim_daily_no_purchase_obx',
        'claim_daily_lt_50_obx',
        'claim_daily_lt_100_obx',
        'claim_daily_lt_500_obx',
        'claim_daily_lt_1000_obx',
        'claim_daily_gte_1000_obx',
        'claim_streak_no_purchase_days',
        'claim_streak_lt_50_days',
        'claim_streak_lt_100_days',
        'claim_streak_lt_500_days',
        'claim_streak_lt_1000_days',
        'claim_streak_gte_1000_days',
        'claim_streak_bonus_no_purchase_obx',
        'claim_streak_bonus_lt_50_obx',
        'claim_streak_bonus_lt_100_obx',
        'claim_streak_bonus_lt_500_obx',
        'claim_streak_bonus_lt_1000_obx',
        'claim_streak_bonus_gte_1000_obx',
        'unlock_fee_usdt',
        'unlock_fee_lt_100_usdt',
        'unlock_fee_lt_500_usdt',
        'unlock_fee_lt_1000_usdt',
        'unlock_fee_gte_1000_usdt',
        'fee_revealed',
        'contract_address',
        'chain_id',
        'is_active',
    ];

    protected $casts = [
        'start_date'   => 'datetime',
        'end_date'     => 'datetime',
        'claim_streak_no_purchase_days' => 'integer',
        'claim_streak_lt_50_days' => 'integer',
        'claim_streak_lt_100_days' => 'integer',
        'claim_streak_lt_500_days' => 'integer',
        'claim_streak_lt_1000_days' => 'integer',
        'claim_streak_gte_1000_days' => 'integer',
        'unlock_fee_usdt' => 'decimal:6',
        'unlock_fee_lt_100_usdt' => 'decimal:6',
        'unlock_fee_lt_500_usdt' => 'decimal:6',
        'unlock_fee_lt_1000_usdt' => 'decimal:6',
        'unlock_fee_gte_1000_usdt' => 'decimal:6',
        'fee_revealed' => 'boolean',
        'is_active'    => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function claims()
    {
        return $this->hasMany(AirdropClaim::class, 'campaign_id');
    }

    public function unlocks()
    {
        return $this->hasMany(AirdropUnlock::class, 'campaign_id');
    }

    // ─── Computed helpers ─────────────────────────────────────────────────────

    public function isLive(): bool
    {
        $now = Carbon::now();
        return $this->is_active
            && $now->gte($this->start_date)
            && $now->lt($this->end_date);
    }

    public function hasEnded(): bool
    {
        return Carbon::now()->gte($this->end_date);
    }

    public function hasStarted(): bool
    {
        return Carbon::now()->gte($this->start_date);
    }

    public function canUnlock(): bool
    {
        return $this->hasEnded() && $this->fee_revealed;
    }

    public function resolveUnlockFeeByPurchaseUsd(float $purchasedUsd): ?float
    {
        $purchasedUsd = max(0, $purchasedUsd);

        if ($purchasedUsd < 100) {
            $fee = $this->unlock_fee_lt_100_usdt;
        } elseif ($purchasedUsd < 500) {
            $fee = $this->unlock_fee_lt_500_usdt;
        } elseif ($purchasedUsd < 1000) {
            $fee = $this->unlock_fee_lt_1000_usdt;
        } else {
            $fee = $this->unlock_fee_gte_1000_usdt;
        }

        if (is_numeric($fee) && (float) $fee > 0) {
            return (float) $fee;
        }

        if (is_numeric($this->unlock_fee_usdt) && (float) $this->unlock_fee_usdt > 0) {
            return (float) $this->unlock_fee_usdt;
        }

        return null;
    }

    public function resolveUnlockFeeTierLabel(float $purchasedUsd): string
    {
        $purchasedUsd = max(0, $purchasedUsd);

        if ($purchasedUsd < 100) {
            return '< 100 USD';
        }
        if ($purchasedUsd < 500) {
            return '100 - 499.99 USD';
        }
        if ($purchasedUsd < 1000) {
            return '500 - 999.99 USD';
        }

        return '>= 1000 USD';
    }

    public function resolveClaimConfigByPurchaseUsd(float $purchasedUsd): array
    {
        $purchasedUsd = max(0, $purchasedUsd);

        $tiers = $this->claimTierMatrix();
        $currentTierIndex = $this->resolveClaimTierIndex($purchasedUsd);
        $currentTier = $tiers[$currentTierIndex] ?? $tiers[0];
        $nextTier = $tiers[$currentTierIndex + 1] ?? null;

        $amountToNextUsd = 0.0;
        if ($nextTier) {
            $nextTierMinUsd = (float) ($nextTier['min_usd'] ?? 0);
            $amountToNextUsd = max(0, round($nextTierMinUsd - $purchasedUsd, 2));
        }

        return [
            'daily_claim_amount' => (string) ($currentTier['daily_claim_amount'] ?? '0'),
            'streak_days' => (int) ($currentTier['streak_days'] ?? 1),
            'streak_bonus_amount' => (string) ($currentTier['streak_bonus_amount'] ?? '0'),
            'tier_label' => (string) ($currentTier['tier_label'] ?? $currentTier['tier_name'] ?? '--'),
            'tier_name' => (string) ($currentTier['tier_name'] ?? '--'),
            'tier_requirement' => (string) ($currentTier['requirement_label'] ?? '--'),
            'tier_code' => (string) ($currentTier['tier_code'] ?? 'unknown'),
            'tier_index' => $currentTierIndex,
            'tiers' => $tiers,
            'has_next_tier' => $nextTier !== null,
            'next_tier_name' => $nextTier ? (string) ($nextTier['tier_name'] ?? null) : null,
            'next_tier_requirement' => $nextTier ? (string) ($nextTier['requirement_label'] ?? null) : null,
            'next_tier_min_usd' => $nextTier ? (float) ($nextTier['min_usd'] ?? 0) : null,
            'amount_to_next_usd' => $amountToNextUsd,
        ];
    }

    public function claimTierMatrix(): array
    {
        return [
            [
                'tier_code' => 'seed_explorer',
                'tier_name' => 'Seed Explorer',
                'tier_label' => 'Seed Explorer',
                'min_usd' => 0.00,
                'max_usd' => 0.00,
                'requirement_label' => 'Total buy = 0 USD',
                'daily_claim_amount' => $this->resolveClaimTierDailyAmount($this->claim_daily_no_purchase_obx),
                'streak_days' => $this->resolveClaimTierStreakDays($this->claim_streak_no_purchase_days),
                'streak_bonus_amount' => $this->resolveClaimTierBonusAmount($this->claim_streak_bonus_no_purchase_obx),
            ],
            [
                'tier_code' => 'bronze_builder',
                'tier_name' => 'Bronze Builder',
                'tier_label' => 'Bronze Builder',
                'min_usd' => 1.00,
                'max_usd' => 50.00,
                'requirement_label' => '1.00 - 50.00 USD total buy',
                'daily_claim_amount' => $this->resolveClaimTierDailyAmount($this->claim_daily_lt_50_obx),
                'streak_days' => $this->resolveClaimTierStreakDays($this->claim_streak_lt_50_days),
                'streak_bonus_amount' => $this->resolveClaimTierBonusAmount($this->claim_streak_bonus_lt_50_obx),
            ],
            [
                'tier_code' => 'silver_strider',
                'tier_name' => 'Silver Strider',
                'tier_label' => 'Silver Strider',
                'min_usd' => 51.00,
                'max_usd' => 100.00,
                'requirement_label' => '51.00 - 100.00 USD total buy',
                'daily_claim_amount' => $this->resolveClaimTierDailyAmount($this->claim_daily_lt_100_obx),
                'streak_days' => $this->resolveClaimTierStreakDays($this->claim_streak_lt_100_days),
                'streak_bonus_amount' => $this->resolveClaimTierBonusAmount($this->claim_streak_bonus_lt_100_obx),
            ],
            [
                'tier_code' => 'gold_grinder',
                'tier_name' => 'Gold Grinder',
                'tier_label' => 'Gold Grinder',
                'min_usd' => 101.00,
                'max_usd' => 500.00,
                'requirement_label' => '101.00 - 500.00 USD total buy',
                'daily_claim_amount' => $this->resolveClaimTierDailyAmount($this->claim_daily_lt_500_obx),
                'streak_days' => $this->resolveClaimTierStreakDays($this->claim_streak_lt_500_days),
                'streak_bonus_amount' => $this->resolveClaimTierBonusAmount($this->claim_streak_bonus_lt_500_obx),
            ],
            [
                'tier_code' => 'platinum_pioneer',
                'tier_name' => 'Platinum Pioneer',
                'tier_label' => 'Platinum Pioneer',
                'min_usd' => 501.00,
                'max_usd' => 1000.00,
                'requirement_label' => '501.00 - 1000.00 USD total buy',
                'daily_claim_amount' => $this->resolveClaimTierDailyAmount($this->claim_daily_lt_1000_obx),
                'streak_days' => $this->resolveClaimTierStreakDays($this->claim_streak_lt_1000_days),
                'streak_bonus_amount' => $this->resolveClaimTierBonusAmount($this->claim_streak_bonus_lt_1000_obx),
            ],
            [
                'tier_code' => 'diamond_titan',
                'tier_name' => 'Diamond Titan',
                'tier_label' => 'Diamond Titan',
                'min_usd' => 1001.00,
                'max_usd' => null,
                'requirement_label' => '>= 1001.00 USD total buy',
                'daily_claim_amount' => $this->resolveClaimTierDailyAmount($this->claim_daily_gte_1000_obx),
                'streak_days' => $this->resolveClaimTierStreakDays($this->claim_streak_gte_1000_days),
                'streak_bonus_amount' => $this->resolveClaimTierBonusAmount($this->claim_streak_bonus_gte_1000_obx),
            ],
        ];
    }

    private function resolveClaimTierIndex(float $purchasedUsd): int
    {
        if ($purchasedUsd <= 0) {
            return 0;
        }

        if ($purchasedUsd <= 50) {
            return 1;
        }

        if ($purchasedUsd <= 100) {
            return 2;
        }

        if ($purchasedUsd <= 500) {
            return 3;
        }

        if ($purchasedUsd <= 1000) {
            return 4;
        }

        return 5;
    }

    private function resolveClaimTierDailyAmount($rawAmount): string
    {
        if (is_numeric($rawAmount) && (float) $rawAmount > 0) {
            return function_exists('bcmul')
                ? bcmul((string) $rawAmount, '1', 18)
                : (string) $rawAmount;
        }

        if (is_numeric($this->daily_claim_amount) && (float) $this->daily_claim_amount > 0) {
            return function_exists('bcmul')
                ? bcmul((string) $this->daily_claim_amount, '1', 18)
                : (string) $this->daily_claim_amount;
        }

        return '0';
    }

    private function resolveClaimTierStreakDays($rawStreakDays): int
    {
        if (is_numeric($rawStreakDays) && (int) $rawStreakDays > 0) {
            return min(365, max(1, (int) $rawStreakDays));
        }

        return min(365, max(1, (int) ($this->streak_days ?? 5)));
    }

    private function resolveClaimTierBonusAmount($rawBonusAmount): string
    {
        if (is_numeric($rawBonusAmount) && (float) $rawBonusAmount >= 0) {
            return function_exists('bcmul')
                ? bcmul((string) $rawBonusAmount, '1', 18)
                : (string) $rawBonusAmount;
        }

        if (is_numeric($this->streak_bonus_amount) && (float) $this->streak_bonus_amount >= 0) {
            return function_exists('bcmul')
                ? bcmul((string) $this->streak_bonus_amount, '1', 18)
                : (string) $this->streak_bonus_amount;
        }

        return '0';
    }

    /**
     * Total OBX claimed so far across all users (bcmath-safe string sum).
     */
    public function totalClaimedObx(): string
    {
        return $this->claims()->sum('amount_obx') ?: '0';
    }
}
