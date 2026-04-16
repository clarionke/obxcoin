<?php

namespace Tests\Feature;

use App\Model\AdminSetting;
use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Services\BlockchainService;
use App\Services\NowPaymentsService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NowPaymentsPaymentStatusSyncTest extends TestCase
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
        if (!Schema::hasColumn('buy_coin_histories', 'buyer_wallet')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('buyer_wallet', 42)->nullable();
            });
        }
        if (!Schema::hasColumn('buy_coin_histories', 'tx_hash')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('tx_hash', 100)->nullable();
            });
        }
        if (!Schema::hasColumn('buy_coin_histories', 'obx_delivery_status')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('obx_delivery_status', 24)->default('pending');
            });
        }
        if (!Schema::hasColumn('buy_coin_histories', 'obx_delivery_tx_hash')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('obx_delivery_tx_hash', 66)->nullable();
            });
        }
        if (!Schema::hasColumn('buy_coin_histories', 'obx_delivery_error')) {
            Schema::table('buy_coin_histories', function (Blueprint $table) {
                $table->string('obx_delivery_error', 500)->nullable();
            });
        }

        AdminSetting::updateOrCreate(['slug' => 'nowpayments_enabled'], ['value' => '1']);
    }

    /** @test */
    public function status_endpoint_auto_syncs_finished_payment_and_credits_default_wallet(): void
    {
        $user = User::factory()->create([
            'role' => 2,
            'status' => 1,
            'is_verified' => 1,
            'bsc_wallet' => '0x6666666666666666666666666666666666666666',
        ]);

        $purchaseId = DB::table('buy_coin_histories')->insertGetId([
            'address' => 'np-sync-order',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 15,
            'btc' => 0,
            'doller' => 15,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'requested_amount' => 15,
            'buyer_wallet' => '0x6666666666666666666666666666666666666666',
            'nowpayments_payment_id' => 'np-sync-001',
            'obx_delivery_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $np = \Mockery::mock(NowPaymentsService::class);
        $np->shouldReceive('getPaymentStatus')->once()->with('np-sync-001')->andReturn([
            'payment_id' => 'np-sync-001',
            'payment_status' => 'finished',
            'payin_hash' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);
        $this->app->instance(NowPaymentsService::class, $np);

        $txHash = '0xdddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxOnChain')->once()->andReturn(['txHash' => $txHash]);
        $this->app->instance(BlockchainService::class, $blockchain);

        $response = $this->actingAs($user)->get(route('buyCoinPaymentStatus', ['address' => 'np-sync-001']));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status_code' => STATUS_SUCCESS,
            'obx_delivery_status' => 'success',
            'credited' => true,
            'remote_status' => 'finished',
        ]);

        $purchase = BuyCoinHistory::findOrFail($purchaseId);
        $this->assertSame(STATUS_SUCCESS, (int) $purchase->status);
        $this->assertSame('success', (string) $purchase->obx_delivery_status);
        $this->assertSame($txHash, (string) $purchase->obx_delivery_tx_hash);

        $wallet = Wallet::where('user_id', $user->id)
            ->where('coin_type', DEFAULT_COIN_TYPE)
            ->where('is_primary', 1)
            ->first();

        $this->assertNotNull($wallet);
        $this->assertSame(15.0, (float) $wallet->balance);
    }

    /** @test */
    public function status_endpoint_uses_default_obx_wallet_history_when_bsc_wallet_is_missing(): void
    {
        $user = User::factory()->create([
            'role' => 2,
            'status' => 1,
            'is_verified' => 1,
            'bsc_wallet' => null,
        ]);

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'coin_type' => DEFAULT_COIN_TYPE,
            'is_primary' => 1,
            'balance' => 0,
            'name' => 'OBX Wallet',
        ]);

        WalletAddressHistory::create([
            'wallet_id' => $wallet->id,
            'address' => '0x7777777777777777777777777777777777777777',
            'coin_type' => DEFAULT_COIN_TYPE,
        ]);

        DB::table('buy_coin_histories')->insert([
            'address' => 'np-sync-order-2',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 7,
            'btc' => 0,
            'doller' => 7,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'requested_amount' => 7,
            'buyer_wallet' => null,
            'nowpayments_payment_id' => 'np-sync-002',
            'obx_delivery_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $np = \Mockery::mock(NowPaymentsService::class);
        $np->shouldReceive('getPaymentStatus')->once()->with('np-sync-002')->andReturn([
            'payment_id' => 'np-sync-002',
            'payment_status' => 'finished',
            'payin_hash' => '0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
        ]);
        $this->app->instance(NowPaymentsService::class, $np);

        $txHash = '0xabababababababababababababababababababababababababababababababab';
        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxOnChain')
            ->once()
            ->with('0x7777777777777777777777777777777777777777', '7')
            ->andReturn(['txHash' => $txHash]);
        $this->app->instance(BlockchainService::class, $blockchain);

        $response = $this->actingAs($user)->get(route('buyCoinPaymentStatus', ['address' => 'np-sync-002']));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status_code' => STATUS_SUCCESS,
            'obx_delivery_status' => 'success',
            'credited' => true,
            'remote_status' => 'finished',
        ]);

        $this->assertSame(7.0, (float) $wallet->fresh()->balance);
    }
}
