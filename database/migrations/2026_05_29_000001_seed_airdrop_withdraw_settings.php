<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedAirdropWithdrawSettings extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('admin_settings')->updateOrInsert(
            ['slug' => AIRDROP_WITHDRAW_ENABLED_SLUG],
            ['value' => '0', 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('admin_settings')->updateOrInsert(
            ['slug' => AIRDROP_WITHDRAW_FEE_USDT_SLUG],
            ['value' => '5.00', 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('admin_settings')->updateOrInsert(
            ['slug' => AIRDROP_WITHDRAW_PAY_CURRENCY_SLUG],
            ['value' => 'usdtbsc', 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('admin_settings')->updateOrInsert(
            ['slug' => AIRDROP_WITHDRAW_FEE_VISIBLE_SLUG],
            ['value' => '0', 'updated_at' => $now, 'created_at' => $now]
        );
    }

    public function down()
    {
        DB::table('admin_settings')
            ->whereIn('slug', [
                AIRDROP_WITHDRAW_ENABLED_SLUG,
                AIRDROP_WITHDRAW_FEE_USDT_SLUG,
                AIRDROP_WITHDRAW_PAY_CURRENCY_SLUG,
                AIRDROP_WITHDRAW_FEE_VISIBLE_SLUG,
            ])
            ->delete();
    }
}
