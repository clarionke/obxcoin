<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('airdrop_campaigns', function (Blueprint $table) {
            $table->decimal('unlock_fee_lt_100_usdt', 18, 6)->nullable()->after('unlock_fee_usdt');
            $table->decimal('unlock_fee_lt_500_usdt', 18, 6)->nullable()->after('unlock_fee_lt_100_usdt');
            $table->decimal('unlock_fee_lt_1000_usdt', 18, 6)->nullable()->after('unlock_fee_lt_500_usdt');
            $table->decimal('unlock_fee_gte_1000_usdt', 18, 6)->nullable()->after('unlock_fee_lt_1000_usdt');
        });

        DB::table('airdrop_campaigns')
            ->whereNotNull('unlock_fee_usdt')
            ->update([
                'unlock_fee_lt_100_usdt' => DB::raw('unlock_fee_usdt'),
                'unlock_fee_lt_500_usdt' => DB::raw('unlock_fee_usdt'),
                'unlock_fee_lt_1000_usdt' => DB::raw('unlock_fee_usdt'),
                'unlock_fee_gte_1000_usdt' => DB::raw('unlock_fee_usdt'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airdrop_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'unlock_fee_lt_100_usdt',
                'unlock_fee_lt_500_usdt',
                'unlock_fee_lt_1000_usdt',
                'unlock_fee_gte_1000_usdt',
            ]);
        });
    }
};
