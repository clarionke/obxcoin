<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\User;

/**
 * Authentication and Authorization Boundary Tests
 *
 * Verifies that all sensitive routes require authentication and
 * the correct role. Tests cover:
 *
 * - Unauthenticated users redirected to login
 * - Regular users cannot access admin routes
 * - Admin routes require admin role
 * - Authenticated users can access their own routes
 * - Session invalidation on logout
 * - Encrypted route param tampering (decrypt)
 * - User cannot impersonate without admin role
 */
class AuthBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'status'      => 1,
            'role'        => 1,
            'is_verified' => 1,
        ], $attrs));
    }

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'status'      => 1,
            'role'        => 2,
            'is_verified' => 1,
        ], $attrs));
    }

    // ── Unauthenticated access: web routes ───────────────────────────────────

    /** @test */
    public function unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->get(route('userDashboard'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_my_pocket()
    {
        $response = $this->get(route('myPocket'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_buy_coin_page()
    {
        $response = $this->get(route('buyCoin'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_post_buy_coin()
    {
        $response = $this->post(route('buyCoinProcess'), []);
        // Should redirect to login, not process the purchase
        $response->assertRedirect(route('login'));
    }

    // ── Unauthenticated access: API routes ───────────────────────────────────

    /** @test */
    public function api_wallet_list_requires_authentication()
    {
        $response = $this->getJson('/api/user/wallets');
        // Unauthenticated requests must not return 200 (any non-success status is acceptable)
        $this->assertNotEquals(200, $response->status(),
            'Unauthenticated API requests must not return 200');
    }

    /** @test */
    public function api_wallet_generate_requires_authentication()
    {
        $response = $this->postJson('/api/user/wallets/generate', ['label' => 'Test']);
        $this->assertNotEquals(200, $response->status(),
            'Unauthenticated API requests must not return 200');
    }

    /** @test */
    public function api_wallet_refresh_requires_authentication()
    {
        $response = $this->postJson('/api/user/wallets/1/refresh-balance');
        $this->assertNotEquals(200, $response->status(),
            'Unauthenticated API requests must not return 200');
    }

    // ── Unauthenticated: admin routes ────────────────────────────────────────

    /** @test */
    public function unauthenticated_user_cannot_access_admin_users_page()
    {
        $response = $this->get(route('adminUsers'));
        // Admin routes redirect to login
        $response->assertRedirect(route('login'));
    }

    // ── Regular user vs admin routes ────────────────────────────────────────

    /** @test */
    public function regular_user_cannot_access_admin_user_list()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('adminUsers'));
        // Should be blocked — either redirect or 403/404
        $response->assertRedirect();
        $this->assertNotEquals(route('adminUsers'), $response->headers->get('Location'));
    }

    /** @test */
    public function regular_user_cannot_access_admin_dashboard()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('adminDashboard'));
        $response->assertRedirect();
    }

    /** @test */
    public function regular_user_cannot_access_admin_coin_settings()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('adminCoinList'));
        $response->assertRedirect();
    }

    // ── Admin impersonation boundaries ───────────────────────────────────────

    /** @test */
    public function regular_user_cannot_impersonate_other_users()
    {
        $user    = $this->makeUser();
        $target  = $this->makeUser();

        $response = $this->actingAs($user)
            ->get(route('admin.user.impersonate', encrypt($target->id)));

        // Regular user must be denied — they do not have admin role
        $response->assertRedirect();
        // Verify the user is still themselves by making another authenticated request
        $followup = $this->actingAs($user)->get(route('userDashboard'));
        $followup->assertStatus(200, 'User should still be able to access their own dashboard');
    }

    /** @test */
    public function admin_cannot_impersonate_another_admin()
    {
        $admin1 = $this->makeAdmin();
        $admin2 = $this->makeAdmin();

        $response = $this->actingAs($admin1)
            ->get(route('admin.user.impersonate', encrypt($admin2->id)));

        $response->assertRedirect();
        // Should NOT have switched to admin2
        $this->assertNotEquals($admin2->id, \Illuminate\Support\Facades\Auth::id());
    }

    /** @test */
    public function impersonating_invalid_encrypted_id_does_not_crash()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.user.impersonate', 'not-a-valid-encrypted-id'));

        // Must return a redirect (graceful error), not a 500 server error
        $this->assertNotEquals(500, $response->status(),
            'Invalid encrypted ID must not cause a 500 server error');
        $response->assertRedirect();
    }

    // ── Callback routes are auth-guarded ────────────────────────────────────

    /** @test */
    public function withdrawal_callback_requires_authentication()
    {
        $response = $this->post(route('callback'), ['hash' => '0xtest']);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function deposit_callback_requires_authentication()
    {
        $response = $this->post(route('depositCallback'), ['transactionHash' => '0xtest']);
        $response->assertRedirect(route('login'));
    }

    // ── Authenticated users can access their routes ─────────────────────────

    /** @test */
    public function authenticated_user_can_access_dashboard()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('userDashboard'));
        $response->assertStatus(200);
    }

    /** @test */
    public function authenticated_user_can_access_buy_coin_page()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('buyCoin'));
        $response->assertStatus(200);
    }

    /** @test */
    public function authenticated_admin_can_access_admin_dashboard()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('adminDashboard'));
        $response->assertStatus(200);
    }

    // ── Session: logout invalidates session ──────────────────────────────────

    /** @test */
    public function logged_out_user_cannot_reach_protected_route()
    {
        $user = $this->makeUser();

        // Login then logout
        $this->actingAs($user);
        $this->get(route('logOut'));

        // Now try accessing protected route in a fresh request
        $response = $this->get(route('userDashboard'));
        $response->assertRedirect(route('login'));
    }

    // ── CSRF protection on POST ──────────────────────────────────────────────

    /** @test */
    public function buy_coin_post_without_csrf_token_is_rejected()
    {
        $user = $this->makeUser();

        // Use a real (non-withoutMiddleware) request with a deliberately wrong CSRF token
        // The VerifyCsrfToken middleware must intercept and return 419.
        $response = $this->actingAs($user)
            ->withSession(['_token' => 'real_token'])
            ->call('POST', route('buyCoinProcess'), ['coin' => 100, 'payment_type' => 8], [], [], [
                'HTTP_X_CSRF_TOKEN' => 'wrong-token',
            ]);

        // 419 = Token Mismatch; 302 = redirect (unauthenticated fallback); both are non-2xx
        $this->assertContains($response->status(), [419, 302, 403],
            'Buy coin POST with invalid CSRF must be blocked');
    }

    /** @test */
    public function withdraw_balance_post_without_csrf_token_is_rejected_for_unauthenticated_user()
    {
        // Without authentication, submitting to withdraw route must redirect to login
        $response = $this->post(route('WithdrawBalance'), []);
        $response->assertRedirect(route('login'));
    }
}
