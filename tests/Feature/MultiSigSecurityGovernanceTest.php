<?php

namespace Tests\Feature;

use App\Model\AdminSetting;
use App\Model\CoWalletSignatoryChangeRequest;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiSigSecurityGovernanceTest extends TestCase
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

    private function enableCoWalletFeature(): void
    {
        AdminSetting::updateOrCreate(['slug' => CO_WALLET_FEATURE_ACTIVE_SLUG], ['value' => STATUS_ACTIVE]);
        AdminSetting::updateOrCreate(['slug' => MAX_CO_WALLET_USER_SLUG], ['value' => 5]);
        AdminSetting::updateOrCreate(['slug' => CO_WALLET_WITHDRAWAL_USER_APPROVAL_PERCENTAGE_SLUG], ['value' => 60]);
    }

    /** @test */
    public function creator_can_assign_signatory_permission_to_co_user()
    {
        $this->enableCoWalletFeature();

        $creator = $this->makeUser(['email' => 'creator-sign@example.com']);
        $member = $this->makeUser(['email' => 'member-sign@example.com']);

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $creator->id,
            'name' => 'Secured MultiSig',
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 0,
            'balance' => 100,
            'referral_balance' => 0,
            'type' => CO_WALLET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_co_users')->insert([
            ['wallet_id' => $walletId, 'user_id' => $creator->id, 'status' => STATUS_ACTIVE, 'can_approve' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['wallet_id' => $walletId, 'user_id' => $member->id, 'status' => STATUS_ACTIVE, 'can_approve' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $coUserId = DB::table('wallet_co_users')->where('wallet_id', $walletId)->where('user_id', $member->id)->value('id');

        $response = $this->actingAs($creator)->post(route('setCoWalletUserApprover', [$walletId, $coUserId]), [
            'can_approve' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('wallet_co_users', [
            'id' => $coUserId,
            'can_approve' => 1,
        ]);
    }

    /** @test */
    public function non_signatory_cannot_approve_pending_withdrawal()
    {
        $this->enableCoWalletFeature();

        $creator = $this->makeUser(['email' => 'creator-wd@example.com']);
        $nonSignatory = $this->makeUser(['email' => 'nonsig-wd@example.com']);

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $creator->id,
            'name' => 'Approval Restricted Wallet',
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 0,
            'balance' => 200,
            'referral_balance' => 0,
            'type' => CO_WALLET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_co_users')->insert([
            ['wallet_id' => $walletId, 'user_id' => $creator->id, 'status' => STATUS_ACTIVE, 'can_approve' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['wallet_id' => $walletId, 'user_id' => $nonSignatory->id, 'status' => STATUS_ACTIVE, 'can_approve' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $tempWithdrawId = DB::table('temp_withdraws')->insertGetId([
            'user_id' => $creator->id,
            'wallet_id' => $walletId,
            'withdraw_id' => null,
            'amount' => 10,
            'address' => '0xsecure123',
            'message' => 'pending signatory approval',
            'status' => STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($nonSignatory)->post(route('approveCoWalletWithdraw', $tempWithdrawId));

        $response->assertRedirect();
        $this->assertDatabaseMissing('co_wallet_withdraw_approvals', [
            'temp_withdraw_id' => $tempWithdrawId,
            'user_id' => $nonSignatory->id,
        ]);
    }

    /** @test */
    public function signatory_change_request_is_applied_after_required_approval()
    {
        $this->enableCoWalletFeature();

        $creator = $this->makeUser(['email' => 'creator-change@example.com']);
        $target = $this->makeUser(['email' => 'target-change@example.com']);

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $creator->id,
            'name' => 'Signatory Governance Wallet',
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 0,
            'balance' => 50,
            'referral_balance' => 0,
            'type' => CO_WALLET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_co_users')->insert([
            ['wallet_id' => $walletId, 'user_id' => $creator->id, 'status' => STATUS_ACTIVE, 'can_approve' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['wallet_id' => $walletId, 'user_id' => $target->id, 'status' => STATUS_ACTIVE, 'can_approve' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $targetCoUserId = DB::table('wallet_co_users')->where('wallet_id', $walletId)->where('user_id', $target->id)->value('id');

        $request = CoWalletSignatoryChangeRequest::create([
            'wallet_id' => $walletId,
            'requested_by_user_id' => $creator->id,
            'requested_by_admin_id' => null,
            'target_user_id' => $target->id,
            'target_wallet_co_user_id' => $targetCoUserId,
            'requested_can_approve' => 1,
            'status' => STATUS_PENDING,
        ]);

        $response = $this->actingAs($creator)->post(route('approveCoWalletSignatoryChange', $request->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('wallet_co_users', [
            'id' => $targetCoUserId,
            'can_approve' => 1,
        ]);
        $this->assertDatabaseHas('co_wallet_signatory_change_requests', [
            'id' => $request->id,
            'status' => STATUS_ACCEPTED,
        ]);
    }
}
