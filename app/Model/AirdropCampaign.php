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

    /**
     * Total OBX claimed so far across all users (bcmath-safe string sum).
     */
    public function totalClaimedObx(): string
    {
        return $this->claims()->sum('amount_obx') ?: '0';
    }
}
