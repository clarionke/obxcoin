<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\User;

/**
 * HTTP Security Headers Tests
 *
 * Verifies that the SecurityHeaders middleware injects the correct
 * security response headers on every request:
 *
 * - X-Content-Type-Options: nosniff
 * - X-Frame-Options: SAMEORIGIN
 * - X-XSS-Protection: 1; mode=block
 * - Referrer-Policy: strict-origin-when-cross-origin
 * - Permissions-Policy restricts camera/mic/geolocation
 * - HSTS only on HTTPS requests
 * - Headers present on both web and API routes
 * - Headers present for unauthenticated requests
 * - Headers present for authenticated requests
 */
class SecurityHeadersTest extends TestCase
{
    // ── Public pages ─────────────────────────────────────────────────────────

    /** @test */
    public function login_page_has_x_content_type_options_header()
    {
        $response = $this->get(route('login'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @test */
    public function login_page_has_x_frame_options_sameorigin()
    {
        $response = $this->get(route('login'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function login_page_has_xss_protection_header()
    {
        $response = $this->get(route('login'));
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /** @test */
    public function login_page_has_referrer_policy_header()
    {
        $response = $this->get(route('login'));
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** @test */
    public function login_page_has_permissions_policy_header()
    {
        $response = $this->get(route('login'));
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    // ── Authenticated user pages ─────────────────────────────────────────────

    /** @test */
    public function authenticated_dashboard_has_security_headers()
    {
        $user = User::factory()->create([
            'status'      => 1,
            'role'        => 2,
            'is_verified' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('userDashboard'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    // ── API routes ───────────────────────────────────────────────────────────

    /** @test */
    public function api_unauthenticated_request_has_security_headers()
    {
        $response = $this->getJson('/api/user/wallets');

        // Must not be a 200 success for unauthenticated requests
        $this->assertNotEquals(200, $response->status());
        // Security headers must be present regardless of auth status
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function webhook_endpoint_has_security_headers()
    {
        $response = $this->postJson('/api/presale/webhook', []);

        // Must not succeed with empty payload
        $this->assertNotEquals(200, $response->status());
        // Security headers must be present
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // ── Headers must NOT expose server internals ─────────────────────────────

    /** @test */
    public function responses_do_not_expose_x_powered_by_php_version()
    {
        $response = $this->get(route('login'));
        // X-Powered-By: PHP/8.x would leak version information
        $this->assertFalse(
            $response->headers->has('X-Powered-By'),
            'X-Powered-By header must not be present in responses'
        );
    }

    /** @test */
    public function responses_do_not_expose_server_software_header()
    {
        $response = $this->get(route('login'));
        $serverHeader = $response->headers->get('Server', '');
        // Ensure the server header does not contain version strings like "Apache/2.4" or "nginx/1.x"
        $hasVersion = (bool) preg_match('/\d+\.\d+/', $serverHeader);
        $this->assertFalse($hasVersion,
            'Server header must not contain version strings, got: ' . $serverHeader);
    }

    // ── HSTS only for HTTPS ──────────────────────────────────────────────────

    /** @test */
    public function http_request_does_not_set_hsts_header()
    {
        // In test env the request is not HTTPS, so HSTS must NOT be set
        $response = $this->get(route('login'));

        // HSTS must NOT be sent over plain HTTP (browsers would enforce HTTPS prematurely)
        $this->assertFalse(
            $response->headers->has('Strict-Transport-Security'),
            'HSTS must not be sent for non-HTTPS requests'
        );
    }
}
