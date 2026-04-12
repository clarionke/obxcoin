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
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'approval_timeout_minutes')) {
                $table->unsignedInteger('approval_timeout_minutes')
                    ->default(60)
                    ->after('max_co_users');
            }
        });

        Schema::table('temp_withdraws', function (Blueprint $table) {
            if (!Schema::hasColumn('temp_withdraws', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('status');
                $table->index('expires_at', 'temp_withdraws_expires_at_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temp_withdraws', function (Blueprint $table) {
            if (Schema::hasColumn('temp_withdraws', 'expires_at')) {
                $table->dropIndex('temp_withdraws_expires_at_index');
                $table->dropColumn('expires_at');
            }
        });

        Schema::table('wallets', function (Blueprint $table) {
            if (Schema::hasColumn('wallets', 'approval_timeout_minutes')) {
                $table->dropColumn('approval_timeout_minutes');
            }
        });
    }
};
