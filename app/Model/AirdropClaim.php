<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class AirdropClaim extends Model
{
    protected $table = 'airdrop_claims';

    protected $fillable = [
        'user_id',
        'campaign_id',
        'claim_date',
        'amount_obx',
    ];

    protected $casts = [
        'claim_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(AirdropCampaign::class, 'campaign_id');
    }
}
