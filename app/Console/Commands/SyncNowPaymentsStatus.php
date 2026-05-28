<?php

namespace App\Console\Commands;

use App\Model\AirdropUnlock;
use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\User;
use App\Repository\AffiliateRepository;
use App\Repository\WalletRepository;
use App\Services\BlockchainService;
use App\Services\NowPaymentsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncNowPaymentsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nowpayments:sync-status {--limit=200 : Maximum orders to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pending NOWPayments buy orders and airdrop withdrawal payments.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $items = BuyCoinHistory::with('user')
            ->where('type', NOWPAYMENTS)
            ->whereNotNull('nowpayments_payment_id')
            ->where('status', '!=', STATUS_SUCCESS)
            ->where('status', '!=', STATUS_REJECTED)
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        $airdropItems = AirdropUnlock::whereNotNull('nowpayments_payment_id')
            ->where('status', '!=', 'confirmed')
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        if ($items->isEmpty() && $airdropItems->isEmpty()) {
            $this->info('No pending NOWPayments orders to sync.');
            return self::SUCCESS;
        }

        $nowPayments = app(NowPaymentsService::class);
        $processed = 0;
        $finalized = 0;
        $rejected = 0;
        $stillPending = 0;
        $failed = 0;

        foreach ($items as $purchase) {
            $processed++;

            try {
                $npResponse = $nowPayments->getPaymentStatus((string) $purchase->nowpayments_payment_id);
                $paymentStatus = strtolower((string) ($npResponse['payment_status'] ?? ''));
                $onChainHash = $npResponse['payin_hash'] ?? $npResponse['tx_hash'] ?? $npResponse['withdrawal_hash'] ?? null;

                if (!empty($onChainHash) && is_string($onChainHash) && preg_match('/^0x[a-fA-F0-9]{64}$/', $onChainHash)) {
                    if (empty($purchase->tx_hash)) {
                        $purchase->update(['tx_hash' => $onChainHash]);
                        $purchase->refresh();
                    }
                }

                if (in_array($paymentStatus, ['failed', 'expired', 'refunded'], true)) {
                    $purchase->update(['status' => STATUS_REJECTED]);
                    $rejected++;
                    continue;
                }

                if ($paymentStatus !== 'finished') {
                    $stillPending++;
                    continue;
                }

                $this->finalizeFinishedOrder($purchase);
                $finalized++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('nowpayments:sync-status failed for order', [
                    'buy_coin_history_id' => $purchase->id,
                    'payment_id' => $purchase->nowpayments_payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $airdropProcessed = 0;
        $airdropFinalized = 0;
        $airdropRejected = 0;
        $airdropPending = 0;
        $airdropFailed = 0;

        foreach ($airdropItems as $unlock) {
            $airdropProcessed++;

            try {
                $npResponse = $nowPayments->getPaymentStatus((string) $unlock->nowpayments_payment_id);
                $paymentStatus = strtolower((string) ($npResponse['payment_status'] ?? ''));
                $onChainHash = $npResponse['payin_hash'] ?? $npResponse['tx_hash'] ?? $npResponse['withdrawal_hash'] ?? null;

                $updates = [
                    'nowpayments_payment_status' => $paymentStatus !== ''
                        ? $paymentStatus
                        : ($unlock->nowpayments_payment_status ?: 'waiting'),
                ];

                if (!empty($npResponse['payment_id'])) {
                    $updates['nowpayments_payment_id'] = (string) $npResponse['payment_id'];
                }
                if (!empty($npResponse['pay_address'])) {
                    $updates['nowpayments_pay_address'] = (string) $npResponse['pay_address'];
                }
                if (isset($npResponse['pay_amount'])) {
                    $updates['nowpayments_pay_amount'] = (string) $npResponse['pay_amount'];
                }
                if (!empty($npResponse['pay_currency'])) {
                    $updates['nowpayments_pay_currency'] = strtolower((string) $npResponse['pay_currency']);
                }
                if (!empty($npResponse['order_id'])) {
                    $updates['nowpayments_order_id'] = (string) $npResponse['order_id'];
                }
                if (!empty($onChainHash) && is_string($onChainHash) && preg_match('/^0x[a-fA-F0-9]{64}$/', $onChainHash)) {
                    if (empty($unlock->tx_hash)) {
                        $updates['tx_hash'] = strtolower($onChainHash);
                    }
                }

                $unlock->update($updates);
                $unlock->refresh();

                if (in_array($paymentStatus, ['failed', 'expired', 'refunded'], true)) {
                    $airdropRejected++;
                    continue;
                }

                if ($paymentStatus !== 'finished') {
                    $airdropPending++;
                    continue;
                }

                $this->finalizeFinishedAirdropUnlock($unlock, $onChainHash);
                $airdropFinalized++;
            } catch (\Throwable $e) {
                $airdropFailed++;
                Log::warning('nowpayments:sync-status failed for airdrop unlock', [
                    'airdrop_unlock_id' => $unlock->id,
                    'payment_id' => $unlock->nowpayments_payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(
            "NOWPayments sync done. "
            . "buy{processed={$processed}, finalized={$finalized}, rejected={$rejected}, pending={$stillPending}, failed={$failed}} "
            . "airdrop{processed={$airdropProcessed}, finalized={$airdropFinalized}, rejected={$airdropRejected}, pending={$airdropPending}, failed={$airdropFailed}}"
        );

        return self::SUCCESS;
    }

    private function finalizeFinishedOrder(BuyCoinHistory $purchase): void
    {
        $wasAlreadyCredited = ((int) $purchase->status === STATUS_SUCCESS);

        $isDelivered = (($purchase->obx_delivery_status ?? 'pending') === 'success');
        if (!$isDelivered) {
            $targetWallet = $this->resolveTargetWallet($purchase);

            if (!$targetWallet) {
                $purchase->update([
                    'obx_delivery_status' => 'failed',
                    'obx_delivery_error' => 'No valid EVM wallet configured for delivery',
                ]);
                return;
            }

            $blockchain = app(BlockchainService::class);
            $beforeBalance = $this->safeGetObxBalance($blockchain, $targetWallet);
            $tx = $blockchain->transferObxOnChain($targetWallet, (string) $purchase->requested_amount);

            if (!$tx || empty($tx['txHash'])) {
                $err = $blockchain->getLastSignerError() ?: 'OBX delivery failed';
                $purchase->update([
                    'obx_delivery_status' => 'failed',
                    'obx_delivery_error' => mb_substr($err, 0, 500),
                ]);
                return;
            }

            $afterBalance = $this->safeGetObxBalance($blockchain, $targetWallet);
            $deliveredAmount = '0';
            if (is_string($beforeBalance) && is_string($afterBalance) && bccomp($afterBalance, $beforeBalance, 18) >= 0) {
                $deliveredAmount = bcsub($afterBalance, $beforeBalance, 18);
            }
            if (bccomp($deliveredAmount, '0', 18) <= 0) {
                $deliveredAmount = $blockchain->getObxReceivedAmountFromTx($tx['txHash'], $targetWallet);
            }

            $updates = [
                'obx_delivery_status' => 'success',
                'obx_delivery_tx_hash' => $tx['txHash'],
                'obx_delivery_error' => null,
            ];
            if (empty($purchase->tx_hash)) {
                $updates['tx_hash'] = $tx['txHash'];
            }
            $purchase->update($updates);
            $purchase->refresh();
        }

        DB::transaction(function () use ($purchase) {
            $locked = BuyCoinHistory::where('id', $purchase->id)->lockForUpdate()->first();
            if (!$locked || (int) $locked->status === STATUS_SUCCESS) {
                return;
            }

            $locked->update(['status' => STATUS_SUCCESS]);

            $wallet = $this->resolveOrCreatePrimaryObxWallet((int) $locked->user_id);
            if ($wallet) {
                $wallet->increment('balance', $this->resolveDeliveredAmountForCredit($locked));
            }
        });

        if (!$wasAlreadyCredited && !empty($purchase->phase_id)) {
            try {
                app(AffiliateRepository::class)->storeAffiliationHistoryForBuyCoin($purchase->fresh());
            } catch (\Throwable $e) {
                Log::warning('nowpayments:sync-status buy referral distribution failed', [
                    'buy_id' => $purchase->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function finalizeFinishedAirdropUnlock(AirdropUnlock $unlock, ?string $onChainHash = null): void
    {
        DB::transaction(function () use ($unlock, $onChainHash) {
            $locked = AirdropUnlock::where('id', $unlock->id)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }

            if ($locked->status === 'confirmed') {
                if ($locked->nowpayments_payment_status !== 'finished') {
                    $locked->update(['nowpayments_payment_status' => 'finished']);
                }
                return;
            }

            $amount = (string) ($locked->obx_released ?? '0');
            if (!preg_match('/^\d+(\.\d+)?$/', $amount) || bccomp($amount, '0', 18) <= 0) {
                Log::warning('nowpayments:sync-status invalid airdrop amount', [
                    'airdrop_unlock_id' => $locked->id,
                    'obx_released' => $locked->obx_released,
                ]);
                $locked->update(['nowpayments_payment_status' => 'finished']);
                return;
            }

            $targetWallet = $this->resolveAirdropTargetWallet((int) $locked->user_id);
            if (!$targetWallet) {
                Log::warning('nowpayments:sync-status could not resolve EVM wallet for airdrop unlock', [
                    'airdrop_unlock_id' => $locked->id,
                    'user_id' => $locked->user_id,
                ]);
                $locked->update(['nowpayments_payment_status' => 'delivery_failed']);
                return;
            }

            $blockchain = app(BlockchainService::class);
            $tx = $blockchain->transferObxOnChain($targetWallet, $amount);
            if (!$tx || empty($tx['txHash'])) {
                $error = $blockchain->getLastSignerError() ?: 'OBX on-chain transfer failed';
                Log::warning('nowpayments:sync-status airdrop on-chain transfer failed', [
                    'airdrop_unlock_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'wallet' => $targetWallet,
                    'error' => $error,
                ]);
                $locked->update(['nowpayments_payment_status' => 'delivery_failed']);
                return;
            }

            $updates = [
                'status' => 'confirmed',
                'unlocked_at' => now(),
                'nowpayments_payment_status' => 'finished',
                'tx_hash' => strtolower((string) $tx['txHash']),
            ];

            if (empty($updates['tx_hash']) && !empty($onChainHash) && preg_match('/^0x[a-fA-F0-9]{64}$/', $onChainHash)) {
                $updates['tx_hash'] = strtolower((string) $onChainHash);
            }

            $locked->update($updates);
        });
    }

    private function resolveAirdropTargetWallet(int $userId): ?string
    {
        $user = User::find($userId);
        $directWallet = strtolower(trim((string) ($user->bsc_wallet ?? '')));
        if (preg_match('/^0x[a-f0-9]{40}$/', $directWallet)) {
            return $directWallet;
        }

        $historyAddress = '';

        $primaryWallet = get_primary_wallet($userId, DEFAULT_COIN_TYPE);
        if ($primaryWallet) {
            $historyAddress = (string) WalletAddressHistory::where('wallet_id', (int) $primaryWallet->id)
                ->orderByDesc('id')
                ->value('address');

            if (!preg_match('/^0x[a-f0-9]{40}$/', strtolower(trim($historyAddress)))) {
                try {
                    app(WalletRepository::class)->generateTokenAddress((int) $primaryWallet->id);
                    $historyAddress = (string) WalletAddressHistory::where('wallet_id', (int) $primaryWallet->id)
                        ->orderByDesc('id')
                        ->value('address');
                } catch (\Throwable $e) {
                    Log::warning('nowpayments:sync-status failed to auto-generate airdrop delivery wallet', [
                        'user_id' => $userId,
                        'wallet_id' => (int) $primaryWallet->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $candidates = [strtolower(trim((string) $historyAddress))];

        foreach ($candidates as $candidate) {
            if (preg_match('/^0x[a-f0-9]{40}$/', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveTargetWallet(BuyCoinHistory $purchase): ?string
    {
        $purchase->loadMissing('user');

        $historyAddress = '';
        $primaryWallet = get_primary_wallet((int) $purchase->user_id, DEFAULT_COIN_TYPE);
        if ($primaryWallet) {
            $historyAddress = (string) WalletAddressHistory::where('wallet_id', (int) $primaryWallet->id)
                ->orderByDesc('id')
                ->value('address');

            if (!preg_match('/^0x[a-f0-9]{40}$/', strtolower(trim($historyAddress)))) {
                try {
                    app(WalletRepository::class)->generateTokenAddress((int) $primaryWallet->id);
                    $historyAddress = (string) WalletAddressHistory::where('wallet_id', (int) $primaryWallet->id)
                        ->orderByDesc('id')
                        ->value('address');
                } catch (\Throwable $e) {
                    Log::warning('nowpayments:sync-status failed to auto-generate delivery wallet', [
                        'user_id' => (int) $purchase->user_id,
                        'wallet_id' => (int) $primaryWallet->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $candidates = [
            strtolower(trim((string) ($purchase->buyer_wallet ?? ''))),
            strtolower(trim((string) ($purchase->user->bsc_wallet ?? ''))),
            strtolower(trim($historyAddress)),
        ];

        foreach ($candidates as $candidate) {
            if (preg_match('/^0x[a-f0-9]{40}$/', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveOrCreatePrimaryObxWallet(int $userId): ?Wallet
    {
        $wallet = get_primary_wallet($userId, DEFAULT_COIN_TYPE);
        if ($wallet) {
            return $wallet;
        }

        return Wallet::firstOrCreate(
            [
                'user_id' => $userId,
                'coin_type' => DEFAULT_COIN_TYPE,
                'is_primary' => 1,
            ],
            [
                'name' => 'OBX Wallet',
                'balance' => 0,
            ]
        );
    }

    private function resolveDeliveredAmountForCredit(BuyCoinHistory $purchase): float
    {
        $requested = (string) ($purchase->requested_amount ?? '0');
        if (!preg_match('/^\d+(\.\d+)?$/', $requested)) {
            return 0.0;
        }

        $targetWallet = $this->resolveTargetWallet($purchase);
        $txHash = strtolower(trim((string) ($purchase->obx_delivery_tx_hash ?: $purchase->tx_hash ?: '')));

        if ($targetWallet && preg_match('/^0x[a-f0-9]{40}$/', $targetWallet) && preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
            try {
                $amount = app(BlockchainService::class)->getObxReceivedAmountFromTx($txHash, $targetWallet);
                if (preg_match('/^\d+(\.\d+)?$/', (string) $amount) && bccomp((string) $amount, '0', 18) > 0) {
                    return (float) $amount;
                }
            } catch (\Throwable $e) {
                // Fallback below.
            }
        }

        $fallback = bcmul($requested, '0.9995', 8);
        return (float) $fallback;
    }

    private function safeGetObxBalance(BlockchainService $blockchain, string $address): ?string
    {
        try {
            $balance = $blockchain->getObxBalance($address);
            return is_string($balance) ? $balance : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
