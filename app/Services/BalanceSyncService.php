<?php

namespace App\Services;

use App\Model\DepositeTransaction;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Model\WithdrawHistory;
use App\User;
use App\Repository\CustomTokenRepository;
use Illuminate\Support\Facades\Http;
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
    private const HISTORY_PAGE_SIZE = 1000;
    private const HISTORY_MAX_PAGES = 200;

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

            // Import missed incoming and outgoing transfers into their respective tables.
            $syncStats = $this->syncMissingTransactions($primaryWallet, $walletAddress);

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
                        'created_deposits' => $syncStats['created_deposits'],
                        'created_withdrawals' => $syncStats['created_withdrawals'],
                        'credited_amount' => $syncStats['credited_amount'],
                        'debited_amount' => $syncStats['debited_amount'],
                        'imported_deposit_hashes' => $syncStats['imported_deposit_hashes'],
                        'imported_withdraw_hashes' => $syncStats['imported_withdraw_hashes'],
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
                        'created_deposits' => $syncStats['created_deposits'],
                        'created_withdrawals' => $syncStats['created_withdrawals'],
                        'credited_amount' => $syncStats['credited_amount'],
                        'debited_amount' => $syncStats['debited_amount'],
                        'imported_deposit_hashes' => $syncStats['imported_deposit_hashes'],
                        'imported_withdraw_hashes' => $syncStats['imported_withdraw_hashes'],
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
                    'created_deposits' => $syncStats['created_deposits'],
                    'created_withdrawals' => $syncStats['created_withdrawals'],
                    'credited_amount' => $syncStats['credited_amount'],
                    'debited_amount' => $syncStats['debited_amount'],
                    'imported_deposit_hashes' => $syncStats['imported_deposit_hashes'],
                    'imported_withdraw_hashes' => $syncStats['imported_withdraw_hashes'],
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
    private function syncMissingTransactions(Wallet $wallet, string $walletAddress): array
    {
        $createdDeposits = 0;
        $createdWithdrawals = 0;
        $credited = '0';
        $debited = '0';
        $importedDepositHashes = [];
        $importedWithdrawHashes = [];

        try {
            $repo = new CustomTokenRepository();
            $targetAddress = strtolower(trim($walletAddress));
            $rows = $this->fetchAllTokenTransfers($targetAddress);

            // Fallback to legacy recent-block scan if explorer history API returns no rows.
            if (empty($rows)) {
                $events = $repo->getLatestTransactionFromBlock();
                if (($events['success'] ?? false) === true) {
                    $rows = array_map(function ($row) {
                        return [
                            'tx_hash' => (string) ($row->tx_hash ?? ''),
                            'from_address' => (string) ($row->from_address ?? ''),
                            'to_address' => (string) ($row->to_address ?? ''),
                            'amount' => (string) ($row->amount ?? '0'),
                        ];
                    }, $events['data'] ?? []);
                }
            }

            foreach ($rows as $row) {
                $toAddress = strtolower(trim((string) ($row['to_address'] ?? '')));
                $fromAddress = strtolower(trim((string) ($row['from_address'] ?? '')));
                $txHash = strtolower(trim((string) ($row['tx_hash'] ?? '')));
                $amountRaw = (string) ($row['amount'] ?? '0');
                $amount = (float) $amountRaw;

                if ($txHash === '' || $amount <= 0) {
                    continue;
                }

                if ($toAddress === $targetAddress) {
                    $exists = DepositeTransaction::where('receiver_wallet_id', $wallet->id)
                        ->whereRaw('LOWER(transaction_id) = ?', [$txHash])
                        ->exists();

                    if (!$exists) {
                        $result = $repo->checkAddressAndDeposit(
                            $toAddress,
                            $txHash,
                            $amount,
                            (string) ($row['from_address'] ?? '')
                        );

                        if (($result['success'] ?? false) === true) {
                            $createdDeposits++;
                            $credited = bcadd($credited, (string) $amount, 18);
                            $importedDepositHashes[] = $txHash;
                        }
                    }
                }

                if ($fromAddress === $targetAddress) {
                    $exists = WithdrawHistory::where('wallet_id', $wallet->id)
                        ->whereRaw('LOWER(transaction_hash) = ?', [$txHash])
                        ->exists();

                    if (!$exists) {
                        $result = $this->createHistoricalWithdrawal($wallet, $txHash, $toAddress, $amount);
                        if ($result['success'] === true) {
                            $createdWithdrawals++;
                            $debited = bcadd($debited, (string) $amount, 18);
                            $importedWithdrawHashes[] = $txHash;
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('BalanceSyncService::syncMissingTransactions failed: ' . $e->getMessage());
        }

        return [
            'created_deposits' => $createdDeposits,
            'created_withdrawals' => $createdWithdrawals,
            'credited_amount' => $credited,
            'debited_amount' => $debited,
            'imported_deposit_hashes' => $importedDepositHashes,
            'imported_withdraw_hashes' => $importedWithdrawHashes,
        ];
    }

    /**
     * Fetch all token transfers for a wallet address using paginated explorer APIs.
     * Primary: Etherscan v2 API. Fallback: BscScan legacy API.
     */
    private function fetchAllTokenTransfers(string $walletAddress): array
    {
        $walletAddress = strtolower(trim($walletAddress));
        if (!preg_match('/^0x[a-f0-9]{40}$/', $walletAddress)) {
            return [];
        }

        $contractAddress = strtolower(trim((string) (settings('contract_address') ?: '')));
        if (!preg_match('/^0x[a-f0-9]{40}$/', $contractAddress)) {
            Log::warning('BalanceSyncService::fetchAllIncomingTokenTransfers missing/invalid contract address');
            return [];
        }

        $apiKey = trim((string) (settings('bscscan_api_key') ?: env('BSCSCAN_API_KEY', '')));
        $chainId = (int) (settings('chain_id') ?: settings('presale_chain_id') ?: config('blockchain.presale_chain_id', 56));
        if ($chainId <= 0) {
            $chainId = 56;
        }

        $all = [];
        $seen = [];

        // Try Etherscan v2 first.
        $all = $this->fetchTransfersFromEtherscanV2($walletAddress, $contractAddress, $apiKey, $chainId);

        // Fallback to legacy BscScan API if v2 returned nothing.
        if (empty($all)) {
            $all = $this->fetchTransfersFromBscScanLegacy($walletAddress, $contractAddress, $apiKey);
        }

        $normalized = [];
        foreach ($all as $row) {
            $to = strtolower(trim((string) ($row['to'] ?? '')));
            $hash = strtolower(trim((string) ($row['hash'] ?? '')));
            if ($to !== $walletAddress || $hash === '') {
                continue;
            }

            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            $value = (string) ($row['value'] ?? '0');
            $decimals = (int) ($row['tokenDecimal'] ?? 18);
            $amount = $this->normalizeTokenAmount($value, $decimals);
            if ($amount === '0') {
                continue;
            }

            $normalized[] = [
                'tx_hash' => $hash,
                'from_address' => (string) ($row['from'] ?? ''),
                'to_address' => $to,
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    private function createHistoricalWithdrawal(Wallet $wallet, string $txHash, string $toAddress, float $amount): array
    {
        try {
            $receiverHistory = WalletAddressHistory::whereRaw('LOWER(address) = ?', [strtolower($toAddress)])
                ->where('coin_type', self::DEFAULT_COIN_TYPE_CONST)
                ->orderBy('id', 'desc')
                ->first();

            $receiverWalletId = (int) ($receiverHistory->wallet_id ?? 0);
            $addressType = $receiverWalletId > 0 ? ADDRESS_TYPE_INTERNAL : ADDRESS_TYPE_EXTERNAL;

            WithdrawHistory::create([
                'receiver_wallet_id' => $receiverWalletId,
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'confirmations' => 1,
                'status' => STATUS_SUCCESS,
                'address' => $toAddress,
                'address_type' => $addressType,
                'amount' => $amount,
                'fees' => 0,
                'transaction_hash' => $txHash,
                'message' => 'Historical on-chain withdrawal sync',
                'btc' => (string) $amount,
                'doller' => bcmul((string) $amount, (string) settings('coin_price'), 8),
                'coin_type' => self::DEFAULT_COIN_TYPE_CONST,
                'used_gas' => 0,
            ]);

            // Bring system balance in line with missing outgoing transfer.
            $wallet->decrement('balance', $amount);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('BalanceSyncService::createHistoricalWithdrawal failed: ' . $e->getMessage(), [
                'wallet_id' => $wallet->id,
                'tx_hash' => $txHash,
                'to_address' => $toAddress,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function fetchTransfersFromEtherscanV2(string $walletAddress, string $contractAddress, string $apiKey, int $chainId): array
    {
        $rows = [];

        for ($page = 1; $page <= self::HISTORY_MAX_PAGES; $page++) {
            try {
                $query = [
                    'chainid' => $chainId,
                    'module' => 'account',
                    'action' => 'tokentx',
                    'address' => $walletAddress,
                    'contractaddress' => $contractAddress,
                    'page' => $page,
                    'offset' => self::HISTORY_PAGE_SIZE,
                    'sort' => 'asc',
                ];
                if ($apiKey !== '') {
                    $query['apikey'] = $apiKey;
                }

                $response = Http::timeout(20)
                    ->get('https://api.etherscan.io/v2/api', $query)
                    ->json();

                $status = (string) ($response['status'] ?? '0');
                $result = $response['result'] ?? [];
                if ($status !== '1' || !is_array($result) || empty($result)) {
                    break;
                }

                $rows = array_merge($rows, $result);

                if (count($result) < self::HISTORY_PAGE_SIZE) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('BalanceSyncService::fetchTransfersFromEtherscanV2 failed: ' . $e->getMessage());
                break;
            }
        }

        return $rows;
    }

    private function fetchTransfersFromBscScanLegacy(string $walletAddress, string $contractAddress, string $apiKey): array
    {
        $rows = [];

        for ($page = 1; $page <= self::HISTORY_MAX_PAGES; $page++) {
            try {
                $query = [
                    'module' => 'account',
                    'action' => 'tokentx',
                    'address' => $walletAddress,
                    'contractaddress' => $contractAddress,
                    'page' => $page,
                    'offset' => self::HISTORY_PAGE_SIZE,
                    'sort' => 'asc',
                ];
                if ($apiKey !== '') {
                    $query['apikey'] = $apiKey;
                }

                $response = Http::timeout(20)
                    ->get('https://api.bscscan.com/api', $query)
                    ->json();

                $status = (string) ($response['status'] ?? '0');
                $result = $response['result'] ?? [];
                if ($status !== '1' || !is_array($result) || empty($result)) {
                    break;
                }

                $rows = array_merge($rows, $result);

                if (count($result) < self::HISTORY_PAGE_SIZE) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('BalanceSyncService::fetchTransfersFromBscScanLegacy failed: ' . $e->getMessage());
                break;
            }
        }

        return $rows;
    }

    private function normalizeTokenAmount(string $rawValue, int $decimals): string
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '' || !preg_match('/^\d+$/', $rawValue)) {
            return '0';
        }

        $decimals = max(0, min(30, $decimals));
        if ($decimals === 0) {
            return ltrim($rawValue, '0') === '' ? '0' : $rawValue;
        }

        $divisor = '1' . str_repeat('0', $decimals);
        $amount = bcdiv($rawValue, $divisor, 18);
        return $amount !== '' ? $amount : '0';
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
