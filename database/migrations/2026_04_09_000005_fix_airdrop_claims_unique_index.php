<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original unique index on (user_id, campaign_id, claim_date) prevents
 * inserting a streak bonus claim on the same day as the regular daily claim.
 * Drop it and replace with (user_id, campaign_id, claim_date, is_bonus) so
 * each user can have at most one regular claim AND one bonus claim per day.
 */
class FixAirdropClaimsUniqueIndex extends Migration
{
    public function up()
    {
        Schema::table('airdrop_claims', function (Blueprint $table) {
            // Add the new, broader unique index first so MySQL has a covering
            // index for the foreign keys before we drop the old one.
            $table->unique(['user_id', 'campaign_id', 'claim_date', 'is_bonus'],
                'ac_user_campaign_date_bonus_unique');
        });

        Schema::table('airdrop_claims', function (Blueprint $table) {
            // Now safe to drop the old index — the new one covers FK resolution.
            $table->dropUnique('airdrop_claims_user_id_campaign_id_claim_date_unique');
        });
    }

    public function down()
    {
        Schema::table('airdrop_claims', function (Blueprint $table) {
            $table->unique(['user_id', 'campaign_id', 'claim_date'],
                'airdrop_claims_user_id_campaign_id_claim_date_unique');
        });

        Schema::table('airdrop_claims', function (Blueprint $table) {
            $table->dropUnique('ac_user_campaign_date_bonus_unique');
        });
    }
}
