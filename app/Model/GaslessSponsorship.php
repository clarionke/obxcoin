<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class GaslessSponsorship extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_address',
        'action',
        'chain_id',
        'gas_amount_native',
        'estimated_gas_limit',
        'status',
        'tx_hash',
        'error_message',
    ];
}
