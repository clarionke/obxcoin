<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedObxWithdrawalFeeSetting extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admin_settings')) {
            return;
        }

        $slug = 'obx_withdrawal_fee_percent';
        $exists = DB::table('admin_settings')->where('slug', $slug)->exists();
        if ($exists) {
            return;
        }

        $defaultFee = '0';
        if (Schema::hasTable('coins')) {
            $coinFee = DB::table('coins')
                ->where('type', 'OBXCoin')
                ->value('withdrawal_fees');

            if ($coinFee !== null && $coinFee !== '' && is_numeric((string) $coinFee)) {
                $defaultFee = (string) $coinFee;
            }
        }

        DB::table('admin_settings')->insert([
            'slug' => $slug,
            'value' => $defaultFee,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('admin_settings')) {
            return;
        }

        DB::table('admin_settings')->where('slug', 'obx_withdrawal_fee_percent')->delete();
    }
}
