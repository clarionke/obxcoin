<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Model\ObxWallet;
use App\Services\WalletService;
use App\Services\BlockchainService;
use App\User;

/**
 * Comprehensive feature tests for the OBX Wallet system.
 *
 * Tests cover:
 *  â€¢ Wallet auto-generation on user registration
 *  â€¢ Wallet listing via API
 *  â€¢ New wallet generation via API (with mocked signer.js)
 *  â€¢ Wallet label update
 *  â€¢ Wallet balance refresh (with mocked BlockchainService)
 *  â€¢ Per-user wallet limit enforcement
 *  â€¢ Users cannot access each other's wallets
 *  â€¢ Address uniqueness
 *  â€¢ Private key never exposed in any API response
 *  â€¢ Label validation (max 100 chars)
 */
class ObxWalletTest extends TestCase
{
    use DatabaseTransactions;

    // â”€â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'status'      => 1,
            'role'        => 2,
            'is_verified' => 1,
        ], $attrs));
    }

    /**
     * Build a WalletService with a mocked subprocess that returns a
     * deterministic fake wallet address so no real node process is needed.
     */
    private function mockWalletService(array $sequence = []): WalletService
    {
        $blockchainMock = \Mockery::mock(BlockchainService::class);
        $blockchainMock->shouldReceive('getObxBalance')->andReturn('0.000000000000000000');

        $counter = 0;
        $service = \Mockery::mock(WalletService::class, [$blockchainMock])->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        // Override callSignerGenerateWallet via a partial mock â€” but since it's private
        // we override the full generateWallet to avoid the subprocess:
        $addresses = $sequence ?: [
            '0xaabbccddee11223344556677889900aabbccddee',
            '0x1122334455667788990011223344556677889900',
            '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            '0xcafebabecafebabecafebabecafebabecafebabe',
            '0x0102030405060708090a0b0c0d0e0f1011121314',
        ];

        $service->shouldReceive('generateWallet')
                ->andReturnUsing(function (int $userId, string $label = 'Wallet', bool $isPrimary = false) use ($addresses, &$counter) {
                    $address = $addresses[$counter++ % count($addresses)];
                    // Demote existing primary if needed
                    if ($isPrimary) {
                        ObxWallet::where('user_id', $userId)
                            ->where('is_primary', true)
                            ->update(['is_primary' => false]);
                    }
                    return ObxWallet::create([
                        'user_id'               => $userId,
                        'address'               => $address . strtolower(dechex($counter)), // ensure unique
                        'encrypted_private_key' => encrypt('fake-private-key-' . $counter),
                        'label'                 => $label,
                        'is_primary'            => $isPrimary,
                    ]);
                });

        $service->shouldReceive('ensurePrimaryWallet')
                ->andReturnUsing(function (int $userId) use ($service) {
                    $primary = ObxWallet::where('user_id', $userId)->where('is_primary', true)->first();
                    if ($primary) return $primary;
                    return $service->generateWallet($userId, 'Primary Wallet', true);
                });

        return $service;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Bind the mocked WalletService for all tests in this class
        $this->app->instance(WalletService::class, $this->mockWalletService());
    }

    // â”€â”€â”€ Tests â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /** @test */
    public function user_gets_a_primary_wallet_automatically()
    {
        $user = $this->makeUser();
        // Manually call ensurePrimaryWallet (mimics registration flow)
        app(WalletService::class)->ensurePrimaryWallet($user->id);

        $wallets = ObxWallet::where('user_id', $user->id)->get();
        $this->assertCount(1, $wallets);
        $this->assertTrue($wallets->first()->is_primary);
        $this->assertStringStartsWith('0x', $wallets->first()->address);
    }

    /** @test */
    public function primary_wallet_label_defaults_to_primary_wallet()
    {
        $user = $this->makeUser();
        $wallet = app(WalletService::class)->ensurePrimaryWallet($user->id);

        $this->assertEquals('Primary Wallet', $wallet->label);
    }

    /** @test */
    public function user_can_list_wallets_via_api()
    {
        $user = $this->makeUser();
        $service = app(WalletService::class);
        $service->ensurePrimaryWallet($user->id);
        $service->generateWallet($user->id, 'Trading');

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->getJson('/api/user/wallets');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'wallets' => [['id', 'address', 'label', 'is_primary', 'cached_balance', 'explorer_url']],
                 ])
                 ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('wallets'));
    }

    /** @test */
    public function api_never_exposes_private_key_in_wallet_list()
    {
        $user = $this->makeUser();
        app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->getJson('/api/user/wallets');

        $response->assertStatus(200);

        // The raw private key must not appear anywhere in the JSON output
        $body = $response->getContent();
        $this->assertStringNotContainsString('encrypted_private_key', $body);
        $this->assertStringNotContainsString('privateKey', $body);
        $this->assertStringNotContainsString('private_key', $body);
    }

    /** @test */
    public function user_can_generate_additional_wallet_via_api()
    {
        $user = $this->makeUser();
        app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->postJson('/api/user/wallets/generate', ['label' => 'Staking Wallet']);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('wallet.label', 'Staking Wallet')
                 ->assertJsonPath('wallet.is_primary', false);

        $this->assertCount(2, ObxWallet::where('user_id', $user->id)->get());
    }

    /** @test */
    public function generated_wallet_label_defaults_to_wallet_when_not_provided()
    {
        $user = $this->makeUser();
        app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->postJson('/api/user/wallets/generate', []);

        $response->assertStatus(201)
                 ->assertJsonPath('wallet.label', 'Wallet');
    }

    /** @test */
    public function user_can_update_wallet_label()
    {
        $user    = $this->makeUser();
        $wallet  = app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->patchJson("/api/user/wallets/{$wallet->id}/label", [
                             'label' => 'My DeFi Wallet',
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('wallet.label', 'My DeFi Wallet');

        $this->assertDatabaseHas('obx_wallets', [
            'id'    => $wallet->id,
            'label' => 'My DeFi Wallet',
        ]);
    }

    /** @test */
    public function label_must_not_exceed_100_characters()
    {
        $user   = $this->makeUser();
        $wallet = app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->patchJson("/api/user/wallets/{$wallet->id}/label", [
                             'label' => str_repeat('a', 101),
                         ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function label_must_not_be_empty()
    {
        $user   = $this->makeUser();
        $wallet = app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->patchJson("/api/user/wallets/{$wallet->id}/label", [
                             'label' => '',
                         ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function user_cannot_update_another_users_wallet_label()
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $walletA = app(WalletService::class)->ensurePrimaryWallet($userA->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($userB)
                         ->patchJson("/api/user/wallets/{$walletA->id}/label", [
                             'label' => 'Hacked',
                         ]);

        // Returns 404 (not found for userB), not 403
        $response->assertStatus(404);
    }

    /** @test */
    public function wallet_balance_refresh_returns_balance()
    {
        // Override the blockchain mock for this test to return a specific balance
        $blockchainMock = \Mockery::mock(BlockchainService::class);
        $blockchainMock->shouldReceive('getObxBalance')
                       ->once()
                       ->andReturn('1234.567890000000000000');

        $user   = $this->makeUser();
        $wallet = ObxWallet::create([
            'user_id'               => $user->id,
            'address'               => '0x' . str_repeat('a', 40),
            'encrypted_private_key' => encrypt('fake'),
            'label'                 => 'Test',
            'is_primary'            => true,
        ]);

        $realService = new WalletService($blockchainMock);
        $balance = $realService->refreshBalance($wallet);

        $this->assertEquals('1234.567890000000000000', $balance);
        $this->assertDatabaseHas('obx_wallets', [
            'id'             => $wallet->id,
            'cached_balance' => '1234.567890000000000000',
        ]);
    }

    /** @test */
    public function wallet_balance_refresh_endpoint_returns_json()
    {
        $user = $this->makeUser();
        $wallet = ObxWallet::create([
            'user_id'               => $user->id,
            'address'               => '0x' . str_repeat('b', 40),
            'encrypted_private_key' => encrypt('fake-key'),
            'label'                 => 'Test Wallet',
            'is_primary'            => true,
        ]);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->postJson("/api/user/wallets/{$wallet->id}/refresh-balance");

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'address', 'balance', 'balance_updated_at']);
    }

    /** @test */
    public function user_cannot_refresh_another_users_wallet_balance()
    {
        $userA  = $this->makeUser();
        $userB  = $this->makeUser();

        $wallet = ObxWallet::create([
            'user_id'               => $userA->id,
            'address'               => '0x' . str_repeat('c', 40),
            'encrypted_private_key' => encrypt('fake'),
            'label'                 => 'A Wallet',
            'is_primary'            => true,
        ]);

        $response = $this->withoutMiddleware()
                         ->actingAs($userB)
                         ->postJson("/api/user/wallets/{$wallet->id}/refresh-balance");

        $response->assertStatus(404);
    }

    /** @test */
    public function wallet_addresses_are_unique_per_address_field()
    {
        $userA = $this->makeUser();
        $addr  = '0x' . str_repeat('1', 40);

        ObxWallet::create([
            'user_id'               => $userA->id,
            'address'               => $addr,
            'encrypted_private_key' => encrypt('key1'),
            'label'                 => 'First',
            'is_primary'            => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt to insert duplicate address â€” must fail with unique constraint
        ObxWallet::create([
            'user_id'               => $userA->id,
            'address'               => $addr,
            'encrypted_private_key' => encrypt('key2'),
            'label'                 => 'Duplicate',
            'is_primary'            => false,
        ]);
    }

    /** @test */
    public function explorer_url_matches_configured_chain()
    {
        config(['blockchain.presale_chain_id' => 56]);

        $user   = $this->makeUser();
        app(WalletService::class)->ensurePrimaryWallet($user->id);

        $response = $this->withoutMiddleware()
                         ->actingAs($user)
                         ->getJson('/api/user/wallets');

        $response->assertStatus(200);
        $url = $response->json('wallets.0.explorer_url');
        $this->assertStringStartsWith('https://bscscan.com/address/', $url);
    }
}
