<?php

namespace Tests\Feature;

use App\Http\Services\TransactionService;
use App\Model\Wallet;
use App\Services\BlockchainService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObxExternalWithdrawalRollbackTest extends TestCase
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

    private function ensureObxCoin(): int
    {
        $coinId = DB::table('coins')->where('type', DEFAULT_COIN_TYPE)->value('id');
        if (!empty($coinId)) {
            return (int) $coinId;
        }

        return (int) DB::table('coins')->insertGetId([
            'name' => DEFAULT_COIN_TYPE,
            'type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_withdrawal' => 1,
            'is_deposit' => 1,
            'is_buy' => 1,
            'is_sell' => 1,
            'minimum_withdrawal' => 0,
            'maximum_withdrawal' => 99999999,
            'withdrawal_fees' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeWallet(int $userId, int $coinId, float $balance, int $type = PERSONAL_WALLET): int
    {
        return (int) DB::table('wallets')->insertGetId([
            'user_id' => $userId,
            'name' => 'OBX Wallet',
            'coin_id' => $coinId,
            'coin_type' => DEFAULT_COIN_TYPE,
            'status' => STATUS_ACTIVE,
            'is_primary' => 1,
            'balance' => $balance,
            'referral_balance' => 0,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addWalletAddress(int $walletId, string $address): void
    {
        DB::table('wallet_address_histories')->insert([
            'wallet_id' => $walletId,
            'address' => strtolower($address),
            'coin_type' => DEFAULT_COIN_TYPE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function obx_external_withdrawal_marks_success_and_debits_balance_when_chain_send_succeeds()
    {
        $user = $this->makeUser(['email' => 'withdraw-ok@example.com']);
        $coinId = $this->ensureObxCoin();
        $walletId = $this->makeWallet($user->id, $coinId, 100.0);

        $senderAddress = '0x1111111111111111111111111111111111111111';
        $recipientAddress = '0x2222222222222222222222222222222222222222';
        $this->addWalletAddress($walletId, $senderAddress);

        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxFromOnChain')
            ->once()
            ->with(strtolower($senderAddress), $recipientAddress, '10')
            ->andReturn(['txHash' => '0xabc123']);
        $this->app->instance(BlockchainService::class, $blockchain);

        $service = new TransactionService();
        $result = $service->send($walletId, $recipientAddress, '10', false, null, $user->id, 'withdraw test');

        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta(90.0, (float) Wallet::where('id', $walletId)->value('balance'), 0.00000001);

        $this->assertDatabaseHas('withdraw_histories', [
            'wallet_id' => $walletId,
            'address' => $recipientAddress,
            'amount' => 10.00000000,
            'status' => STATUS_SUCCESS,
            'transaction_hash' => '0xabc123',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function obx_external_withdrawal_rolls_back_wallet_and_history_when_chain_send_fails()
    {
        $user = $this->makeUser(['email' => 'withdraw-fail@example.com']);
        $coinId = $this->ensureObxCoin();
        $walletId = $this->makeWallet($user->id, $coinId, 100.0);

        $senderAddress = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $recipientAddress = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $this->addWalletAddress($walletId, $senderAddress);

        $blockchain = \Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('transferObxFromOnChain')
            ->once()
            ->with(strtolower($senderAddress), $recipientAddress, '10')
            ->andReturn(null);
        $this->app->instance(BlockchainService::class, $blockchain);

        $service = new TransactionService();
        $result = $service->send($walletId, $recipientAddress, '10', false, null, $user->id, 'withdraw test fail');

        $this->assertFalse($result['success']);
        $this->assertSame('On-chain OBX send failed', $result['message']);
        $this->assertEqualsWithDelta(100.0, (float) Wallet::where('id', $walletId)->value('balance'), 0.00000001);

        $this->assertDatabaseMissing('withdraw_histories', [
            'wallet_id' => $walletId,
            'address' => $recipientAddress,
            'amount' => 10.00000000,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_can_withdraw_from_personal_wallet_to_a_team_wallet_where_they_are_a_member()
    {
        $sender = $this->makeUser(['email' => 'sender-member@example.com']);
        $teamOwner = $this->makeUser(['email' => 'team-owner@example.com']);

        $coinId = $this->ensureObxCoin();

        $senderWalletId = $this->makeWallet($sender->id, $coinId, 100.0, PERSONAL_WALLET);
        $this->addWalletAddress($senderWalletId, '0x3333333333333333333333333333333333333333');

        $teamWalletId = $this->makeWallet($teamOwner->id, $coinId, 50.0, CO_WALLET);
        $teamWalletAddress = '0x4444444444444444444444444444444444444444';
        $this->addWalletAddress($teamWalletId, $teamWalletAddress);

        DB::table('wallet_co_users')->insert([
            'wallet_id' => $teamWalletId,
            'user_id' => $sender->id,
            'status' => STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.id' => $senderWalletId, 'wallets.user_id' => $sender->id])
            ->select(
                'wallets.*',
                'coins.status as coin_status',
                'coins.is_withdrawal',
                'coins.minimum_withdrawal',
                'coins.maximum_withdrawal',
                'coins.withdrawal_fees'
            )
            ->first();

        $request = new Request([
            'amount' => 1,
            'address' => $teamWalletAddress,
        ]);

        $service = new TransactionService();
        $result = $service->checkWithdrawalValidation($request, $sender, $wallet);

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['message']);
    }
}
