<?php

namespace App\Services;

use App\Model\DepositeTransaction;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\User;
use App\Repository\CustomTokenRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BalanceSyncService
 * 
 * Reconciles on-chain OBX balances with system database balances.
 * Detects missed deposits that weren't caught by the polling system.
 * 
 * Usage:
 *   $sync = new BalanceSyncService();
 *   $result = $sync->reconcileUserBalance($userId);
 */
class BalanceSyncService
{
    private BlockchainService $blockchain;
    private const DEFAULT_COIN_TYPE_CONST = DEFAULT_COIN_TYPE;

    public function __construct()
    {
        $this->blockchain = app(BlockchainService::class);
    }

    /**
     * Reconcile a specific user's OBX wallet balance.
     * Compares on-chain balance with system balance and reconciles differences.
     * 
     * @param int $userId
     * @return array ['success' => bool, 'message' => string, 'data' => [...]]
     */
    public function reconcileUserBalance(int $userId): array
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }

            // Get user's primary default wallet
            $primaryWallet = $this->getDefaultWallet($user);
            if (!$primaryWallet) {
                return ['success' => false, 'message' => 'User has no default OBX wallet configured'];
            }

            // Get the user's default wallet address
            $walletAddress = $this->getDefaultWalletAddress($primaryWallet);
            if (!$walletAddress || !preg_match('/^0x[a-f0-9]{40}$/i', $walletAddress)) {
                return ['success' => false, 'message' => 'User wallet address not properly configured'];
            }

            // Query on-chain and system balances first
            $onChainBalanceDec = (string) $this->blockchain->getObxBalance($walletAddress);
            $systemBalanceBefore = (string) $primaryWallet->balance;

            Log::info('BalanceSyncService: Reconciling user ' . $userId, [
                'wallet_id' => $primaryWallet->id,
                'address' => $walletAddress,
                'on_chain_balance' => $onChainBalanceDec,
                'system_balance_before' => $systemBalanceBefore,
            ]);

            // If already in sync, no action needed.
            if (bccomp($onChainBalanceDec, $systemBalanceBefore, 18) === 0) {
                return [
                    'success' => true,
                    'message' => 'Balances already synchronized',
                    'data' => [
                        'wallet_id' => $primaryWallet->id,
                        'address' => $walletAddress,
                        'on_chain_balance' => $onChainBalanceDec,
                        'system_balance' => $systemBalanceBefore,
                        'action' => 'none',
                    ]
                ];
            }

            // Import missed deposits transaction-by-transaction into deposite_transactions.
            $syncStats = $this->syncMissingDepositTransactions($primaryWallet, $walletAddress);

            // Reload wallet balance after per-transaction sync.
            $primaryWallet->refresh();
            $systemBalanceAfter = (string) $primaryWallet->balance;

            // If still lower than on-chain, report remaining mismatch without creating lump-sum rows.
            if (bccomp($onChainBalanceDec, $systemBalanceAfter, 18) > 0) {
                $remaining = bcsub($onChainBalanceDec, $systemBalanceAfter, 18);
                return [
                    'success' => true,
                    'message' => 'Per-transaction reconciliation completed with remaining unmatched on-chain balance.',
                    'data' => [
                        'wallet_id' => $primaryWallet->id,
                        'address' => $walletAddress,
                        'on_chain_balance' => $onChainBalanceDec,
                        'system_balance_before' => $systemBalanceBefore,
                        'system_balance_after' => $systemBalanceAfter,
                        'created_transactions' => $syncStats['created_transactions'],
                        'credited_amount' => $syncStats['credited_amount'],
                        'remaining_unmatched' => $remaining,
                        'action' => 'partial_transaction_sync',
                    ]
                ];
            }

            // If system exceeds on-chain after sync, flag for investigation (withdrawals/burn/manual edits).
            if (bccomp($onChainBalanceDec, $systemBalanceAfter, 18) < 0) {
                $difference = bcsub($systemBalanceAfter, $onChainBalanceDec, 18);
                return [
                    'success' => false,
                    'message' => 'System balance exceeds on-chain balance. Possible withdrawal or burn issue.',
                    'data' => [
                        'wallet_id' => $primaryWallet->id,
                        'address' => $walletAddress,
                        'on_chain_balance' => $onChainBalanceDec,
                        'system_balance_before' => $systemBalanceBefore,
                        'system_balance_after' => $systemBalanceAfter,
                        'created_transactions' => $syncStats['created_transactions'],
                        'credited_amount' => $syncStats['credited_amount'],
                        'discrepancy' => $difference,
                        'action' => 'requires_investigation',
                    ]
                ];
            }

            return [
                'success' => true,
                'message' => 'Per-transaction reconciliation completed successfully',
                'data' => [
                    'wallet_id' => $primaryWallet->id,
                    'address' => $walletAddress,
                    'on_chain_balance' => $onChainBalanceDec,
                    'system_balance_before' => $systemBalanceBefore,
                    'system_balance_after' => $systemBalanceAfter,
                    'created_transactions' => $syncStats['created_transactions'],
                    'credited_amount' => $syncStats['credited_amount'],
                    'action' => 'transaction_sync_applied',
                ]
            ];

        } catch (\Exception $e) {
            Log::error('BalanceSyncService::reconcileUserBalance failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pull token transfer events and store each missing incoming transaction
     * as a distinct deposite_transactions row (no lump-sum reconciliation row).
     */
    private function syncMissingDepositTransactions(Wallet $wallet, string $walletAddress): array
    {
        $created = 0;
        $credited = '0';

        try {
            $repo = new CustomTokenRepository();
            $events = $repo->getLatestTransactionFromBlock();

            if (($events['success'] ?? false) !== true) {
                return ['created_transactions' => 0, 'credited_amount' => '0'];
            }

            $targetAddress = strtolower(trim($walletAddress));
            $rows = $events['data'] ?? [];

            foreach ($rows as $row) {
                $toAddress = strtolower(trim((string) ($row->to_address ?? '')));
                $txHash = strtolower(trim((string) ($row->tx_hash ?? '')));

                if ($toAddress === '' || $txHash === '' || $toAddress !== $targetAddress) {
                    continue;
                }

                $exists = DepositeTransaction::where('receiver_wallet_id', $wallet->id)
                    ->where('transaction_id', $txHash)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $amountRaw = (string) ($row->amount ?? '0');
                $amount = (float) $amountRaw;
                if ($amount <= 0) {
                    continue;
                }

                $result = $repo->checkAddressAndDeposit(
                    $toAddress,
                    $txHash,
                    $amount,
                    (string) ($row->from_address ?? '')
                );

                if (($result['success'] ?? false) === true) {
                    $created++;
                    $credited = bcadd($credited, (string) $amount, 18);
                }
            }

        } catch (\Exception $e) {
            Log::error('BalanceSyncService::syncMissingDepositTransactions failed: ' . $e->getMessage());
        }

        return [
            'created_transactions' => $created,
            'credited_amount' => $credited,
        ];
    }

    /**
     * Get user's default (primary) OBX wallet.
     * 
     * @param User $user
     * @return Wallet|null
     */
    private function getDefaultWallet(User $user): ?Wallet
    {
        return get_primary_wallet((int) $user->id, self::DEFAULT_COIN_TYPE_CONST)
            ?? $user->wallets()->where('coin_type', self::DEFAULT_COIN_TYPE_CONST)->first();
    }

    /**
     * Get the on-chain address for a wallet.
     * Resolves from WalletAddressHistory (most recent).
     * 
     * @param Wallet $wallet
     * @return string|null
     */
    private function getDefaultWalletAddress(Wallet $wallet): ?string
    {
        $address = WalletAddressHistory::where('wallet_id', $wallet->id)
            ->where('coin_type', self::DEFAULT_COIN_TYPE_CONST)
            ->orderBy('id', 'desc')
            ->value('address');

        return $address ? strtolower(trim((string) $address)) : null;
    }

    /**
     * Reconcile all users' balances.
     * Useful for batch reconciliation via cron job.
     * 
     * @return array ['total_users' => int, 'reconciled' => int, 'errors' => int, 'details' => [...]]
     */
    public function reconcileAllUsersBalances(): array
    {
        try {
            $users = User::all();
            $total = count($users);
            $reconciled = 0;
            $errors = 0;
            $details = [];

            foreach ($users as $user) {
                $result = $this->reconcileUserBalance($user->id);
                if ($result['success']) {
                    $reconciled++;
                    $details[] = [
                        'user_id' => $user->id,
                        'status' => 'success',
                        'message' => $result['message'],
                    ];
                } else {
                    $errors++;
                    $details[] = [
                        'user_id' => $user->id,
                        'status' => 'error',
                        'message' => $result['message'],
                    ];
                }
            }

            return [
                'total_users' => $total,
                'reconciled' => $reconciled,
                'errors' => $errors,
                'details' => $details,
            ];

        } catch (\Exception $e) {
            Log::error('BalanceSyncService::reconcileAllUsersBalances failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
