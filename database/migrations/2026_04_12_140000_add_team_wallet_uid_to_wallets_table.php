<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamWalletUidToWalletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'team_wallet_uid')) {
                $table->string('team_wallet_uid', 40)->nullable()->after('key');
                $table->unique('team_wallet_uid', 'wallets_team_wallet_uid_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (Schema::hasColumn('wallets', 'team_wallet_uid')) {
                $table->dropUnique('wallets_team_wallet_uid_unique');
                $table->dropColumn('team_wallet_uid');
            }
        });
    }
}
