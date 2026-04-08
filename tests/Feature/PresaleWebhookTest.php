<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Model\IcoPhase;
use App\Model\BuyCoinHistory;
use App\User;
use App\Model\Wallet;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\DB;

/**
 * Feature tests for the on-chain presale webhook endpoint.
 */
class PresaleWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private string $webhookSecret = 'test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['blockchain.webhook_secret' => $this->webhookSecret]);
        config(['blockchain.presale_contract' => '0xTestContract']);

        // Mock BlockchainService so verifyPurchaseTransaction() returns the
        // on-chain verified data without making real RPC calls in tests.
        // The mock returns a decoded event matching the tx_hash sent as input.
        $mock = \Mockery::mock(BlockchainService::class)->makePartial();
        $mock->shouldReceive('verifyPurchaseTransaction')
             ->andReturnUsing(function (string $txHash) {
                 // Simulate a successful on-chain lookup: return well-formed data
                 // keyed by the txHash that was requested.
                 return $this->fakeVerifiedReceipts[$txHash] ?? null;
             });
        $this->app->instance(BlockchainService::class, $mock);
    }

    /**
     * Registry of fake "on-chain receipts" per tx_hash.
     * Each test registers its expected transactions here.
     * @var array<string,array>
     */
    private array $fakeVerifiedReceipts = [];

    private function makeSignature(string $body): string
    {
        return hash_hmac('sha256', $body, $this->webhookSecret);
    }

    /**
     * Register a fake on-chain receipt so the mocked verifyPurchaseTransaction
     * returns it, simulating a confirmed on-chain event.
     */
    private function registerReceipt(array $event): void
    {
        $this->fakeVerifiedReceipts[$event['tx_hash']] = [
            'tx_hash'              => $event['tx_hash'],
            'buyer'                => $event['buyer'],
            'contract_phase_index' => $event['contract_phase_index'],
            'db_phase_id'          => $event['db_phase_id'],
            'usdt_amount'          => $event['usdt_amount'],
            'obx_allocated'        => $event['obx_allocated'],
            'bonus_obx'            => $event['bonus_obx'],
            'block_number'         => $event['block_number'],
            'timestamp'            => $event['timestamp'],
        ];
    }

    /** @test */
    public function webhook_rejects_invalid_signature()
    {
        $payload = json_encode(['buyer' => '0xabc', 'db_phase_id' => 1]);

        $response = $this->postJson('/api/presale/webhook', json_decode($payload, true), [
            'X-Presale-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function webhook_accepts_valid_signature_and_processes_event()
    {
        // Create a user with a BSC wallet
        $user = User::factory()->create([
            'bsc_wallet'  => '0xabcdef1234567890abcdef1234567890abcdef12',
            'status'      => 1,
            'role'        => 2,
            'is_verified' => 1,
        ]);

        // Create an OBX wallet for the user
        $wallet = Wallet::create([
            'user_id'    => $user->id,
            'coin_type'  => DEFAULT_COIN_TYPE,
            'is_primary' => 1,
            'balance'    => 0,
            'name'       => 'OBX Wallet',
        ]);

        // Create a phase in DB
        $phase = IcoPhase::create([
            'phase_name' => 'Seed Round',
            'start_date' => now()->subDay(),
            'end_date'   => now()->addDay(),
            'rate'       => 0.01,
            'amount'     => 1000000,
            'bonus'      => 5,
            'fees'       => 0,
            'status'     => 1,
        ]);

        $event = [
            'tx_hash'              => '0xdeadbeefcafe',
            'buyer'                => '0xabcdef1234567890abcdef1234567890abcdef12',
            'contract_phase_index' => 0,
            'db_phase_id'          => $phase->id,
            'usdt_amount'          => '100.000000',
            'obx_allocated'        => '10000.000000000000000000',
            'bonus_obx'            => '500.000000000000000000',
            'block_number'         => 38000001,
            'timestamp'            => now()->timestamp,
        ];

        $this->registerReceipt($event);

        $body      = json_encode($event);
        $signature = $this->makeSignature($body);

        $response = $this->postJson(
            '/api/presale/webhook',
            $event,
            ['X-Presale-Signature' => $signature]
        );

        $response->assertStatus(200)
                 ->assertJson(['processed' => 1]);

        // Purchase history created
        $this->assertDatabaseHas('buy_coin_histories', [
            'tx_hash'  => '0xdeadbeefcafe',
            'user_id'  => $user->id,
            'phase_id' => $phase->id,
            'status'   => STATUS_SUCCESS,
        ]);

        // Wallet balance credited (DB stores up to 8 decimal places)
        $wallet->refresh();
        $this->assertEquals('10000.00000000', $wallet->balance);
    }

    /** @test */
    public function webhook_is_idempotent_for_duplicate_tx_hash()
    {
        $user = User::factory()->create([
            'bsc_wallet'  => '0xabcdef1234567890abcdef1234567890abcdef12',
            'status'      => 1,
            'role'        => 2,
            'is_verified' => 1,
        ]);

        Wallet::create([
            'user_id'    => $user->id,
            'coin_type'  => DEFAULT_COIN_TYPE,
            'is_primary' => 1,
            'balance'    => 0,
            'name'       => 'OBX Wallet',
        ]);

        $phase = IcoPhase::create([
            'phase_name' => 'Round 2',
            'start_date' => now()->subDay(),
            'end_date'   => now()->addDay(),
            'rate'       => 0.01,
            'amount'     => 1000000,
            'bonus'      => 0,
            'fees'       => 0,
            'status'     => 1,
        ]);

        $event = [
            'tx_hash'              => '0xduplicatetx',
            'buyer'                => '0xabcdef1234567890abcdef1234567890abcdef12',
            'contract_phase_index' => 0,
            'db_phase_id'          => $phase->id,
            'usdt_amount'          => '50.000000',
            'obx_allocated'        => '5000.000000000000000000',
            'bonus_obx'            => '0',
            'block_number'         => 38000002,
            'timestamp'            => now()->timestamp,
        ];

        $this->registerReceipt($event);

        $body      = json_encode($event);
        $signature = $this->makeSignature($body);
        $headers   = ['X-Presale-Signature' => $signature];

        // Send same event twice
        $this->postJson('/api/presale/webhook', $event, $headers)->assertStatus(200)->assertJson(['processed' => 1]);
        $this->postJson('/api/presale/webhook', $event, $headers)->assertStatus(200)->assertJson(['processed' => 0]);

        // Only one record in DB
        $this->assertDatabaseCount('buy_coin_histories', 1);
    }

    /** @test */
    public function webhook_handles_unknown_buyer_wallet_gracefully()
    {
        $phase = IcoPhase::create([
            'phase_name' => 'Public Round',
            'start_date' => now()->subDay(),
            'end_date'   => now()->addDay(),
            'rate'       => 0.05,
            'amount'     => 5000000,
            'bonus'      => 0,
            'fees'       => 0,
            'status'     => 1,
        ]);

        $event = [
            'tx_hash'              => '0xunknownwallettx',
            'buyer'                => '0x0000000000000000000000000000000000000001',
            'contract_phase_index' => 0,
            'db_phase_id'          => $phase->id,
            'usdt_amount'          => '200.000000',
            'obx_allocated'        => '4000.000000000000000000',
            'bonus_obx'            => '0',
            'block_number'         => 38000003,
            'timestamp'            => now()->timestamp,
        ];

        $this->registerReceipt($event);

        $body      = json_encode($event);
        $signature = $this->makeSignature($body);

        $response = $this->postJson('/api/presale/webhook', $event, [
            'X-Presale-Signature' => $signature,
        ]);

        // Should still process (record saved with user_id=0), not crash
        $response->assertStatus(200)->assertJson(['processed' => 1]);

        $this->assertDatabaseHas('buy_coin_histories', [
            'tx_hash'  => '0xunknownwallettx',
            'user_id'  => 0,
        ]);
    }

    /** @test */
    public function save_bsc_wallet_validates_address_format()
    {
        $user = User::factory()->create(['status' => 1, 'role' => 2, 'is_verified' => 1]);

        // Test invalid address is rejected
        $this->withoutMiddleware()
             ->actingAs($user)
             ->post(route('saveBscWallet'), ['bsc_wallet' => 'not-a-valid-address'])
             ->assertSessionHasErrors('bsc_wallet');
    }

    /** @test */
    public function save_bsc_wallet_accepts_valid_address()
    {
        $user = User::factory()->create(['status' => 1, 'role' => 2, 'is_verified' => 1]);

        $this->withoutMiddleware()
             ->actingAs($user)
             ->post(route('saveBscWallet'), ['bsc_wallet' => '0xAbCdEf1234567890AbCdEf1234567890AbCdEf12'])
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'bsc_wallet' => '0xabcdef1234567890abcdef1234567890abcdef12', // stored lowercase
        ]);
    }
}
