<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Repository\WalletRepository;
use App\Services\BlockchainService;
use App\Services\NowPaymentsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles IPN (Instant Payment Notification) callbacks from NOWPayments.
 *
 * Route: POST /api/nowpayments/ipn  (unauthenticated, verified by HMAC-SHA512)
 *
 * NOWPayments lifecycle statuses:
 *   waiting → confirming → confirmed → sending → partially_paid → finished
 *   failed | refunded | expired
 *
 * We finalize purchases only after successful on-chain OBX delivery.
 */
class NowPaymentsWebhookController extends Controller
{
    public function handleIpn(Request $request): Response
    {
        // ── 1. Read raw body ───────────────────────────────────────────────
        $rawBody  = $request->getContent();
        $sigHeader = $request->header('x-nowpayments-sig', '');

        // ── 2. Verify HMAC-SHA512 signature ───────────────────────────────
        $nowPayments = new NowPaymentsService();
        if (!$nowPayments->verifyIpnSignature($rawBody, $sigHeader)) {
            Log::warning('NowPaymentsIPN: invalid signature', [
                'ip'  => $request->ip(),
                'sig' => $sigHeader,
            ]);
            return response('Unauthorized', 401);
        }

        // ── 3. Parse payload ──────────────────────────────────────────────
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            Log::warning('NowPaymentsIPN: malformed JSON body');
            return response('Bad Request', 400);
        }

        $paymentId     = $data['payment_id']     ?? null;
        $paymentStatus = $data['payment_status'] ?? null;
        $orderId       = $data['order_id']       ?? null;
        $onChainHash   = $data['payin_hash'] ?? $data['tx_hash'] ?? $data['withdrawal_hash'] ?? null;
        $buyerWallet   = $data['pay_address'] ?? null;

        Log::info("NowPaymentsIPN: payment_id=$paymentId status=$paymentStatus order=$orderId");

        if (!$paymentId || !$paymentStatus) {
            return response('OK', 200); // Not a valid IPN — ignore silently
        }

        // ── 4. Find the purchase record ───────────────────────────────────
        $purchase = BuyCoinHistory::where('nowpayments_payment_id', (string) $paymentId)->first();

        if (!$purchase) {
            // Try by order_id as fallback (orderId = buy_coin_histories.id)
            $purchase = BuyCoinHistory::find((int) $orderId);
        }

        if (!$purchase) {
            Log::warning("NowPaymentsIPN: no BuyCoinHistory for payment_id=$paymentId order=$orderId");
            return response('OK', 200);
        }

        // Keep on-chain visibility fields updated whenever IPN includes them.
        $purchaseUpdates = [];
        if (!empty($onChainHash) && is_string($onChainHash) && str_starts_with(strtolower($onChainHash), '0x')) {
            $purchaseUpdates['tx_hash'] = $onChainHash;
        }
        if (!empty($buyerWallet) && is_string($buyerWallet)) {
            $purchaseUpdates['buyer_wallet'] = strtolower($buyerWallet);
        }
        if (!empty($purchaseUpdates)) {
            $purchase->update($purchaseUpdates);
            $purchase->refresh();
        }

        // ── 5. Handle status transitions ──────────────────────────────────
        if (in_array($paymentStatus, ['failed', 'expired', 'refunded'])) {
            $purchase->update(['status' => STATUS_REJECTED]);
            Log::info("NowPaymentsIPN: order #{$purchase->id} marked REJECTED ($paymentStatus)");
            return response('OK', 200);
        }

        if ($paymentStatus !== 'finished') {
            // Intermediate status (waiting, confirming, confirmed, sending, partially_paid)
            // Update the stored payment_id in case we created the record before receiving it
            if ($paymentId && !$purchase->nowpayments_payment_id) {
                $purchase->update(['nowpayments_payment_id' => (string) $paymentId]);
            }
            Log::info("NowPaymentsIPN: order #{$purchase->id} intermediate status=$paymentStatus — no action");
            return response('OK', 200);
        }

        // ── 6. Finished — enforce on-chain delivery first ──────────────────
        $wasAlreadyCredited = ((int) $purchase->status === STATUS_SUCCESS);
        $isDelivered = (($purchase->obx_delivery_status ?? 'pending') === 'success');

        if (!$isDelivered) {
            $targetWallet = $this->resolveTargetWallet($purchase);

            if (!$targetWallet) {
                $purchase->update([
                    'obx_delivery_status' => 'failed',
                    'obx_delivery_error' => 'No valid EVM wallet configured for delivery',
                ]);
                Log::warning("NowPaymentsIPN: EVM delivery failed, missing wallet for order #{$purchase->id}");
                return response('OK', 200);
            }

            try {
                $blockchain = app(BlockchainService::class);
                $beforeBalance = $blockchain->getObxBalance($targetWallet);
                $tx = $blockchain->transferObxOnChain($targetWallet, (string) $purchase->requested_amount);

                if ($tx && !empty($tx['txHash'])) {
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
                    Log::info("NowPaymentsIPN: delivered OBX on-chain for order #{$purchase->id} tx={$tx['txHash']}");
                } else {
                    $err = $blockchain->getLastSignerError() ?: 'OBX delivery failed';
                    $purchase->update([
                        'obx_delivery_status' => 'failed',
                        'obx_delivery_error' => mb_substr($err, 0, 500),
                    ]);
                    Log::warning("NowPaymentsIPN: OBX delivery failed for order #{$purchase->id}: {$err}");
                    return response('OK', 200);
                }
            } catch (\Throwable $e) {
                $purchase->update([
                    'obx_delivery_status' => 'failed',
                    'obx_delivery_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
                Log::error("NowPaymentsIPN: OBX delivery exception for order #{$purchase->id}: " . $e->getMessage());
                return response('OK', 200);
            }
        }

        DB::beginTransaction();
        try {
            if (!$wasAlreadyCredited) {
                $purchase->update(['status' => STATUS_SUCCESS]);
            }

            // Mirror the finalized on-chain delivery into the internal OBX wallet ledger.
            $wallet = $this->resolveOrCreatePrimaryObxWallet((int) $purchase->user_id);

            if ($wallet && !$wasAlreadyCredited) {
                $creditedAmount = $this->resolveDeliveredAmountForCredit($purchase);
                $wallet->increment('balance', $creditedAmount);
                Log::info("NowPaymentsIPN: credited {$creditedAmount} OBX to user #{$purchase->user_id} after on-chain delivery");
            } elseif (!$wallet) {
                Log::warning("NowPaymentsIPN: no primary OBX wallet for user #{$purchase->user_id}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NowPaymentsIPN: DB error for payment_id=' . $paymentId . ': ' . $e->getMessage());
            // Return 200 so NOWPayments does not retry; alert is in logs
            return response('OK', 200);
        }

        return response('OK', 200);
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

            // Ensure default OBX wallet has an address before failing delivery.
            if (!preg_match('/^0x[a-f0-9]{40}$/', strtolower(trim($historyAddress)))) {
                try {
                    app(WalletRepository::class)->generateTokenAddress((int) $primaryWallet->id);
                    $historyAddress = (string) WalletAddressHistory::where('wallet_id', (int) $primaryWallet->id)
                        ->orderByDesc('id')
                        ->value('address');
                } catch (\Throwable $e) {
                    Log::warning('NowPaymentsIPN: failed to auto-generate delivery wallet', [
                        'user_id' => (int) $purchase->user_id,
                        'wallet_id' => (int) $primaryWallet->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $candidates = [
            strtolower(trim((string)($purchase->buyer_wallet ?? ''))),
            strtolower(trim((string)($purchase->user->bsc_wallet ?? ''))),
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

        // Conservative fallback to contract burn model (0.05%) if tx parsing is unavailable.
        $fallback = bcmul($requested, '0.9995', 8);
        return (float) $fallback;
    }
}
