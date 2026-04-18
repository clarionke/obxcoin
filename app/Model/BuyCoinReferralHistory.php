<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BuyCoinReferralHistory extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'buy_id',
        'phase_id',
        'child_id',
        'level',
        'system_fees',
        'amount',
        'tx_hash',
        'status'
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}
