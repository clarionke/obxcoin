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
            $table->string('claim_streak_bonus_no_purchase_obx', 36)->nullable()->after('claim_streak_gte_1000_days');
            $table->string('claim_streak_bonus_lt_50_obx', 36)->nullable()->after('claim_streak_bonus_no_purchase_obx');
            $table->string('claim_streak_bonus_lt_100_obx', 36)->nullable()->after('claim_streak_bonus_lt_50_obx');
            $table->string('claim_streak_bonus_lt_500_obx', 36)->nullable()->after('claim_streak_bonus_lt_100_obx');
            $table->string('claim_streak_bonus_lt_1000_obx', 36)->nullable()->after('claim_streak_bonus_lt_500_obx');
            $table->string('claim_streak_bonus_gte_1000_obx', 36)->nullable()->after('claim_streak_bonus_lt_1000_obx');
        });

        DB::table('airdrop_campaigns')
            ->select('id', 'streak_bonus_amount')
            ->orderBy('id')
            ->chunkById(200, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    $bonusAmount = is_numeric($campaign->streak_bonus_amount)
                        ? (string) $campaign->streak_bonus_amount
                        : '0';

                    DB::table('airdrop_campaigns')
                        ->where('id', $campaign->id)
                        ->update([
                            'claim_streak_bonus_no_purchase_obx' => $bonusAmount,
                            'claim_streak_bonus_lt_50_obx' => $bonusAmount,
                            'claim_streak_bonus_lt_100_obx' => $bonusAmount,
                            'claim_streak_bonus_lt_500_obx' => $bonusAmount,
                            'claim_streak_bonus_lt_1000_obx' => $bonusAmount,
                            'claim_streak_bonus_gte_1000_obx' => $bonusAmount,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airdrop_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'claim_streak_bonus_no_purchase_obx',
                'claim_streak_bonus_lt_50_obx',
                'claim_streak_bonus_lt_100_obx',
                'claim_streak_bonus_lt_500_obx',
                'claim_streak_bonus_lt_1000_obx',
                'claim_streak_bonus_gte_1000_obx',
            ]);
        });
    }
};
