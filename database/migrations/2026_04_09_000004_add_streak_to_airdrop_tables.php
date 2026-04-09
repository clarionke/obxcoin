<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStreakToAirdropTables extends Migration
{
    public function up()
    {
        // Add streak gamification fields to campaigns
        Schema::table('airdrop_campaigns', function (Blueprint $table) {
            // How many consecutive days trigger a bonus (default 5)
            $table->tinyInteger('streak_days')->unsigned()->default(5)->after('daily_claim_amount');
            // OBX bonus amount awarded on each streak milestone (stored as string for bcmath)
            $table->string('streak_bonus_amount', 36)->default('0')->after('streak_days');
        });

        // Flag bonus claims so we don't count them in streak calculation
        Schema::table('airdrop_claims', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('amount_obx');
        });
    }

    public function down()
    {
        Schema::table('airdrop_campaigns', function (Blueprint $table) {
            $table->dropColumn(['streak_days', 'streak_bonus_amount']);
        });

        Schema::table('airdrop_claims', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });
    }
}
