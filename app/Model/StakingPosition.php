<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class StakingPosition extends Model
{
    protected $table = 'staking_positions';

    protected $fillable = [
        'user_id',
        'pool_id',
        'wallet_address',
        'contract_stake_idx',
        'gross_amount',
        'burned_on_stake',
        'net_amount',
        'reward_earned',
        'burned_on_unstake',
        'returned_amount',
        'status',
        'tx_hash_stake',
        'tx_hash_unstake',
        'staked_at',
        'lock_until',
        'unstaked_at',
    ];

    protected $dates = ['staked_at', 'lock_until', 'unstaked_at'];

    public function pool()
    {
        return $this->belongsTo(StakingPool::class, 'pool_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(StakingTransaction::class, 'position_id');
    }

    /**
     * True if stake is currently locked (lock period has not expired).
     */
    public function isLocked(): bool
    {
        return $this->status === 'active' && $this->lock_until && now()->lt($this->lock_until);
    }

    /**
     * Seconds remaining until unlock.
     */
    public function secondsUntilUnlock(): int
    {
        if (!$this->lock_until || !$this->isLocked()) return 0;
        return (int) now()->diffInSeconds($this->lock_until, false);
    }
}
