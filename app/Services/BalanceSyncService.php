<?php

namespace App\Services;

use App\Model\DepositeTransaction;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\User;
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

            // Query on-chain balance
            $onChainBalance = $this->blockchain->getObxBalance($walletAddress);
            $onChainBalanceDec = (string) $onChainBalance;

            // Get system balance
            $systemBalance = (string) $primaryWallet->balance;

            // Log the reconciliation
            Log::info('BalanceSyncService: Reconciling user ' . $userId, [
                'wallet_id' => $primaryWallet->id,
                'address' => $walletAddress,
                'on_chain_balance' => $onChainBalanceDec,
                'system_balance' => $systemBalance,
            ]);

            // If balances match, no action needed
            if (bccomp($onChainBalanceDec, $systemBalance, 18) === 0) {
                return [
                    'success' => true,
                    'message' => 'Balances already synchronized',
                    'data' => [
                        'wallet_id' => $primaryWallet->id,
                        'address' => $walletAddress,
                        'on_chain_balance' => $onChainBalanceDec,
                        'system_balance' => $systemBalance,
                        'action' => 'none',
                    ]
                ];
            }

            // If on-chain balance is HIGHER, reconcile by creating a reconciliation deposit
            if (bccomp($onChainBalanceDec, $systemBalance, 18) > 0) {
                $difference = bcsub($onChainBalanceDec, $systemBalance, 18);
                return $this->reconcileDepositDifference($primaryWallet, $walletAddress, $difference, $onChainBalanceDec, $systemBalance);
            }

            // If on-chain balance is LOWER, this indicates a withdrawal issue or burn
            if (bccomp($onChainBalanceDec, $systemBalance, 18) < 0) {
                $difference = bcsub($systemBalance, $onChainBalanceDec, 18);
                return [
                    'success' => false,
                    'message' => 'System balance exceeds on-chain balance. Possible withdrawal or burn issue.',
                    'data' => [
                        'wallet_id' => $primaryWallet->id,
                        'address' => $walletAddress,
                        'on_chain_balance' => $onChainBalanceDec,
                        'system_balance' => $systemBalance,
                        'discrepancy' => $difference,
                        'action' => 'requires_investigation',
                    ]
                ];
            }

            return ['success' => false, 'message' => 'Unknown reconciliation state'];

        } catch (\Exception $e) {
            Log::error('BalanceSyncService::reconcileUserBalance failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reconcile by creating a deposit record for the missing balance.
     * This brings the system balance in sync with the on-chain balance.
     * 
     * @param Wallet $wallet
     * @param string $address
     * @param string $difference
     * @param string $onChainBalance
     * @param string $systemBalance
     * @return array
     */
    private function reconcileDepositDifference(Wallet $wallet, string $address, string $difference, string $onChainBalance, string $systemBalance): array
    {
        try {
            DB::beginTransaction();

            // Create a reconciliation deposit record
            $reconciliationDeposit = DepositeTransaction::create([
                'address' => strtolower($address),
                'from_address' => 'RECONCILIATION',  // Special marker for system-created reconciliation
                'receiver_wallet_id' => $wallet->id,
                'address_type' => ADDRESS_TYPE_EXTERNAL,
                'type' => self::DEFAULT_COIN_TYPE_CONST,
                'amount' => floatval($difference),
                'doller' => bcmul($difference, (string) settings('coin_price'), 8),
                'transaction_id' => 'RECONCILIATION-' . time(),
                'coin_id' => $wallet->coin_id,
                'status' => STATUS_ACTIVE,  // Mark as active immediately
                'unique_code' => uniqid() . date('Y-m-d-H-i-s') . time(),
            ]);

            // Update wallet balance to match on-chain
            $wallet->update(['balance' => floatval($onChainBalance)]);

            DB::commit();

            Log::info('BalanceSyncService: Reconciliation successful', [
                'wallet_id' => $wallet->id,
                'reconciliation_deposit_id' => $reconciliationDeposit->id,
                'difference' => $difference,
                'new_system_balance' => $onChainBalance,
            ]);

            return [
                'success' => true,
                'message' => 'Balance reconciliation completed successfully',
                'data' => [
                    'wallet_id' => $wallet->id,
                    'address' => $address,
                    'on_chain_balance' => $onChainBalance,
                    'system_balance_before' => $systemBalance,
                    'system_balance_after' => $onChainBalance,
                    'reconciliation_deposit_id' => $reconciliationDeposit->id,
                    'difference_credited' => $difference,
                    'action' => 'reconciliation_applied',
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BalanceSyncService::reconcileDepositDifference failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
