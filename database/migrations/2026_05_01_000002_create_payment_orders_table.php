<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();                          // Public-facing identifier
            $table->unsignedBigInteger('merchant_id');               // FK → merchant_api_keys.id
            $table->string('merchant_order_id', 200)->nullable();    // Merchant's own reference
            $table->string('coin_type', 50);                         // e.g. 'OBXCoin', 'BTC'
            $table->unsignedBigInteger('coin_id')->nullable();       // FK → coins.id
            $table->decimal('amount', 28, 8);                        // Amount expected
            $table->decimal('amount_received', 28, 8)->default(0);   // Amount detected on-chain
            $table->string('pay_address', 200);                      // Address buyer should send to
            // Status lifecycle: pending → confirming → completed | expired | underpaid
            $table->string('status', 20)->default('pending');
            $table->json('metadata')->nullable();                    // Arbitrary merchant data
            $table->text('callback_url')->nullable();                // Per-order override webhook URL
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('webhook_sent_at')->nullable();
            $table->text('webhook_response')->nullable();
            $table->string('transaction_hash', 200)->nullable();
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchant_api_keys')->onDelete('cascade');
            $table->foreign('coin_id')->references('id')->on('coins')->onDelete('set null');
            $table->index(['status', 'expires_at']);
            $table->index(['pay_address']);
            $table->index(['merchant_id', 'merchant_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
}
