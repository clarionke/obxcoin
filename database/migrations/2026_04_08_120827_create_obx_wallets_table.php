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
        Schema::create('obx_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // Ethereum-compatible address (lowercase hex, 42 chars including 0x prefix)
            $table->string('address', 42)->unique();
            // AES-256-GCM encrypted private key via Laravel Crypt::encrypt()
            // Never expose raw; only used for signing outbound transactions
            $table->text('encrypted_private_key');
            // User-defined label for the wallet (e.g. "Main Wallet", "Trading")
            $table->string('label', 100)->default('Wallet');
            // Only one wallet per user can be primary
            $table->boolean('is_primary')->default(false);
            // Cached on-chain OBX balance (18 decimal precision) — refreshed on demand
            $table->decimal('cached_balance', 36, 18)->default('0.000000000000000000');
            $table->timestamp('balance_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obx_wallets');
    }
};
