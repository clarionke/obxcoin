<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use App\Model\AdminSetting;
use App\Model\StakingPool;
use App\Model\StakingPosition;
use App\Model\StakingTransaction;
use App\User;

/**
 * Staking Feature Tests
 *
 * Covers:
 *  - Unauthenticated access is blocked
 *  - Authenticated user can view staking index
 *  - Authenticated user cannot stake with invalid pool
 *  - Authenticated user cannot stake with missing wallet_address
 *  - Authenticated user cannot stake with malformed tx_hash
 *  - Authenticated user cannot stake if tx_hash already exists (duplicate)
 *  - Authenticated user can confirm a valid stake (position + 2 transactions)
 *  - Confirm stake with 0 burn_bps creates only stake_in transaction
 *  - Authenticated user cannot unstake unknown position
 *  - Authenticated user cannot unstake another user's position
 *  - Authenticated user can confirm unstake (position→unstaked, transactions recorded)
 *  - Admin can view staking index
 *  - Admin can view staking pools
 *  - Admin can save a new pool
 *  - Admin can update an existing pool
 */
class StakingTest extends TestCase
{
    use DatabaseTransactions;

    // ── Factory helpers ─────────────────────────────────────────────────────

    private function makeUser(int $role = 2): User
    {
        return User::factory()->create([
            'role'        => $role,
            'status'      => 1,
            'is_verified' => 1,
        ]);
    }

    private function makeAdmin(): User
    {
        return $this->makeUser(1);
    }

    private function makePool(array $overrides = []): StakingPool
    {
        return StakingPool::create(array_merge([
            'name'                => 'Silver 30-Day',
            'pool_id_onchain'     => 0,
            'min_amount'          => 500,
            'duration_days'       => 30,
            'apy_bps'             => 500,   // 5%
            'burn_on_stake_bps'   => 100,   // 1%
            'burn_on_unstake_bps' => 200,   // 2%
            'status'              => 1,
        ], $overrides));
    }

    private function validTxHash(): string
    {
        return '0x' . str_pad('a', 64, 'a');
    }

    private function configureStakingOnchain(string $contract = '0x9999999999999999999999999999999999999999'): void
    {
        AdminSetting::updateOrCreate(['slug' => 'staking_contract'], ['value' => strtolower($contract)]);
        AdminSetting::updateOrCreate(['slug' => 'obx_token_decimals'], ['value' => '18']);
        AdminSetting::updateOrCreate(['slug' => 'chain_link'], ['value' => 'https://rpc.test.local']);
    }

    private function fakeStakeRpc(string $txHash, string $wallet, string $contract, int $poolIdOnchain, string $grossAmount): void
    {
        $wei = bcmul((string)$grossAmount, '1000000000000000000', 0);
        $poolHex = str_pad(dechex($poolIdOnchain), 64, '0', STR_PAD_LEFT);
        $amountHex = str_pad($this->decToHex($wei), 64, '0', STR_PAD_LEFT);

        Http::fake(function ($request) use ($txHash, $wallet, $contract, $poolHex, $amountHex) {
            $payload = $request->data();
            $method = $payload['method'] ?? '';
            if ($method === 'eth_getTransactionReceipt') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'status' => '0x1',
                        'to' => strtolower($contract),
                        'transactionHash' => $txHash,
                    ],
                ], 200);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'from' => strtolower($wallet),
                    'input' => '0x7b0472f0' . $poolHex . $amountHex,
                    'to' => strtolower($contract),
                    'hash' => $txHash,
                ],
            ], 200);
        });
    }

    private function fakeUnstakeRpc(string $txHash, string $wallet, string $contract, int $stakeIdx): void
    {
        $idxHex = str_pad(dechex($stakeIdx), 64, '0', STR_PAD_LEFT);

        Http::fake(function ($request) use ($txHash, $wallet, $contract, $idxHex) {
            $payload = $request->data();
            $method = $payload['method'] ?? '';
            if ($method === 'eth_getTransactionReceipt') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'status' => '0x1',
                        'to' => strtolower($contract),
                        'transactionHash' => $txHash,
                    ],
                ], 200);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'from' => strtolower($wallet),
                    'input' => '0x2e17de78' . $idxHex,
                    'to' => strtolower($contract),
                    'hash' => $txHash,
                ],
            ], 200);
        });
    }

    private function decToHex(string $dec): string
    {
        if ($dec === '0') {
            return '0';
        }

        $hex = '';
        $map = '0123456789abcdef';
        while (bccomp($dec, '0', 0) > 0) {
            $rem = (int) bcmod($dec, '16');
            $hex = $map[$rem] . $hex;
            $dec = bcdiv($dec, '16', 0);
        }
        return $hex;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Auth boundary
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function unauthenticated_user_cannot_view_staking_index()
    {
        $response = $this->get(route('user.staking.index'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_post_confirm_stake()
    {
        $response = $this->post(route('user.staking.confirmStake'));
        $response->assertRedirect(route('login'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // User: view index
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function authenticated_user_can_view_staking_index()
    {
        $user = $this->makeUser();
        $this->makePool();

        $response = $this->actingAs($user)->get(route('user.staking.index'));
        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // User: confirmStake validation
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function user_cannot_stake_with_invalid_pool_id()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'        => 99999,
            'wallet_address' => '0x' . str_pad('1', 40, '1'),
            'gross_amount'   => 500,
            'tx_hash'        => $this->validTxHash(),
        ]);

        $response->assertRedirect(); // validation error redirects back
        $response->assertSessionHasErrors('pool_id');
    }

    /** @test */
    public function user_cannot_stake_with_missing_wallet_address()
    {
        $user = $this->makeUser();
        $pool = $this->makePool();

        $response = $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'      => $pool->id,
            'gross_amount' => 500,
            'tx_hash'      => $this->validTxHash(),
        ]);

        $response->assertSessionHasErrors('wallet_address');
    }

    /** @test */
    public function user_cannot_stake_with_malformed_tx_hash()
    {
        $user = $this->makeUser();
        $pool = $this->makePool();

        $response = $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'        => $pool->id,
            'wallet_address' => '0x' . str_pad('1', 40, '1'),
            'gross_amount'   => 500,
            'tx_hash'        => 'not-a-tx-hash',
        ]);

        $response->assertSessionHasErrors('tx_hash');
    }

    /** @test */
    public function user_can_confirm_a_valid_stake_and_two_transactions_are_recorded()
    {
        $user = $this->makeUser();
        $pool = $this->makePool();
        $hash = $this->validTxHash();
        $contract = '0x9999999999999999999999999999999999999999';
        $walletAddress = '0x' . str_pad('2', 40, '2');

        $this->configureStakingOnchain($contract);
        $this->fakeStakeRpc($hash, $walletAddress, $contract, 0, '1000');

        $response = $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'        => $pool->id,
            'wallet_address' => $walletAddress,
            'gross_amount'   => 1000,
            'tx_hash'        => $hash,
        ]);

        $response->assertRedirect(); // success redirect

        $this->assertDatabaseHas('staking_positions', [
            'user_id'       => $user->id,
            'pool_id'       => $pool->id,
            'tx_hash_stake' => $hash,
            'status'        => 'active',
        ]);

        // stake_in + burn_stake
        $this->assertEquals(2, StakingTransaction::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('staking_transactions', [
            'user_id' => $user->id,
            'type'    => 'stake_in',
            'tx_hash' => $hash,
        ]);
        $this->assertDatabaseHas('staking_transactions', [
            'user_id' => $user->id,
            'type'    => 'burn_stake',
        ]);
    }

    /** @test */
    public function user_cannot_stake_with_duplicate_tx_hash()
    {
        $user = $this->makeUser();
        $pool = $this->makePool();
        $hash = $this->validTxHash();
        $contract = '0x9999999999999999999999999999999999999999';
        $walletAddress = '0x' . str_pad('3', 40, '3');

        $this->configureStakingOnchain($contract);
        $this->fakeStakeRpc($hash, $walletAddress, $contract, 0, '1000');

        // First stake succeeds
        $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'        => $pool->id,
            'wallet_address' => $walletAddress,
            'gross_amount'   => 1000,
            'tx_hash'        => $hash,
        ]);

        // Second stake with same hash should fail
        $response = $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'        => $pool->id,
            'wallet_address' => $walletAddress,
            'gross_amount'   => 1000,
            'tx_hash'        => $hash,
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, StakingPosition::where('user_id', $user->id)->count());
    }

    /** @test */
    public function confirm_stake_with_zero_burn_bps_creates_only_stake_in_transaction()
    {
        $user = $this->makeUser();
        $pool = $this->makePool(['burn_on_stake_bps' => 0]);
        $hash = $this->validTxHash();
        $contract = '0x9999999999999999999999999999999999999999';
        $walletAddress = '0x' . str_pad('4', 40, '4');

        $this->configureStakingOnchain($contract);
        $this->fakeStakeRpc($hash, $walletAddress, $contract, 0, '1000');

        $this->actingAs($user)->post(route('user.staking.confirmStake'), [
            'pool_id'            => $pool->id,
            'wallet_address'     => $walletAddress,
            'gross_amount'       => 1000,
            'tx_hash'            => $hash,
            'burn_on_stake_bps'  => 0,
        ]);

        // Only stake_in, no burn_stake row
        $this->assertEquals(1, StakingTransaction::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('staking_transactions', [
            'user_id' => $user->id,
            'type'    => 'stake_in',
        ]);
        $this->assertDatabaseMissing('staking_transactions', [
            'user_id' => $user->id,
            'type'    => 'burn_stake',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // User: confirmUnstake
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function user_cannot_unstake_unknown_position()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('user.staking.confirmUnstake'), [
            'position_id' => 99999,
            'tx_hash'     => $this->validTxHash(),
        ]);

        $response->assertRedirect();
    }

    /** @test */
    public function user_cannot_unstake_another_users_position()
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $pool  = $this->makePool();
        $hash  = $this->validTxHash();

        // Create position owned by $owner
        $position = StakingPosition::create([
            'user_id'        => $owner->id,
            'pool_id'        => $pool->id,
            'wallet_address' => '0x' . str_pad('5', 40, '5'),
            'gross_amount'   => 1000,
            'burned_on_stake'=> 10,
            'net_amount'     => 990,
            'status'         => 'active',
            'tx_hash_stake'  => $hash,
            'staked_at'      => now(),
            'lock_until'     => now()->subMinute(), // unlocked
        ]);

        $unstakeHash = '0x' . str_pad('b', 64, 'b');
        $response = $this->actingAs($other)->post(route('user.staking.confirmUnstake'), [
            'position_id' => $position->id,
            'tx_hash'     => $unstakeHash,
        ]);

        // Position must still belong to $owner unchanged
        $this->assertDatabaseHas('staking_positions', [
            'id'     => $position->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function user_can_confirm_unstake_and_position_is_marked_unstaked()
    {
        $user = $this->makeUser();
        $pool = $this->makePool();
        $hash = $this->validTxHash();
        $contract = '0x9999999999999999999999999999999999999999';
        $walletAddress = '0x' . str_pad('6', 40, '6');

        $this->configureStakingOnchain($contract);

        $position = StakingPosition::create([
            'user_id'        => $user->id,
            'pool_id'        => $pool->id,
            'wallet_address' => $walletAddress,
            'gross_amount'   => 1000,
            'burned_on_stake'=> 10,
            'net_amount'     => 990,
            'status'         => 'active',
            'tx_hash_stake'  => $hash,
            'staked_at'      => now()->subDays(31),
            'lock_until'     => now()->subDay(), // already unlocked
            'contract_stake_idx' => 0,
        ]);

        $unstakeHash = '0x' . str_pad('c', 64, 'c');
        $this->fakeUnstakeRpc($unstakeHash, $walletAddress, $contract, 0);
        $response = $this->actingAs($user)->post(route('user.staking.confirmUnstake'), [
            'position_id'        => $position->id,
            'tx_hash'            => $unstakeHash,
            'reward_earned'      => 4.5,
            'burned_on_unstake'  => 19.8,
            'returned_amount'    => 975.3,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('staking_positions', [
            'id'     => $position->id,
            'status' => 'unstaked',
        ]);
        $this->assertDatabaseHas('staking_transactions', [
            'user_id' => $user->id,
            'type'    => 'unstake_out',
            'tx_hash' => $unstakeHash,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Admin: staking views
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function admin_can_view_staking_index()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('admin.staking.index'));
        $response->assertOk();
    }

    /** @test */
    public function admin_can_view_staking_pools()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('admin.staking.pools'));
        $response->assertOk();
    }

    /** @test */
    public function admin_can_save_a_new_pool()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.staking.savePool'), [
            'name'                => 'Gold 60-Day',
            'pool_id_onchain'     => 1,
            'min_amount'          => 1000,
            'duration_days'       => 60,
            'apy_bps'             => 1000,
            'burn_on_stake_bps'   => 100,
            'burn_on_unstake_bps' => 200,
            'status'              => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staking_pools', [
            'name'        => 'Gold 60-Day',
            'duration_days' => 60,
        ]);
    }

    /** @test */
    public function admin_can_update_an_existing_pool()
    {
        $admin = $this->makeAdmin();
        $pool  = $this->makePool();

        $response = $this->actingAs($admin)->post(route('admin.staking.savePool'), [
            'id'                  => $pool->id,
            'name'                => 'Silver Updated',
            'pool_id_onchain'     => 0,
            'min_amount'          => 600,
            'duration_days'       => 30,
            'apy_bps'             => 600,
            'burn_on_stake_bps'   => 100,
            'burn_on_unstake_bps' => 200,
            'status'              => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staking_pools', [
            'id'         => $pool->id,
            'name'       => 'Silver Updated',
            'min_amount' => 600,
        ]);
    }
}
