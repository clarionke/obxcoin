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
            $table->string('obx_delivery_status', 24)->default('pending')->after('status');
            $table->string('obx_delivery_tx_hash', 66)->nullable()->after('tx_hash');
            $table->string('obx_delivery_error', 500)->nullable()->after('obx_delivery_tx_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buy_coin_histories', function (Blueprint $table) {
            $table->dropColumn([
                'obx_delivery_status',
                'obx_delivery_tx_hash',
                'obx_delivery_error',
            ]);
        });
    }
};
