<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class AirdropUnlock extends Model
{
    protected $table = 'airdrop_unlocks';

    protected $fillable = [
        'user_id',
        'campaign_id',
        'usdt_paid',
        'tx_hash',
        'obx_released',
        'unlocked_at',
        'status',
        'nowpayments_payment_id',
        'nowpayments_order_id',
        'nowpayments_pay_address',
        'nowpayments_pay_amount',
        'nowpayments_pay_currency',
        'nowpayments_payment_status',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'usdt_paid' => 'decimal:6',
    ];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(AirdropCampaign::class, 'campaign_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
