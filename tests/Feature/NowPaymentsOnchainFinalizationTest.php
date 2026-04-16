<?php

namespace Tests\Feature;

use App\Model\AdminSetting;
use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Services\BlockchainService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NowPaymentsOnchainFinalizationTest extends TestCase
{
    use DatabaseTransactions;

    private string $ipnSecret = 'np-test-secret';

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

        AdminSetting::updateOrCreate(['slug' => 'nowpayments_ipn_secret'], ['value' => $this->ipnSecret]);
        AdminSetting::updateOrCreate(['slug' => 'nowpayments_sandbox_mode'], ['value' => '1']);
    }

    /** @test */
    public function finished_ipn_marks_delivery_failed_and_does_not_credit_when_onchain_transfer_fails(): void
    {
        $user = User::factory()->create([
            'bsc_wallet' => '0x3333333333333333333333333333333333333333',
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'coin_type' => DEFAULT_COIN_TYPE,
            'is_primary' => 1,
            'balance' => 0,
            'name' => 'OBX Wallet',
        ]);

        $purchaseId = DB::table('buy_coin_histories')->insertGetId([
            'address' => 'np-order-1',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 100,
            'btc' => 0,
            'doller' => 100,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'requested_amount' => 100,
            'buyer_wallet' => '0x3333333333333333333333333333333333333333',
            'nowpayments_payment_id' => 'pay-test-1',
            'obx_delivery_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxOnChain')->once()->andReturn(null);
        $blockchain->shouldReceive('getLastSignerError')->once()->andReturn('rpc failure');
        $this->app->instance(BlockchainService::class, $blockchain);

        $payload = [
            'payment_id' => 'pay-test-1',
            'payment_status' => 'finished',
            'order_id' => (string) $purchaseId,
        ];

        $response = $this->postIpn($payload);
        $response->assertStatus(200);

        $purchase = BuyCoinHistory::findOrFail($purchaseId);
        $walletBalance = (float) Wallet::where('user_id', $user->id)
            ->where('coin_type', DEFAULT_COIN_TYPE)
            ->sum('balance');

        $this->assertSame(STATUS_PENDING, (int) $purchase->status);
        $this->assertSame('failed', (string) $purchase->obx_delivery_status);
        $this->assertSame(0.0, $walletBalance);
    }

    /** @test */
    public function finished_ipn_finalizes_success_after_onchain_delivery_and_credits_wallet(): void
    {
        $user = User::factory()->create([
            'bsc_wallet' => '0x4444444444444444444444444444444444444444',
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'coin_type' => DEFAULT_COIN_TYPE,
            'is_primary' => 1,
            'balance' => 0,
            'name' => 'OBX Wallet',
        ]);

        $purchaseId = DB::table('buy_coin_histories')->insertGetId([
            'address' => 'np-order-2',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 25,
            'btc' => 0,
            'doller' => 25,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'requested_amount' => 25,
            'buyer_wallet' => '0x4444444444444444444444444444444444444444',
            'nowpayments_payment_id' => 'pay-test-2',
            'obx_delivery_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $txHash = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxOnChain')->once()->andReturn(['txHash' => $txHash]);
        $this->app->instance(BlockchainService::class, $blockchain);

        $payload = [
            'payment_id' => 'pay-test-2',
            'payment_status' => 'finished',
            'order_id' => (string) $purchaseId,
        ];

        $response = $this->postIpn($payload);
        $response->assertStatus(200);

        $purchase = BuyCoinHistory::findOrFail($purchaseId);
        $walletBalance = (float) Wallet::where('user_id', $user->id)
            ->where('coin_type', DEFAULT_COIN_TYPE)
            ->sum('balance');

        $this->assertSame(STATUS_SUCCESS, (int) $purchase->status);
        $this->assertSame('success', (string) $purchase->obx_delivery_status);
        $this->assertSame($txHash, (string) $purchase->obx_delivery_tx_hash);
        $this->assertSame(25.0, $walletBalance);
    }

    private function postIpn(array $payload)
    {
        $sorted = $payload;
        ksort($sorted);
        $sortedJson = json_encode($sorted);
        $signature = hash_hmac('sha512', $sortedJson, $this->ipnSecret);

        return $this->call(
            'POST',
            '/api/nowpayments/ipn',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-NOWPAYMENTS-SIG' => $signature,
            ],
            json_encode($payload)
        );
    }
}
