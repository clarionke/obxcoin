<?php

namespace App\Repository;

use App\Model\StakingPool;
use App\Model\StakingPosition;
use App\Model\StakingTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StakingRepository
{
    /**
     * Confirm a stake transaction received from the frontend after the
     * on-chain tx was signed and mined.
     *
     * Called by: user/StakingController@confirmStake (POST)
     *
     * @param  array $data {
     *   pool_id          int   - staking_pools.id
     *   wallet_address   string
     *   gross_amount     string/float  - gross OBX staked
     *   tx_hash          string - 0x...
     *   contract_stake_idx int|null
     *   locked_at        string ISO-8601
     *   lock_until       string ISO-8601
     * }
     * @param  int   $userId
     * @return array {success, message, position?}
     */
    public function saveStake(array $data, int $userId): array
    {
        DB::beginTransaction();
        try {
            // Prevent duplicate tx
            if (StakingPosition::where('tx_hash_stake', $data['tx_hash'])->exists()) {
                DB::rollBack();
                return ['success' => false, 'message' => __('Transaction already recorded.')];
            }

            $pool = StakingPool::find($data['pool_id']);
            if (!$pool) {
                DB::rollBack();
                return ['success' => false, 'message' => __('Invalid staking pool.')];
            }

            // Calculate amounts (mirrors contract logic)
            $gross    = (float) $data['gross_amount'];
            $burnBps  = (int) ($data['burn_on_stake_bps'] ?? $pool->burn_on_stake_bps);
            $burned   = round($gross * $burnBps / 10000, 8);
            $net      = round($gross - $burned, 8);

            $lockedAt   = isset($data['locked_at'])  ? Carbon::parse($data['locked_at'])  : now();
            $lockUntil  = isset($data['lock_until'])  ? Carbon::parse($data['lock_until'])
                        : $lockedAt->copy()->addDays($pool->duration_days);

            $position = StakingPosition::create([
                'user_id'            => $userId,
                'pool_id'            => $pool->id,
                'wallet_address'     => strtolower($data['wallet_address']),
                'contract_stake_idx' => $data['contract_stake_idx'] ?? null,
                'gross_amount'       => $gross,
                'burned_on_stake'    => $burned,
                'net_amount'         => $net,
                'status'             => 'active',
                'tx_hash_stake'      => $data['tx_hash'],
                'staked_at'          => $lockedAt,
                'lock_until'         => $lockUntil,
            ]);

            // Audit trail: stake in
            StakingTransaction::create([
                'user_id'     => $userId,
                'position_id' => $position->id,
                'type'        => StakingTransaction::TYPE_STAKE_IN,
                'amount'      => $gross,
                'tx_hash'     => $data['tx_hash'],
                'status'      => 'confirmed',
                'note'        => "Staked into pool: {$pool->name}",
            ]);

            // Audit trail: burn on stake
            if ($burned > 0) {
                StakingTransaction::create([
                    'user_id'     => $userId,
                    'position_id' => $position->id,
                    'type'        => StakingTransaction::TYPE_BURN_STAKE,
                    'amount'      => $burned,
                    'tx_hash'     => $data['tx_hash'],
                    'status'      => 'confirmed',
                    'note'        => "Burn on stake ({$burnBps} bps)",
                ]);
            }

            DB::commit();
            return ['success' => true, 'message' => __('Stake recorded successfully.'), 'position' => $position];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('StakingRepository::saveStake ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Confirm an unstake transaction from the frontend.
     *
     * @param  array $data {
     *   position_id      int
     *   tx_hash          string
     *   reward_earned    float
     *   burned_on_unstake float
     *   returned_amount  float
     * }
     * @param  int   $userId
     * @return array {success, message}
     */
    public function saveUnstake(array $data, int $userId): array
    {
        DB::beginTransaction();
        try {
            $position = StakingPosition::where('id', $data['position_id'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if (!$position) {
                DB::rollBack();
                return ['success' => false, 'message' => __('Staking position not found or already unstaked.')];
            }

            if (StakingPosition::where('tx_hash_unstake', $data['tx_hash'])->exists()) {
                DB::rollBack();
                return ['success' => false, 'message' => __('Transaction already recorded.')];
            }

            $reward   = (float) ($data['reward_earned']    ?? 0);
            $burned   = (float) ($data['burned_on_unstake'] ?? 0);
            $returned = (float) ($data['returned_amount']  ?? ($position->net_amount - $burned));

            $position->update([
                'status'           => 'unstaked',
                'tx_hash_unstake'  => $data['tx_hash'],
                'reward_earned'    => $reward,
                'burned_on_unstake'=> $burned,
                'returned_amount'  => $returned,
                'unstaked_at'      => now(),
            ]);

            // Audit trail: unstake out (principal return)
            StakingTransaction::create([
                'user_id'     => $userId,
                'position_id' => $position->id,
                'type'        => StakingTransaction::TYPE_UNSTAKE_OUT,
                'amount'      => $returned,
                'tx_hash'     => $data['tx_hash'],
                'status'      => 'confirmed',
                'note'        => 'Principal returned on unstake',
            ]);

            // Audit trail: burn on unstake
            if ($burned > 0) {
                $pool = $position->pool;
                StakingTransaction::create([
                    'user_id'     => $userId,
                    'position_id' => $position->id,
                    'type'        => StakingTransaction::TYPE_BURN_UNSTAKE,
                    'amount'      => $burned,
                    'tx_hash'     => $data['tx_hash'],
                    'status'      => 'confirmed',
                    'note'        => 'Burn on unstake (' . ($pool ? $pool->burn_on_unstake_bps : '?') . ' bps)',
                ]);
            }

            // Audit trail: reward
            if ($reward > 0) {
                StakingTransaction::create([
                    'user_id'     => $userId,
                    'position_id' => $position->id,
                    'type'        => StakingTransaction::TYPE_REWARD,
                    'amount'      => $reward,
                    'tx_hash'     => $data['tx_hash'],
                    'status'      => 'confirmed',
                    'note'        => 'Staking reward paid',
                ]);
            }

            DB::commit();
            return ['success' => true, 'message' => __('Unstake recorded successfully.')];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('StakingRepository::saveUnstake ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Save a new or updated staking pool from admin.
     */
    public function savePool(array $data): array
    {
        DB::beginTransaction();
        try {
            $fillable = ['name', 'min_amount', 'duration_days', 'apy_bps',
                         'burn_on_stake_bps', 'burn_on_unstake_bps', 'description',
                         'status', 'pool_id_onchain'];

            $values = array_intersect_key($data, array_flip($fillable));

            if (!empty($data['edit_id'])) {
                StakingPool::where('id', $data['edit_id'])->update($values);
                DB::commit();
                return ['success' => true, 'message' => __('Pool updated.')];
            }
            StakingPool::create($values);
            DB::commit();
            return ['success' => true, 'message' => __('Pool created.')];
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
