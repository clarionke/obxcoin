<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNowpaymentsFieldsToAirdropUnlocksTable extends Migration
{
    public function up()
    {
        Schema::table('airdrop_unlocks', function (Blueprint $table) {
            $table->string('nowpayments_payment_id', 80)->nullable();
            $table->string('nowpayments_order_id', 80)->nullable();
            $table->string('nowpayments_pay_address', 191)->nullable();
            $table->string('nowpayments_pay_amount', 40)->nullable();
            $table->string('nowpayments_pay_currency', 30)->nullable();
            $table->string('nowpayments_payment_status', 30)->default('waiting');

            $table->index('nowpayments_payment_id', 'airdrop_unlocks_np_payment_id_idx');
        });
    }

    public function down()
    {
        Schema::table('airdrop_unlocks', function (Blueprint $table) {
            $table->dropIndex('airdrop_unlocks_np_payment_id_idx');
            $table->dropColumn([
                'nowpayments_payment_id',
                'nowpayments_order_id',
                'nowpayments_pay_address',
                'nowpayments_pay_amount',
                'nowpayments_pay_currency',
                'nowpayments_payment_status',
            ]);
        });
    }
}
