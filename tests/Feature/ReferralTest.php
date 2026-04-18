<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Model\AffiliationHistory;
use App\Model\BuyCoinReferralHistory;
use App\Model\ReferralSignBonusHistory;
use App\Model\ReferralUser;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Repository\AffiliateRepository;
use App\Services\BlockchainService;
use App\User;

/**
 * Referral Feature Tests
 *
 * Covers:
 *  - Signup referral creates the sponsor link
 *  - Signup rewards are paid on-chain to upline wallets, not as blind DB credits
 *  - Signup and buy rewards are capped at 5 levels
 *  - Withdrawal referral rewards are disabled
 *  - Dedicated referral tree page is available for users
 */
class ReferralTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::factory()->create([
            'role'        => 2,
            'status'      => 1,
            'is_verified' => 1,
        ]);
    }

    private function primaryWallet(int $userId): Wallet
    {
        return Wallet::where([
            'user_id' => $userId,
            'is_primary' => 1,
            'coin_type' => DEFAULT_COIN_TYPE,
        ])->orderBy('id', 'desc')->first();
    }

    private function makeWalletAddress(int $walletId, string $address = '0x1234567890abcdef1234567890abcdef12345678'): WalletAddressHistory
    {
        return WalletAddressHistory::create([
            'wallet_id' => $walletId,
            'coin_type' => DEFAULT_COIN_TYPE,
            'address'   => $address,
        ]);
    }

    private function mockBlockchainSuccess(string $txHash = '0xdeadbeef'): void
    {
        $this->mock(BlockchainService::class, function ($mock) use ($txHash) {
            $mock->shouldReceive('transferObxOnChain')
                ->andReturn(['txHash' => $txHash]);
        });
    }

    private function mockBlockchainFailure(string $error = 'Signer error'): void
    {
        $this->mock(BlockchainService::class, function ($mock) use ($error) {
            $mock->shouldReceive('transferObxOnChain')
                ->andReturn([]);
            $mock->shouldReceive('getLastSignerError')
                ->andReturn($error);
        });
    }

    private function setAdminSetting(string $key, $value): void
    {
        \App\Model\AdminSetting::updateOrCreate(['slug' => $key], ['slug' => $key, 'value' => $value]);
    }

    private function makeUplineChain(int $levels): array
    {
        $users = [];
        for ($i = 0; $i < $levels; $i++) {
            $users[] = $this->makeUser();
        }

        for ($i = 1; $i < $levels; $i++) {
            ReferralUser::create([
                'user_id' => $users[$i]->id,
                'parent_id' => $users[$i - 1]->id,
            ]);
        }

        foreach ($users as $index => $user) {
            $wallet = $this->primaryWallet($user->id);
            $this->makeWalletAddress($wallet->id, '0x' . str_pad(dechex($index + 1), 40, '0', STR_PAD_LEFT));
        }

        return $users;
    }

    /** @test */
    public function create_referral_user_records_parent_child_link(): void
    {
        $parent = $this->makeUser();
        $child  = $this->makeUser();

        $id = app(AffiliateRepository::class)->createReferralUser($child->id, $parent->id);

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('referral_users', [
            'user_id'   => $child->id,
            'parent_id' => $parent->id,
        ]);
    }

    /** @test */
    public function signup_bonus_pays_direct_parent_onchain(): void
    {
        $parent = $this->makeUser();
        $child  = $this->makeUser();

        $parentWallet = $this->primaryWallet($parent->id);
        $this->makeWalletAddress($parentWallet->id);

        $this->setAdminSetting('referral_signup_reward', '100');
        $this->setAdminSetting('fees_level1', '10');
        $this->setAdminSetting('max_affiliation_level', '5');
        $this->mockBlockchainSuccess('0xabc123');

        app(AffiliateRepository::class)->createReferralUser($child->id, $parent->id);

        $parentWallet->refresh();
        $this->assertEquals(10, (float)$parentWallet->balance);

        $this->assertDatabaseHas('referral_sign_bonus_histories', [
            'user_id'   => $child->id,
            'parent_id' => $parent->id,
            'wallet_id' => $parentWallet->id,
            'amount'    => 10,
        ]);
    }

    /** @test */
    public function signup_bonus_is_capped_at_five_levels(): void
    {
        $upline = $this->makeUplineChain(6);
        $child = $this->makeUser();

        foreach (range(1, 6) as $level) {
            $this->setAdminSetting('fees_level' . $level, '10');
        }
        $this->setAdminSetting('referral_signup_reward', '100');
        $this->setAdminSetting('max_affiliation_level', '6');
        $this->mockBlockchainSuccess();

        app(AffiliateRepository::class)->createReferralUser($child->id, $upline[5]->id);

        $this->assertEquals(0, (float) $this->primaryWallet($upline[0]->id)->balance);
        foreach (range(1, 5) as $offset) {
            $this->assertEquals(10, (float) $this->primaryWallet($upline[6 - $offset - 0]->id)->balance);
        }
    }

    /** @test */
    public function signup_bonus_skips_credit_when_onchain_fails(): void
    {
        $parent = $this->makeUser();
        $child  = $this->makeUser();
        $parentWallet = $this->primaryWallet($parent->id);
        $this->makeWalletAddress($parentWallet->id);

        $this->setAdminSetting('referral_signup_reward', '100');
        $this->setAdminSetting('fees_level1', '10');
        $this->mockBlockchainFailure();

        app(AffiliateRepository::class)->createReferralUser($child->id, $parent->id);

        $parentWallet->refresh();
        $this->assertEquals(0, (float)$parentWallet->balance);
        $this->assertDatabaseMissing('referral_sign_bonus_histories', ['user_id' => $child->id]);
    }

    /** @test */
    public function withdrawal_referral_is_disabled(): void
    {
        $parent = $this->makeUser();
        $child  = $this->makeUser();
        $parentWallet = $this->primaryWallet($parent->id);
        $this->makeWalletAddress($parentWallet->id);

        $this->mock(BlockchainService::class, function ($mock) {
            $mock->shouldNotReceive('transferObxOnChain');
        });

        $transaction = (object) [
            'wallet' => (object) [
                'type' => PERSONAL_WALLET,
                'user' => (object) ['id' => $child->id],
            ],
            'transaction_hash' => '0xwithdrawtx',
            'coin_type' => DEFAULT_COIN_TYPE,
            'fees' => 100,
        ];

        $result = app(AffiliateRepository::class)->storeAffiliationHistory($transaction);

        $this->assertEquals(1, $result);
        $this->assertEquals(0, (float) $parentWallet->fresh()->balance);
        $this->assertDatabaseCount('affiliation_histories', 0);
    }

    /** @test */
    public function buy_referral_pays_direct_parent_onchain(): void
    {
        $parent = $this->makeUser();
        $child  = $this->makeUser();
        ReferralUser::create(['user_id' => $child->id, 'parent_id' => $parent->id]);

        $parentWallet = $this->primaryWallet($parent->id);
        $this->makeWalletAddress($parentWallet->id);

        $this->setAdminSetting('fees_level1', '10');
        $this->mockBlockchainSuccess('0xbuytxhash');

        $transaction = (object) [
            'user_id' => $child->id,
            'referral_level' => 1,
            'id' => 999,
            'phase_id' => 1,
            'bonus' => 200,
        ];

        app(AffiliateRepository::class)->storeAffiliationHistoryForBuyCoin($transaction);

        $this->assertEquals(20, (float) $parentWallet->fresh()->balance);
        $this->assertDatabaseHas('buy_coin_referral_histories', [
            'user_id' => $parent->id,
            'child_id' => $child->id,
            'wallet_id' => $parentWallet->id,
            'buy_id' => 999,
            'level' => 1,
        ]);
    }

    /** @test */
    public function buy_referral_is_capped_at_five_levels(): void
    {
        $upline = $this->makeUplineChain(6);
        $child  = $this->makeUser();
        ReferralUser::create(['user_id' => $child->id, 'parent_id' => $upline[5]->id]);

        foreach (range(1, 6) as $level) {
            $this->setAdminSetting('fees_level' . $level, '10');
        }
        $this->mockBlockchainSuccess();

        $transaction = (object) [
            'user_id' => $child->id,
            'referral_level' => 6,
            'id' => 1001,
            'phase_id' => 1,
            'bonus' => 100,
        ];

        app(AffiliateRepository::class)->storeAffiliationHistoryForBuyCoin($transaction);

        $this->assertEquals(0, (float) $this->primaryWallet($upline[0]->id)->balance);
        foreach (range(1, 5) as $offset) {
            $this->assertEquals(10, (float) $this->primaryWallet($upline[6 - $offset - 0]->id)->balance);
        }
    }

    /** @test */
    public function referral_repository_builds_upline_and_downline_tree(): void
    {
        $grandparent = $this->makeUser();
        $parent = $this->makeUser();
        $child = $this->makeUser();

        ReferralUser::create(['user_id' => $parent->id, 'parent_id' => $grandparent->id]);
        ReferralUser::create(['user_id' => $child->id, 'parent_id' => $parent->id]);

        $repo = app(AffiliateRepository::class);
        $upline = $repo->getUpline($child->id, 5);
        $downline = $repo->getDownlineTree($grandparent->id, 5);

        $this->assertCount(2, $upline);
        $this->assertEquals($parent->id, $upline[0]['user']->id);
        $this->assertEquals($grandparent->id, $upline[1]['user']->id);
        $this->assertCount(1, $downline);
        $this->assertEquals($parent->id, $downline[0]['user']->id);
        $this->assertCount(1, $downline[0]['children']);
        $this->assertEquals($child->id, $downline[0]['children'][0]['user']->id);
    }
}
