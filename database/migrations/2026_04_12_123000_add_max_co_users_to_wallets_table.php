<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaxCoUsersToWalletsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('wallets', 'max_co_users')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->integer('max_co_users')->default(2)->after('type');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('wallets', 'max_co_users')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->dropColumn('max_co_users');
            });
        }
    }
}
