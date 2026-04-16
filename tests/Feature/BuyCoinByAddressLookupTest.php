<?php

namespace Tests\Feature;

use App\Model\BuyCoinHistory;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuyCoinByAddressLookupTest extends TestCase
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
    }

    /** @test */
    public function it_resolves_numeric_nowpayments_payment_id_before_local_numeric_id(): void
    {
        $user = User::factory()->create([
            'role' => 2,
            'status' => 1,
            'is_verified' => 1,
        ]);

        DB::table('buy_coin_histories')->insert([
            'address' => 'legacy-address',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 10,
            'btc' => 0,
            'doller' => 10,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetId = DB::table('buy_coin_histories')->insertGetId([
            'address' => 'np-address',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 25,
            'btc' => 0,
            'doller' => 25,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'nowpayments_payment_id' => '123456',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('buyCoinByAddress', ['address' => '123456']));

        $response->assertOk();
        $response->assertViewIs('user.buy_coin.payment_success');
        $response->assertViewHas('coinAddress', function (BuyCoinHistory $history) use ($targetId) {
            return (int) $history->id === (int) $targetId
                && (string) $history->nowpayments_payment_id === '123456';
        });
    }
}
