<?php

namespace Tests\Feature;

use App\Model\AdminSetting;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiSignatureDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 1,
            'role' => 2,
            'is_verified' => 1,
        ], $attrs));
    }

    private function setCoWalletFeature(bool $enabled): void
    {
        AdminSetting::updateOrCreate(
            ['slug' => CO_WALLET_FEATURE_ACTIVE_SLUG],
            ['value' => $enabled ? STATUS_ACTIVE : STATUS_PENDING]
        );

        AdminSetting::updateOrCreate(
            ['slug' => CO_WALLET_WITHDRAWAL_USER_APPROVAL_PERCENTAGE_SLUG],
            ['value' => 60]
        );
    }

    /** @test */
    public function dashboard_shows_multisig_panel_when_feature_is_enabled()
    {
        $user = $this->makeUser();
        $this->setCoWalletFeature(true);

        $response = $this->actingAs($user)->get(route('userDashboard'));

        $response->assertStatus(200);
        $response->assertSee('Multi-signature Wallet Security');
        $response->assertSee('Approval Threshold');
    }

    /** @test */
    public function dashboard_hides_multisig_panel_when_feature_is_disabled()
    {
        $user = $this->makeUser();
        $this->setCoWalletFeature(false);

        $response = $this->actingAs($user)->get(route('userDashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Multi-signature Wallet Security');
    }

    /** @test */
    public function dashboard_only_lists_pending_requests_that_user_has_not_approved()
    {
        $user = $this->makeUser();
        $requester = $this->makeUser(['email' => 'requester@example.com']);

        $this->setCoWalletFeature(true);

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $requester->id,
            'name' => 'Team MultiSig Wallet',
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 0,
            'balance' => 150,
            'referral_balance' => 0,
            'type' => CO_WALLET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_co_users')->insert([
            ['wallet_id' => $walletId, 'user_id' => $user->id, 'status' => STATUS_ACTIVE, 'can_approve' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['wallet_id' => $walletId, 'user_id' => $requester->id, 'status' => STATUS_ACTIVE, 'can_approve' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $pendingIdVisible = DB::table('temp_withdraws')->insertGetId([
            'user_id' => $requester->id,
            'wallet_id' => $walletId,
            'withdraw_id' => null,
            'amount' => 10,
            'address' => '0xabc123',
            'message' => 'Need approval',
            'status' => STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('co_wallet_withdraw_approvals')->insert([
            'temp_withdraw_id' => $pendingIdVisible,
            'wallet_id' => $walletId,
            'user_id' => $requester->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pendingIdHidden = DB::table('temp_withdraws')->insertGetId([
            'user_id' => $requester->id,
            'wallet_id' => $walletId,
            'withdraw_id' => null,
            'amount' => 5,
            'address' => '0xdef456',
            'message' => 'Already approved by current user',
            'status' => STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('co_wallet_withdraw_approvals')->insert([
            ['temp_withdraw_id' => $pendingIdHidden, 'wallet_id' => $walletId, 'user_id' => $requester->id, 'created_at' => now(), 'updated_at' => now()],
            ['temp_withdraw_id' => $pendingIdHidden, 'wallet_id' => $walletId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->get(route('userDashboard'));

        $response->assertStatus(200);
        $response->assertSee('Request #' . $pendingIdVisible);
        $response->assertDontSee('Request #' . $pendingIdHidden);
    }
}
