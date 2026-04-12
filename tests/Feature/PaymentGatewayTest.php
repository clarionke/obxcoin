<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use App\User;
use App\Model\Coin;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Model\MerchantApiKey;
use App\Model\PaymentOrder;
use App\Jobs\DispatchWebhook;

/**
 * Payment Gateway API Tests
 *
 * Covers:
 *  - Public: list coins
 *  - Authenticated (HMAC): create order, get order, invalid auth paths
 *  - Public polling: status endpoint, deposit check
 *  - Merchant self-service (Passport): key CRUD, order list
 *  - Checkout web page rendering
 *  - Webhook job dispatch on completion
 *  - Security: replay attack, IP whitelist, coin restriction, duplicate order ref
 */
class PaymentGatewayTest extends TestCase
{
    use DatabaseTransactions;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private Coin $coin;
    private Wallet $systemWallet;
    private MerchantApiKey $key;
    private string $plainSecret;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Create a merchant user first (needed as wallet owner too)
        $user = User::factory()->create([
            'role'        => USER_ROLE_USER,
            'status'      => STATUS_ACTIVE,
            'is_verified' => 1,
        ]);

        // Ensure an active coin exists
        $this->coin = Coin::firstOrCreate(
            ['type' => 'TestCoin'],
            [
                'name'              => 'Test Coin',
                'type'              => 'TestCoin',
                'status'            => STATUS_ACTIVE,
                'is_deposit'        => 1,
                'is_withdrawal'     => 0,
                'is_buy'            => 0,
                'sign'              => 'TST',
                'minimum_buy_amount'=> 1,
            ]
        );

        // Create a deposit address for the coin (owned by our test user)
        $this->systemWallet = Wallet::create([
            'user_id'    => $user->id,
            'name'       => 'test-wallet',
            'balance'    => 0,
            'coin_type'  => 'TestCoin',
            'key'        => '0xTestDepositAddress123',
            'is_primary' => 1,
            'status'     => 1,
        ]);

        $credentials         = MerchantApiKey::generateCredentials();
        $this->plainSecret   = $credentials['plain_secret'];

        $this->key = MerchantApiKey::create([
            'user_id'         => $user->id,
            'name'            => 'Test Key',
            'api_key'         => $credentials['api_key'],
            'api_secret_hash' => $credentials['api_secret_hash'],
            'is_active'       => true,
            'allowed_coins'   => null,
            'allowed_ips'     => null,
        ]);
    }

    /**
     * Build HMAC-signed request headers for the given body string.
     */
    private function signedHeaders(string $body, ?string $apiKey = null, ?string $secret = null): array
    {
        $key    = $apiKey  ?? $this->key->api_key;
        $sec    = $secret  ?? $this->plainSecret;
        $ts     = (string) time();

        // Mirror the middleware expectation:
        // signature = HMAC-SHA256(api_key + "." + ts + "." + sha256(body), api_secret_hash)
        // But from the client side the "secret" IS the plain secret —
        // the test must derive the hash exactly as the middleware does.
        $secHash    = hash('sha256', $sec);
        $bodyHash   = hash('sha256', $body);
        $sigString  = $key . '.' . $ts . '.' . $bodyHash;
        $signature  = hash_hmac('sha256', $sigString, $secHash);

        return [
            'X-Api-Key'        => $key,
            'X-Api-Timestamp'  => $ts,
            'X-Api-Signature'  => $signature,
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Public: GET /api/payment/coins
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function list_coins_returns_active_deposit_coins(): void
    {
        $response = $this->getJson('/api/payment/coins');

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'data' => [['id', 'name', 'type', 'symbol']],
                 ])
                 ->assertJson(['success' => true]);

        // Our TestCoin should appear
        $types = collect($response->json('data'))->pluck('type')->toArray();
        $this->assertContains('TestCoin', $types);
    }

    /** @test */
    public function list_coins_does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/payment/coins');
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. POST /api/payment/orders — create order
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function merchant_can_create_payment_order(): void
    {
        $body = json_encode([
            'coin_type'         => 'TestCoin',
            'amount'            => '50.00',
            'merchant_order_id' => 'SHOP-001',
        ]);

        $response = $this->postJson(
            '/api/payment/orders',
            json_decode($body, true),
            $this->signedHeaders($body)
        );

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'merchant_order_id', 'coin_type',
                         'amount', 'pay_address', 'status', 'checkout_url', 'expires_at',
                     ],
                 ]);

        $this->assertEquals('pending', $response->json('data.status'));
        $this->assertEquals('SHOP-001', $response->json('data.merchant_order_id'));
        $this->assertEquals('50.00000000', $response->json('data.amount'));
    }

    /** @test */
    public function create_order_requires_valid_coin_type(): void
    {
        $body = json_encode(['coin_type' => 'NONEXISTENT', 'amount' => '10']);

        $response = $this->postJson(
            '/api/payment/orders',
            json_decode($body, true),
            $this->signedHeaders($body)
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function create_order_requires_positive_amount(): void
    {
        $body = json_encode(['coin_type' => 'TestCoin', 'amount' => '-5']);

        $response = $this->postJson(
            '/api/payment/orders',
            json_decode($body, true),
            $this->signedHeaders($body)
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function create_order_rejects_duplicate_merchant_order_id(): void
    {
        // Create first order
        PaymentOrder::create([
            'merchant_id'       => $this->key->id,
            'merchant_order_id' => 'DUPE-001',
            'coin_type'         => 'TestCoin',
            'amount'            => 10,
            'pay_address'       => '0xAddr',
            'status'            => PaymentOrder::STATUS_PENDING,
            'expires_at'        => now()->addMinutes(30),
        ]);

        $body = json_encode(['coin_type' => 'TestCoin', 'amount' => '10', 'merchant_order_id' => 'DUPE-001']);

        $response = $this->postJson(
            '/api/payment/orders',
            json_decode($body, true),
            $this->signedHeaders($body)
        );

        $response->assertStatus(409);
    }

    /** @test */
    public function key_with_allowed_coins_restriction_rejects_other_coins(): void
    {
        $this->key->update(['allowed_coins' => ['OBXCoin']]);

        $body = json_encode(['coin_type' => 'TestCoin', 'amount' => '10']);

        $response = $this->postJson(
            '/api/payment/orders',
            json_decode($body, true),
            $this->signedHeaders($body)
        );

        $response->assertStatus(403);

        $this->key->update(['allowed_coins' => null]); // restore
    }

    /** @test */
    public function create_order_respects_custom_expiry_minutes(): void
    {
        $body = json_encode(['coin_type' => 'TestCoin', 'amount' => '10', 'expiry_minutes' => 60]);

        $response = $this->postJson(
            '/api/payment/orders',
            json_decode($body, true),
            $this->signedHeaders($body)
        );

        $response->assertStatus(201);

        $expiresAt = \Carbon\Carbon::parse($response->json('data.expires_at'));
        $this->assertEqualsWithDelta(60 * 60, $expiresAt->diffInSeconds(now(), false) * -1, 5);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. GET /api/payment/orders/{uuid} — retrieve order
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function merchant_can_get_own_order(): void
    {
        $order = $this->createOrder();
        $body  = '[]'; // getJson() sends json_encode([]) = '[]' as the body

        $response = $this->getJson(
            '/api/payment/orders/' . $order->uuid,
            $this->signedHeaders($body)
        );

        $response->assertOk()
                 ->assertJson(['success' => true, 'data' => ['id' => $order->uuid]]);
    }

    /** @test */
    public function merchant_cannot_get_another_merchants_order(): void
    {
        // Create order for a different key
        $otherKey = $this->createOtherKey();
        $order    = $this->createOrder($otherKey);
        $body     = '[]'; // getJson() sends json_encode([]) = '[]'

        $response = $this->getJson(
            '/api/payment/orders/' . $order->uuid,
            $this->signedHeaders($body) // signed with THIS merchant's key
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function get_order_returns_404_for_nonexistent_uuid(): void
    {
        $body = '[]'; // getJson sends '[]'
        $response = $this->getJson(
            '/api/payment/orders/00000000-0000-0000-0000-000000000000',
            $this->signedHeaders($body)
        );

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. GET /api/payment/orders/{uuid}/status — public polling
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function poll_status_returns_order_status_without_auth(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson('/api/payment/orders/' . $order->uuid . '/status');

        $response->assertOk()
                 ->assertJsonStructure([
                     'data' => ['status', 'amount', 'amount_received', 'expires_at', 'coin_type'],
                 ])
                 ->assertJson(['data' => ['status' => 'pending']]);
    }

    /** @test */
    public function poll_status_auto_expires_overdue_order(): void
    {
        $order = $this->createOrder(expiresAt: now()->subMinutes(1));

        $this->getJson('/api/payment/orders/' . $order->uuid . '/status');

        $order->refresh();
        $this->assertEquals(PaymentOrder::STATUS_EXPIRED, $order->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. POST /api/payment/orders/{uuid}/check — deposit scan
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function check_deposit_returns_no_deposit_when_none_found(): void
    {
        $order = $this->createOrder();

        $response = $this->postJson('/api/payment/orders/' . $order->uuid . '/check');

        $response->assertOk()
                 ->assertJson(['data' => ['status' => 'pending', 'amount_received' => '0']]);
    }

    /** @test */
    public function check_deposit_does_not_reprocess_completed_order(): void
    {
        $order = $this->createOrder(status: PaymentOrder::STATUS_COMPLETED);

        $response = $this->postJson('/api/payment/orders/' . $order->uuid . '/check');

        $response->assertOk()
                 ->assertJson(['data' => ['status' => 'completed']]);

        Queue::assertNothingPushed();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Security: HMAC middleware
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function request_without_auth_headers_is_rejected(): void
    {
        $response = $this->postJson('/api/payment/orders', [
            'coin_type' => 'TestCoin',
            'amount'    => '10',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function request_with_wrong_signature_is_rejected(): void
    {
        $body = json_encode(['coin_type' => 'TestCoin', 'amount' => '10']);
        $headers = $this->signedHeaders($body);
        $headers['X-Api-Signature'] = 'deadbeefdeadbeef'; // tampered

        $response = $this->postJson('/api/payment/orders', json_decode($body, true), $headers);

        $response->assertStatus(401);
    }

    /** @test */
    public function request_with_expired_timestamp_is_rejected(): void
    {
        $body      = json_encode(['coin_type' => 'TestCoin', 'amount' => '10']);
        $staleTs   = (string) (time() - 400); // 6.5 minutes ago — outside 5 min window
        $bodyHash  = hash('sha256', $body);
        $secHash   = hash('sha256', $this->plainSecret);
        $sig       = hash_hmac('sha256', $this->key->api_key . '.' . $staleTs . '.' . $bodyHash, $secHash);

        $response = $this->postJson('/api/payment/orders', json_decode($body, true), [
            'X-Api-Key'        => $this->key->api_key,
            'X-Api-Timestamp'  => $staleTs,
            'X-Api-Signature'  => $sig,
            'Accept'           => 'application/json',
        ]);

        $response->assertStatus(401)
                 ->assertJsonFragment(['message' => 'Request timestamp is outside the acceptable window.']);
    }

    /** @test */
    public function request_from_non_whitelisted_ip_is_rejected(): void
    {
        $this->key->update(['allowed_ips' => ['1.2.3.4']]); // Not 127.0.0.1

        $body     = json_encode(['coin_type' => 'TestCoin', 'amount' => '10']);
        $response = $this->postJson('/api/payment/orders', json_decode($body, true), $this->signedHeaders($body));

        $response->assertStatus(401)
                 ->assertJsonFragment(['message' => 'Request IP is not whitelisted.']);

        $this->key->update(['allowed_ips' => null]);
    }

    /** @test */
    public function inactive_api_key_is_rejected(): void
    {
        $this->key->update(['is_active' => false]);

        $body     = json_encode(['coin_type' => 'TestCoin', 'amount' => '10']);
        $response = $this->postJson('/api/payment/orders', json_decode($body, true), $this->signedHeaders($body));

        $response->assertStatus(401);

        $this->key->update(['is_active' => true]);
    }

    /** @test */
    public function future_timestamp_within_window_is_accepted(): void
    {
        // Clocks can drift slightly — future timestamps within 5 min should pass
        $body   = json_encode(['coin_type' => 'TestCoin', 'amount' => '10']);
        $ts     = (string) (time() + 60); // 60 seconds in the future
        $secHash   = hash('sha256', $this->plainSecret);
        $bodyHash  = hash('sha256', $body);
        $sig       = hash_hmac('sha256', $this->key->api_key . '.' . $ts . '.' . $bodyHash, $secHash);

        $response = $this->postJson('/api/payment/orders', json_decode($body, true), [
            'X-Api-Key'       => $this->key->api_key,
            'X-Api-Timestamp' => $ts,
            'X-Api-Signature' => $sig,
            'Accept'          => 'application/json',
        ]);

        $response->assertStatus(201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Merchant self-service (Passport): key management
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function authenticated_user_can_list_own_api_keys(): void
    {
        Passport::actingAs($this->key->user, [], 'api');

        $response = $this->getJson('/api/merchant/keys');

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'data' => [['id', 'name', 'api_key', 'is_active']],
                 ]);

        $apiKeys = collect($response->json('data'))->pluck('api_key')->toArray();
        $this->assertContains($this->key->api_key, $apiKeys);
    }

    /** @test */
    public function authenticated_user_can_create_api_key(): void
    {
        Passport::actingAs($this->key->user, [], 'api');

        $response = $this->postJson('/api/merchant/keys', [
            'name'        => 'My Shop Key',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'data' => ['api_key', 'api_secret'],
                 ]);

        // Plain secret returned only once
        $this->assertNotEmpty($response->json('data.api_secret'));
    }

    /** @test */
    public function user_cannot_see_another_users_api_keys(): void
    {
        $other = User::factory()->create(['role' => USER_ROLE_USER, 'status' => 1, 'is_verified' => 1]);
        Passport::actingAs($other, [], 'api');

        $response = $this->getJson('/api/merchant/keys');

        $response->assertOk();
        $apiKeys = collect($response->json('data'))->pluck('api_key')->toArray();
        $this->assertNotContains($this->key->api_key, $apiKeys);
    }

    /** @test */
    public function authenticated_user_can_revoke_own_key(): void
    {
        Passport::actingAs($this->key->user, [], 'api');

        $response = $this->deleteJson('/api/merchant/keys/' . $this->key->id);

        $response->assertOk()->assertJson(['success' => true]);

        $this->key->refresh();
        $this->assertFalse($this->key->is_active);

        $this->key->update(['is_active' => true]); // restore for other tests
    }

    /** @test */
    public function user_cannot_revoke_another_users_key(): void
    {
        $other = User::factory()->create(['role' => USER_ROLE_USER, 'status' => 1, 'is_verified' => 1]);
        Passport::actingAs($other, [], 'api');

        $response = $this->deleteJson('/api/merchant/keys/' . $this->key->id);

        $response->assertStatus(404);
    }

    /** @test */
    public function max_10_active_keys_enforced(): void
    {
        $user = $this->key->user;

        // Already has 1 key from setUp; create 9 more = 10 total
        for ($i = 0; $i < 9; $i++) {
            $c = MerchantApiKey::generateCredentials();
            MerchantApiKey::create([
                'user_id'         => $user->id,
                'name'            => "Key $i",
                'api_key'         => $c['api_key'],
                'api_secret_hash' => $c['api_secret_hash'],
                'is_active'       => true,
            ]);
        }

        Passport::actingAs($user, [], 'api');
        $response = $this->postJson('/api/merchant/keys', ['name' => 'One Too Many']);

        $response->assertStatus(429);
    }

    /** @test */
    public function authenticated_user_can_list_own_orders(): void
    {
        $order = $this->createOrder();
        Passport::actingAs($this->key->user, [], 'api');

        $response = $this->getJson('/api/merchant/orders');

        $response->assertOk()
                 ->assertJsonStructure(['data' => ['orders', 'total', 'per_page', 'page']]);

        $uuids = collect($response->json('data.orders'))->pluck('id')->toArray();
        $this->assertContains($order->uuid, $uuids);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Web checkout page
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function checkout_page_renders_for_pending_order(): void
    {
        $order = $this->createOrder();

        $response = $this->get('/pay/' . $order->uuid);

        $response->assertOk()
                 ->assertSee($order->pay_address)
                 ->assertSee($order->coin_type);
    }

    /** @test */
    public function checkout_page_returns_404_for_invalid_uuid(): void
    {
        $response = $this->get('/pay/00000000-dead-beef-0000-000000000000');
        $response->assertStatus(404);
    }

    /** @test */
    public function checkout_page_shows_completed_state(): void
    {
        $order = $this->createOrder(status: PaymentOrder::STATUS_COMPLETED);

        $response = $this->get('/pay/' . $order->uuid);

        $response->assertOk()
                 ->assertSee('Payment Received');
    }

    /** @test */
    public function checkout_page_shows_expired_state(): void
    {
        $order = $this->createOrder(status: PaymentOrder::STATUS_EXPIRED);

        $response = $this->get('/pay/' . $order->uuid);

        $response->assertOk()
                 ->assertSee('Invoice Expired');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. MerchantApiKey model helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function generate_credentials_produces_valid_key_format(): void
    {
        $c = MerchantApiKey::generateCredentials();

        $this->assertStringStartsWith('obx_', $c['api_key']);
        $this->assertEquals(44, strlen($c['api_key']));  // "obx_" + 40 hex chars
        $this->assertEquals(64, strlen($c['plain_secret']));
        $this->assertEquals(64, strlen($c['api_secret_hash']));
    }

    /** @test */
    public function verify_secret_returns_true_for_correct_secret(): void
    {
        $this->assertTrue($this->key->verifySecret($this->plainSecret));
    }

    /** @test */
    public function verify_secret_returns_false_for_wrong_secret(): void
    {
        $this->assertFalse($this->key->verifySecret('wrong-secret'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. PaymentOrder model helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function payment_order_uuid_is_auto_generated_on_create(): void
    {
        $order = $this->createOrder();
        $this->assertNotEmpty($order->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $order->uuid
        );
    }

    /** @test */
    public function is_expired_returns_true_when_past_expiry(): void
    {
        $order = $this->createOrder(expiresAt: now()->subMinute());
        $this->assertTrue($order->isExpired());
    }

    /** @test */
    public function is_expired_returns_false_for_completed_order(): void
    {
        $order = $this->createOrder(
            status: PaymentOrder::STATUS_COMPLETED,
            expiresAt: now()->subMinute()
        );
        $this->assertFalse($order->isExpired());
    }

    /** @test */
    public function checkout_url_returns_expected_format(): void
    {
        $order = $this->createOrder();
        $this->assertStringEndsWith('/pay/' . $order->uuid, $order->checkoutUrl());
    }

    /** @test */
    public function to_api_array_contains_required_keys(): void
    {
        $order = $this->createOrder();
        $arr   = $order->toApiArray();

        foreach (['id', 'coin_type', 'amount', 'amount_received', 'pay_address', 'status', 'checkout_url', 'expires_at', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: $key");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Webhook job
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function dispatch_webhook_job_is_queued_after_check_completes_order(): void
    {
        // We need a real deposit transaction record matching the order address
        $order = $this->createOrder();

        \App\Model\DepositeTransaction::create([
            'address'            => $order->pay_address,
            'amount'             => $order->amount,
            'status'             => STATUS_SUCCESS,
            'fees'               => 0,
            'sender_wallet_id'   => $this->systemWallet->id,
            'receiver_wallet_id' => $this->systemWallet->id,
            'type'               => DEPOSIT,
            'address_type'       => ADDRESS_TYPE_EXTERNAL,
            'transaction_id'     => 'tx_' . uniqid(),
            'confirmations'      => 6,
        ]);

        $response = $this->postJson('/api/payment/orders/' . $order->uuid . '/check');
        $response->assertOk();

        $order->refresh();
        $this->assertEquals(PaymentOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->confirmed_at);

        Queue::assertPushed(DispatchWebhook::class, function ($job) use ($order) {
            return $this->getJobOrderId($job) === $order->id;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createOrder(
        ?MerchantApiKey $key       = null,
        string $status             = PaymentOrder::STATUS_PENDING,
        ?\Carbon\Carbon $expiresAt = null
    ): PaymentOrder {
        return PaymentOrder::create([
            'merchant_id'  => ($key ?? $this->key)->id,
            'coin_type'    => 'TestCoin',
            'amount'       => 50,
            'pay_address'  => '0xTestAddress' . uniqid(),
            'status'       => $status,
            'expires_at'   => $expiresAt ?? now()->addMinutes(30),
        ]);
    }

    private function createOtherKey(): MerchantApiKey
    {
        $user = User::factory()->create(['role' => USER_ROLE_USER, 'status' => 1, 'is_verified' => 1]);
        $c    = MerchantApiKey::generateCredentials();

        return MerchantApiKey::create([
            'user_id'         => $user->id,
            'name'            => 'Other Key',
            'api_key'         => $c['api_key'],
            'api_secret_hash' => $c['api_secret_hash'],
            'is_active'       => true,
        ]);
    }

    /**
     * Reach into the DispatchWebhook job (private $orderId) via reflection for assertion.
     */
    private function getJobOrderId(DispatchWebhook $job): int
    {
        $ref = new \ReflectionProperty($job, 'orderId');
        $ref->setAccessible(true);
        return $ref->getValue($job);
    }
}
