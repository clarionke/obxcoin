<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class StakingTransaction extends Model
{
    protected $table = 'staking_transactions';

    protected $fillable = [
        'user_id',
        'position_id',
        'type',
        'amount',
        'tx_hash',
        'status',
        'note',
    ];

    // type constants
    const TYPE_STAKE_IN       = 'stake_in';
    const TYPE_UNSTAKE_OUT    = 'unstake_out';
    const TYPE_BURN_STAKE     = 'burn_stake';
    const TYPE_BURN_UNSTAKE   = 'burn_unstake';
    const TYPE_REWARD         = 'reward';

    public function position()
    {
        return $this->belongsTo(StakingPosition::class, 'position_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }
}
