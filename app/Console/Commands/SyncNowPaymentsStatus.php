<?php

namespace App\Console\Commands;

use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
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
    protected $description = 'Sync pending NOWPayments orders in background and finalize OBX delivery/credit.';

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

        if ($items->isEmpty()) {
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

        $this->info("NOWPayments sync done. processed={$processed}, finalized={$finalized}, rejected={$rejected}, pending={$stillPending}, failed={$failed}");

        return self::SUCCESS;
    }

    private function finalizeFinishedOrder(BuyCoinHistory $purchase): void
    {
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
            $beforeBalance = $blockchain->getObxBalance($targetWallet);
            $tx = $blockchain->transferObxOnChain($targetWallet, (string) $purchase->requested_amount);

            if (!$tx || empty($tx['txHash'])) {
                $err = $blockchain->getLastSignerError() ?: 'OBX delivery failed';
                $purchase->update([
                    'obx_delivery_status' => 'failed',
                    'obx_delivery_error' => mb_substr($err, 0, 500),
                ]);
                return;
            }

            $afterBalance = $blockchain->getObxBalance($targetWallet);
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
}
