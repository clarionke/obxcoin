<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CoWalletSignatoryChangeRequest extends Model
{
    protected $fillable = [
        'wallet_id',
        'requested_by_admin_id',
        'requested_by_user_id',
        'target_user_id',
        'target_wallet_co_user_id',
        'requested_can_approve',
        'status',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function approvals()
    {
        return $this->hasMany(CoWalletSignatoryChangeApproval::class, 'request_id');
    }
}
