<?php

namespace Tests\Feature;

use App\Model\AdminSetting;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
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

    /** @test */
    public function creator_can_create_multiple_team_wallets_with_same_coin_type_and_unique_identifier()
    {
        $creator = $this->makeUser(['email' => 'creator3@example.com']);
        $this->enableCoWalletFeature();

        $coinId = DB::table('coins')->where('type', 'LTCT')->value('id');
        if (empty($coinId)) {
            $coinId = DB::table('coins')->insertGetId([
                'name' => 'Litecoin Test',
                'type' => 'LTCT',
                'status' => STATUS_ACTIVE,
                'is_withdrawal' => 1,
                'is_deposit' => 1,
                'is_buy' => 1,
                'is_sell' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($creator)->post(route('createWallet'), [
            'type' => CO_WALLET,
            'wallet_name' => 'Operations Team Wallet',
            'coin_type' => 'LTCT',
            'max_co_users' => 3,
        ])->assertRedirect();

        $this->actingAs($creator)->post(route('createWallet'), [
            'type' => CO_WALLET,
            'wallet_name' => 'Treasury Team Wallet',
            'coin_type' => 'LTCT',
            'max_co_users' => 4,
        ])->assertRedirect();

        $wallets = DB::table('wallets')
            ->where('user_id', $creator->id)
            ->where('type', CO_WALLET)
            ->where('coin_id', $coinId)
            ->get(['id', 'key']);

        $this->assertCount(2, $wallets);
        $this->assertNotEquals($wallets[0]->key, $wallets[1]->key);

        if (Schema::hasColumn('wallets', 'team_wallet_uid')) {
            $walletsWithUid = DB::table('wallets')
                ->where('user_id', $creator->id)
                ->where('type', CO_WALLET)
                ->where('coin_id', $coinId)
                ->get(['id', 'team_wallet_uid']);

            $this->assertNotEmpty($walletsWithUid[0]->team_wallet_uid);
            $this->assertNotEmpty($walletsWithUid[1]->team_wallet_uid);
            $this->assertNotEquals($walletsWithUid[0]->team_wallet_uid, $walletsWithUid[1]->team_wallet_uid);
        }
    }
}
