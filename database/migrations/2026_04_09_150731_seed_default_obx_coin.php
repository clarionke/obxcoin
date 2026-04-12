<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Resolve coin name from admin_settings if available, else fall back.
        $coinName = DB::table('admin_settings')
            ->where('slug', 'coin_name')
            ->value('value') ?? 'OBXCoin';

        DB::table('coins')->updateOrInsert(
            ['type' => 'OBXCoin'],
            [
                'name'                => $coinName,
                'status'              => 1,
                'is_withdrawal'       => 1,
                'is_deposit'          => 1,
                'is_buy'              => 1,
                'is_sell'             => 1,
                'is_base'             => 1,
                'is_currency'         => 0,
                'is_wallet'           => 1,
                'is_transferable'     => 1,
                'is_virtual_amount'   => 0,
                'trade_status'        => 1,
                'minimum_buy_amount'  => 0.0000001,
                'minimum_sell_amount' => 0.0000001,
                'minimum_withdrawal'  => 0.0000001,
                'maximum_withdrawal'  => 99999999.0,
                'withdrawal_fees'     => 0,
                'updated_at'          => now(),
            ]
        );
    }

    public function down(): void
    {
        // Intentionally left blank — do not delete coin data on rollback.
    }
};
