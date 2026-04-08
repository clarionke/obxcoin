<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add on-chain fields to ico_phases and buy_coin_histories.
     *
     * ico_phases:
     *   contract_phase_index  — index in OBXPresale.phases[] array after addPhase() is called on BSC
     *   contract_synced       — whether this phase has been pushed on-chain
     *
     * buy_coin_histories:
     *   tx_hash               — BSC transaction hash from TokensPurchased event
     *   buyer_wallet          — buyer's BSC wallet address
     */
    public function up(): void
    {
        Schema::table('ico_phases', function (Blueprint $table) {
            $table->unsignedInteger('contract_phase_index')->nullable()->after('affiliation_percentage')
                ->comment('Index in OBXPresale.phases[] array on BSC');
            $table->boolean('contract_synced')->default(false)->after('contract_phase_index')
                ->comment('True once addPhase() tx confirmed on-chain');
        });

        Schema::table('buy_coin_histories', function (Blueprint $table) {
            $table->string('tx_hash', 66)->nullable()->after('stripe_token')
                ->comment('BSC transaction hash');
            $table->string('buyer_wallet', 42)->nullable()->after('tx_hash')
                ->comment('Buyer BSC wallet address');
        });
    }

    public function down(): void
    {
        Schema::table('ico_phases', function (Blueprint $table) {
            $table->dropColumn(['contract_phase_index', 'contract_synced']);
        });

        Schema::table('buy_coin_histories', function (Blueprint $table) {
            $table->dropColumn(['tx_hash', 'buyer_wallet']);
        });
    }
};
