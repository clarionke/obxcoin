<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class BuyCoinHistory extends Model
{
    protected $fillable = ['confirmations','status','obx_delivery_status','obx_delivery_tx_hash','obx_delivery_error','coin_type','phase_id','referral_level','fees','bonus','requested_amount','referral_bonus','stripe_token','tx_hash','buyer_wallet','nowpayments_payment_id','nowpayments_pay_address','nowpayments_pay_amount','nowpayments_pay_currency','wc_buyer_address'];

    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
}
