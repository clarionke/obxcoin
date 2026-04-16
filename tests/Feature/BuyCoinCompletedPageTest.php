<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuyCoinCompletedPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasColumn('buy_coin_histories', 'nowpayments_payment_id')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('nowpayments_payment_id')->nullable();
            });
        }
        if (!Schema::hasColumn('buy_coin_histories', 'requested_amount')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->decimal('requested_amount', 19, 8)->default(0);
            });
        }
        if (!Schema::hasColumn('buy_coin_histories', 'obx_delivery_tx_hash')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('obx_delivery_tx_hash', 66)->nullable();
            });
        }
    }

    /** @test */
    public function confirmed_purchase_renders_completed_page_with_amount(): void
    {
        $user = User::factory()->create([
            'role' => 2,
            'status' => 1,
            'is_verified' => 1,
        ]);

        DB::table('buy_coin_histories')->insert([
            'address' => 'np-done-address',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 42,
            'btc' => 0,
            'doller' => 42,
            'status' => STATUS_SUCCESS,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'requested_amount' => 42,
            'nowpayments_payment_id' => 'np-done-42',
            'obx_delivery_tx_hash' => '0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('buyCoinCompleted', ['address' => 'np-done-42']));

        $response->assertOk();
        $response->assertViewIs('user.buy_coin.payment_completed');
        $response->assertViewHas('creditedAmount', 42.0);
        $response->assertSee('OBX Credited Successfully');
    }
}
