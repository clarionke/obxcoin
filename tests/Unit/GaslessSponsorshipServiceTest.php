<?php

namespace Tests\Unit;

use App\Model\AdminSetting;
use App\Services\BlockchainService;
use App\Services\GaslessSponsorshipService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GaslessSponsorshipServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('gasless_sponsorships')) {
            Schema::create('gasless_sponsorships', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('wallet_address', 42);
                $table->string('action', 32);
                $table->unsignedInteger('chain_id')->default(56);
                $table->decimal('gas_amount_native', 24, 12)->default(0);
                $table->unsignedInteger('estimated_gas_limit')->nullable();
                $table->string('status', 24)->default('pending');
                $table->string('tx_hash', 66)->nullable();
                $table->string('error_message', 500)->nullable();
                $table->timestamps();
            });
        }

        AdminSetting::updateOrCreate(['slug' => 'gasless_enabled'], ['value' => '1']);
        AdminSetting::updateOrCreate(['slug' => 'gasless_allowed_actions'], ['value' => 'buy,stake,unstake,unlock,transfer,withdraw']);
        AdminSetting::updateOrCreate(['slug' => 'gasless_daily_budget_native'], ['value' => '1']);
        AdminSetting::updateOrCreate(['slug' => 'gasless_default_amount_native'], ['value' => '0.0015']);
        AdminSetting::updateOrCreate(['slug' => 'gasless_max_per_user_daily'], ['value' => '5']);
        AdminSetting::updateOrCreate(['slug' => 'gasless_cooldown_seconds'], ['value' => '0']);
        AdminSetting::updateOrCreate(['slug' => 'gasless_min_native_balance'], ['value' => '0.00035']);
        AdminSetting::updateOrCreate(['slug' => 'bsc_rpc_url'], ['value' => 'https://rpc.test.local']);
        AdminSetting::updateOrCreate(['slug' => 'presale_chain_id'], ['value' => '56']);
    }

    /** @test */
    public function quote_returns_eligible_when_enabled_and_wallet_needs_topup(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x0',
            ], 200),
        ]);

        $user = User::factory()->create();
        $service = app(GaslessSponsorshipService::class);

        $quote = $service->quote($user->id, '0x1111111111111111111111111111111111111111', 'buy');

        $this->assertTrue($quote['eligible']);
        $this->assertSame('buy', $quote['action']);
        $this->assertSame('0x1111111111111111111111111111111111111111', $quote['wallet_address']);
        $this->assertSame('56', (string) $quote['chain_id']);
    }

    /** @test */
    public function sponsor_creates_broadcasted_record_when_transfer_succeeds(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x0',
            ], 200),
        ]);

        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferNativeForGasTopup')
            ->once()
            ->andReturn([
                'txHash' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ]);
        $this->app->instance(BlockchainService::class, $blockchain);

        $user = User::factory()->create();
        $service = app(GaslessSponsorshipService::class);

        $result = $service->sponsor($user->id, '0x2222222222222222222222222222222222222222', 'transfer');

        $this->assertTrue($result['success']);
        $this->assertSame('broadcasted', $result['data']['id'] ? \App\Model\GaslessSponsorship::find($result['data']['id'])->status : null);

        $this->assertDatabaseHas('gasless_sponsorships', [
            'user_id' => $user->id,
            'wallet_address' => '0x2222222222222222222222222222222222222222',
            'action' => 'transfer',
            'status' => 'broadcasted',
            'tx_hash' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);
    }
}
