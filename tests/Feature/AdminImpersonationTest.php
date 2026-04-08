<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use App\User;

/**
 * Feature tests for admin user-impersonation.
 *
 * Covers:
 *  • Admin can start impersonating an active regular user
 *  • Impersonation stores admin ID in session and redirects to user dashboard
 *  • Admin cannot impersonate another admin
 *  • Non-admin cannot access the impersonate route
 *  • Stop-impersonating restores the original admin session
 *  • Stop-impersonating without an active session redirects safely
 *  • Banner session key is set/cleared correctly
 */
class AdminImpersonationTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'status'      => 1,   // STATUS_ACTIVE / STATUS_SUCCESS
            'role'        => 1,   // USER_ROLE_ADMIN
            'is_verified' => 1,
        ]);
    }

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'status'      => 1,
            'role'        => 2,   // USER_ROLE_USER
            'is_verified' => 1,
        ], $attrs));
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_start_impersonating_a_regular_user()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $response = $this->actingAs($admin)
            ->get(route('admin.user.impersonate', encrypt($user->id)));

        $response->assertRedirect(route('userDashboard'));

        // After redirect the logged-in user should be the target user
        $this->assertEquals($user->id, Auth::id());
    }

    /** @test */
    public function impersonation_stores_admin_id_in_session()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->get(route('admin.user.impersonate', encrypt($user->id)));

        $this->assertEquals($admin->id, session('impersonating_admin_id'));
    }

    /** @test */
    public function admin_cannot_impersonate_another_admin()
    {
        $admin1 = $this->makeAdmin();
        $admin2 = $this->makeAdmin();

        $response = $this->actingAs($admin1)
            ->get(route('admin.user.impersonate', encrypt($admin2->id)));

        // Should redirect back with dismiss message, NOT log in as admin2
        $response->assertRedirect();
        $response->assertSessionHas('dismiss');

        // Original admin should still be logged in
        $this->assertEquals($admin1->id, Auth::id());
    }

    /** @test */
    public function regular_user_cannot_access_impersonate_route()
    {
        $user        = $this->makeUser();
        $targetUser  = $this->makeUser();

        // The admin middleware will reject this and force logout/redirect
        $response = $this->actingAs($user)
            ->get(route('admin.user.impersonate', encrypt($targetUser->id)));

        // Should be redirected away (to login or back), not to user dashboard
        $response->assertRedirect();
        $location = $response->headers->get('Location', '');
        $this->assertStringNotContainsString(route('userDashboard'), $location);
    }

    /** @test */
    public function stop_impersonating_restores_admin_session()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        // Start impersonation
        $this->actingAs($admin)
            ->get(route('admin.user.impersonate', encrypt($user->id)));

        // Confirm we are now the user
        $this->assertEquals($user->id, Auth::id());

        // Stop impersonation
        $response = $this->actingAs(Auth::user())
            ->withSession(['impersonating_admin_id' => $admin->id])
            ->get(route('admin.stop.impersonating'));

        $response->assertRedirect(route('adminUsers'));

        // Admin should be restored
        $this->assertEquals($admin->id, Auth::id());
    }

    /** @test */
    public function stop_impersonating_clears_session_key()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($user)
            ->withSession(['impersonating_admin_id' => $admin->id])
            ->get(route('admin.stop.impersonating'));

        $this->assertNull(session('impersonating_admin_id'));
    }

    /** @test */
    public function stop_impersonating_without_session_redirects_safely()
    {
        $user = $this->makeUser();

        // No impersonating_admin_id in session
        $response = $this->actingAs($user)
            ->withSession([])
            ->get(route('admin.stop.impersonating'));

        // Should redirect somewhere reasonable, not crash
        $response->assertRedirect();
    }

    /** @test */
    public function impersonating_a_nonexistent_user_redirects_with_error()
    {
        $admin = $this->makeAdmin();

        // Use a very high non-existent ID
        $fakeId = 999999999;

        $response = $this->actingAs($admin)
            ->get(route('admin.user.impersonate', encrypt($fakeId)));

        $response->assertRedirect();
        $response->assertSessionHas('dismiss');

        // Admin should still be logged in
        $this->assertEquals($admin->id, Auth::id());
    }

    /** @test */
    public function impersonation_success_flash_message_is_set()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $response = $this->actingAs($admin)
            ->get(route('admin.user.impersonate', encrypt($user->id)));

        $response->assertSessionHas('success');
    }

    /** @test */
    public function stop_impersonating_success_flash_message_is_set()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $response = $this->actingAs($user)
            ->withSession(['impersonating_admin_id' => $admin->id])
            ->get(route('admin.stop.impersonating'));

        $response->assertSessionHas('success');
    }
}
