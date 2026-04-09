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
        'unlock_fee_usdt',
        'fee_revealed',
        'contract_address',
        'chain_id',
        'is_active',
    ];

    protected $casts = [
        'start_date'   => 'datetime',
        'end_date'     => 'datetime',
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

    /**
     * Total OBX claimed so far across all users (bcmath-safe string sum).
     */
    public function totalClaimedObx(): string
    {
        return $this->claims()->sum('amount_obx') ?: '0';
    }
}
