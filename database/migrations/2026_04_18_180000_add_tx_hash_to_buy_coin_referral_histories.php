<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('buy_coin_referral_histories', 'tx_hash')) {
            Schema::table('buy_coin_referral_histories', function (Blueprint $table) {
                $table->string('tx_hash', 66)->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('buy_coin_referral_histories', 'tx_hash')) {
            Schema::table('buy_coin_referral_histories', function (Blueprint $table) {
                $table->dropColumn('tx_hash');
            });
        }
    }
};