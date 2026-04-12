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
        Schema::table('ico_phases', function (Blueprint $table) {
            // Stores the tx hash of a broadcast (but not yet confirmed) on-chain
            // phase add/update. Prevents double-push: if non-null, another admin
            // action is already in flight and will be blocked until confirmed.
            $table->string('pending_onchain_tx', 66)->nullable()->after('contract_synced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ico_phases', function (Blueprint $table) {
            $table->dropColumn('pending_onchain_tx');
        });
    }
};
