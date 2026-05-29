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
            $table->string('claim_daily_no_purchase_obx', 36)->nullable()->after('streak_bonus_amount');
            $table->string('claim_daily_lt_50_obx', 36)->nullable()->after('claim_daily_no_purchase_obx');
            $table->string('claim_daily_lt_100_obx', 36)->nullable()->after('claim_daily_lt_50_obx');
            $table->string('claim_daily_lt_500_obx', 36)->nullable()->after('claim_daily_lt_100_obx');
            $table->string('claim_daily_lt_1000_obx', 36)->nullable()->after('claim_daily_lt_500_obx');
            $table->string('claim_daily_gte_1000_obx', 36)->nullable()->after('claim_daily_lt_1000_obx');

            $table->unsignedSmallInteger('claim_streak_no_purchase_days')->nullable()->after('claim_daily_gte_1000_obx');
            $table->unsignedSmallInteger('claim_streak_lt_50_days')->nullable()->after('claim_streak_no_purchase_days');
            $table->unsignedSmallInteger('claim_streak_lt_100_days')->nullable()->after('claim_streak_lt_50_days');
            $table->unsignedSmallInteger('claim_streak_lt_500_days')->nullable()->after('claim_streak_lt_100_days');
            $table->unsignedSmallInteger('claim_streak_lt_1000_days')->nullable()->after('claim_streak_lt_500_days');
            $table->unsignedSmallInteger('claim_streak_gte_1000_days')->nullable()->after('claim_streak_lt_1000_days');
        });

        DB::table('airdrop_campaigns')->update([
            'claim_daily_no_purchase_obx' => '2.000000000000000000',
            'claim_daily_lt_50_obx' => '3.500000000000000000',
            'claim_daily_lt_100_obx' => '5.000000000000000000',
            'claim_daily_lt_500_obx' => '10.000000000000000000',
            'claim_daily_lt_1000_obx' => '20.000000000000000000',
            'claim_daily_gte_1000_obx' => '25.000000000000000000',
            'claim_streak_no_purchase_days' => 15,
            'claim_streak_lt_50_days' => 20,
            'claim_streak_lt_100_days' => 30,
            'claim_streak_lt_500_days' => 50,
            'claim_streak_lt_1000_days' => 80,
            'claim_streak_gte_1000_days' => 100,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airdrop_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'claim_daily_no_purchase_obx',
                'claim_daily_lt_50_obx',
                'claim_daily_lt_100_obx',
                'claim_daily_lt_500_obx',
                'claim_daily_lt_1000_obx',
                'claim_daily_gte_1000_obx',
                'claim_streak_no_purchase_days',
                'claim_streak_lt_50_days',
                'claim_streak_lt_100_days',
                'claim_streak_lt_500_days',
                'claim_streak_lt_1000_days',
                'claim_streak_gte_1000_days',
            ]);
        });
    }
};
