<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class CoWalletSignatoryChangeApproval extends Model
{
    protected $fillable = ['request_id', 'wallet_id', 'user_id'];

    public function request()
    {
        return $this->belongsTo(CoWalletSignatoryChangeRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
