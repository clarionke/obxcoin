<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('buy_coin_histories', function (Blueprint $table) {
            // NOWPayments payment tracking
            $table->string('nowpayments_payment_id')->nullable()->unique()->after('stripe_token');
            $table->string('nowpayments_pay_address')->nullable()->after('nowpayments_payment_id');
            $table->decimal('nowpayments_pay_amount', 30, 18)->nullable()->after('nowpayments_pay_address');
            $table->string('nowpayments_pay_currency', 20)->nullable()->after('nowpayments_pay_amount');
            // WalletConnect on-chain buyer address
            $table->string('wc_buyer_address', 42)->nullable()->after('nowpayments_pay_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buy_coin_histories', function (Blueprint $table) {
            $table->dropColumn([
                'nowpayments_payment_id',
                'nowpayments_pay_address',
                'nowpayments_pay_amount',
                'nowpayments_pay_currency',
                'wc_buyer_address',
            ]);
        });
    }
};
