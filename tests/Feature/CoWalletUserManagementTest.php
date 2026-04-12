<?php

namespace Tests\Feature;

use App\Model\AdminSetting;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoWalletUserManagementTest extends TestCase
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
        AdminSetting::updateOrCreate(
            ['slug' => CO_WALLET_FEATURE_ACTIVE_SLUG],
            ['value' => STATUS_ACTIVE]
        );

        AdminSetting::updateOrCreate(
            ['slug' => MAX_CO_WALLET_USER_SLUG],
            ['value' => 5]
        );
    }

    /** @test */
    public function creator_can_add_user_to_multisig_wallet_by_email()
    {
        $creator = $this->makeUser(['email' => 'creator@example.com']);
        $invitee = $this->makeUser(['email' => 'invitee@example.com']);

        $this->enableCoWalletFeature();

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $creator->id,
            'name' => 'Team Co Wallet',
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 0,
            'balance' => 0,
            'referral_balance' => 0,
            'type' => CO_WALLET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_co_users')->insert([
            'wallet_id' => $walletId,
            'user_id' => $creator->id,
            'status' => STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($creator)->post(route('addCoWalletUser', $walletId), [
            'email' => $invitee->email,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('wallet_co_users', [
            'wallet_id' => $walletId,
            'user_id' => $invitee->id,
        ]);
    }

    /** @test */
    public function non_creator_cannot_add_user_to_multisig_wallet()
    {
        $creator = $this->makeUser(['email' => 'creator2@example.com']);
        $member = $this->makeUser(['email' => 'member2@example.com']);
        $target = $this->makeUser(['email' => 'target2@example.com']);

        $this->enableCoWalletFeature();

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $creator->id,
            'name' => 'Owner Managed Wallet',
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 0,
            'balance' => 0,
            'referral_balance' => 0,
            'type' => CO_WALLET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_co_users')->insert([
            ['wallet_id' => $walletId, 'user_id' => $creator->id, 'status' => STATUS_ACTIVE, 'created_at' => now(), 'updated_at' => now()],
            ['wallet_id' => $walletId, 'user_id' => $member->id, 'status' => STATUS_ACTIVE, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($member)->post(route('addCoWalletUser', $walletId), [
            'email' => $target->email,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('wallet_co_users', [
            'wallet_id' => $walletId,
            'user_id' => $target->id,
        ]);
    }
}
