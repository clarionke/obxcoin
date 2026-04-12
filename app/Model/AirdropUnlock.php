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
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
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
