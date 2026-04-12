<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class StakingPool extends Model
{
    protected $table = 'staking_pools';

    protected $fillable = [
        'name',
        'pool_id_onchain',
        'min_amount',
        'duration_days',
        'apy_bps',
        'burn_on_stake_bps',
        'burn_on_unstake_bps',
        'description',
        'status',
    ];

    public function positions()
    {
        return $this->hasMany(StakingPosition::class, 'pool_id');
    }

    /**
     * APY as a human-readable percentage string, e.g. "5.00 %"
     */
    public function getApyPercentAttribute(): string
    {
        return number_format($this->apy_bps / 100, 2) . ' %';
    }

    /**
     * Burn rate on stake as readable percentage, e.g. "1.00 %"
     */
    public function getBurnStakePctAttribute(): string
    {
        return number_format($this->burn_on_stake_bps / 100, 2) . ' %';
    }

    /**
     * Burn rate on unstake as readable percentage, e.g. "2.00 %"
     */
    public function getBurnUnstakePctAttribute(): string
    {
        return number_format($this->burn_on_unstake_bps / 100, 2) . ' %';
    }
}
