<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantApiKeysTable extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_api_keys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('name', 100);                        // Friendly label
            $table->string('api_key', 64)->unique();            // Public identifier (prefix obx_)
            $table->string('api_secret_hash', 128);             // bcrypt-hashed secret
            $table->json('allowed_ips')->nullable();            // IP whitelist (null = any)
            $table->json('allowed_coins')->nullable();          // Coin types (null = all active)
            $table->text('webhook_url')->nullable();            // Default callback URL
            $table->string('webhook_secret', 64)->nullable();   // Used to sign outgoing webhooks
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_api_keys');
    }
}
