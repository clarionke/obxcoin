<?php

namespace App\Console\Commands;

use App\Model\BuyCoinHistory;
use App\Model\WalletAddressHistory;
use App\Services\BlockchainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedObxDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nowpayments:retry-obx-delivery {--limit=100 : Maximum failed deliveries to retry per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed NOWPayments OBX on-chain deliveries and finalize successful orders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $items = BuyCoinHistory::with('user')
            ->whereNotNull('nowpayments_payment_id')
            ->where('obx_delivery_status', 'failed')
            ->where('status', '!=', STATUS_REJECTED)
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $this->info('No failed OBX deliveries to retry.');
            return self::SUCCESS;
        }

        $blockchain = app(BlockchainService::class);

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($items as $purchase) {
            $processed++;

            $targetWallet = $this->resolveTargetWallet($purchase);
            if (!$targetWallet) {
                $failed++;
                $purchase->update([
                    'obx_delivery_error' => 'Retry skipped: no valid EVM wallet configured',
                ]);
                continue;
            }

            try {
                $tx = $blockchain->transferObxOnChain($targetWallet, (string) $purchase->requested_amount);

                if (!$tx || empty($tx['txHash'])) {
                    $failed++;
                    $err = $blockchain->getLastSignerError() ?: 'OBX delivery retry failed';
                    $purchase->update([
                        'obx_delivery_status' => 'failed',
                        'obx_delivery_error' => mb_substr($err, 0, 500),
                    ]);
                    continue;
                }

                DB::beginTransaction();

                $updates = [
                    'obx_delivery_status' => 'success',
                    'obx_delivery_tx_hash' => $tx['txHash'],
                    'obx_delivery_error' => null,
                ];

                if (empty($purchase->tx_hash)) {
                    $updates['tx_hash'] = $tx['txHash'];
                }

                if ((int) $purchase->status !== STATUS_SUCCESS) {
                    $updates['status'] = STATUS_SUCCESS;
                    $wallet = get_primary_wallet((int) $purchase->user_id, DEFAULT_COIN_TYPE);
                    if ($wallet) {
                        $wallet->increment('balance', (float) $purchase->requested_amount);
                    } else {
                        Log::warning('RetryFailedObxDeliveries: no primary OBX wallet for user', [
                            'user_id' => $purchase->user_id,
                            'buy_coin_history_id' => $purchase->id,
                        ]);
                    }
                }

                $purchase->update($updates);
                DB::commit();
                $succeeded++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $failed++;
                Log::error('RetryFailedObxDeliveries exception', [
                    'buy_coin_history_id' => $purchase->id,
                    'error' => $e->getMessage(),
                ]);

                $purchase->update([
                    'obx_delivery_status' => 'failed',
                    'obx_delivery_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }

        $this->info("OBX delivery retry complete. processed={$processed}, succeeded={$succeeded}, failed={$failed}");

        return self::SUCCESS;
    }

    private function resolveTargetWallet(BuyCoinHistory $purchase): ?string
    {
        $historyAddress = '';
        $primaryWallet = get_primary_wallet((int) $purchase->user_id, DEFAULT_COIN_TYPE);
        if ($primaryWallet) {
            $historyAddress = (string) WalletAddressHistory::where('wallet_id', (int) $primaryWallet->id)
                ->orderByDesc('id')
                ->value('address');
        }

        $candidates = [
            strtolower(trim((string) ($purchase->buyer_wallet ?? ''))),
            strtolower(trim((string) ($purchase->wc_buyer_address ?? ''))),
            strtolower(trim((string) (($purchase->user->bsc_wallet ?? '')))),
            strtolower(trim($historyAddress)),
        ];

        foreach ($candidates as $candidate) {
            if (preg_match('/^0x[a-f0-9]{40}$/', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
