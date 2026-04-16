<?php

namespace Tests\Feature;

use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Services\BlockchainService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetryFailedObxDeliveriesCommandTest extends TestCase
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
    }

    /** @test */
    public function command_retries_failed_delivery_and_finalizes_order(): void
    {
        $user = User::factory()->create([
            'bsc_wallet' => '0x5555555555555555555555555555555555555555',
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'coin_type' => DEFAULT_COIN_TYPE,
            'is_primary' => 1,
            'balance' => 0,
            'name' => 'OBX Wallet',
        ]);

        $purchaseId = DB::table('buy_coin_histories')->insertGetId([
            'address' => 'retry-order-1',
            'type' => NOWPAYMENTS,
            'user_id' => $user->id,
            'coin' => 10,
            'btc' => 0,
            'doller' => 10,
            'status' => STATUS_PENDING,
            'admin_confirmation' => STATUS_PENDING,
            'confirmations' => 0,
            'coin_type' => 'BTC',
            'requested_amount' => 10,
            'buyer_wallet' => '0x5555555555555555555555555555555555555555',
            'nowpayments_payment_id' => 'retry-pay-1',
            'obx_delivery_status' => 'failed',
            'obx_delivery_error' => 'initial fail',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $txHash = '0xcccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxOnChain')->once()->andReturn(['txHash' => $txHash]);
        $this->app->instance(BlockchainService::class, $blockchain);

        $this->artisan('nowpayments:retry-obx-delivery --limit=10')
            ->expectsOutput('OBX delivery retry complete. processed=1, succeeded=1, failed=0')
            ->assertExitCode(0);

        $purchase = BuyCoinHistory::findOrFail($purchaseId);
        $walletBalance = (float) Wallet::where('user_id', $user->id)
            ->where('coin_type', DEFAULT_COIN_TYPE)
            ->sum('balance');

        $this->assertSame(STATUS_SUCCESS, (int) $purchase->status);
        $this->assertSame('success', (string) $purchase->obx_delivery_status);
        $this->assertSame($txHash, (string) $purchase->obx_delivery_tx_hash);
        $this->assertSame(10.0, $walletBalance);
    }
}
