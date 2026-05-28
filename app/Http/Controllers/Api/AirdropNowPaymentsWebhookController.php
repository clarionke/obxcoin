<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\AirdropUnlock;
use App\Model\WalletAddressHistory;
use App\Repository\WalletRepository;
use App\Services\BlockchainService;
use App\Services\NowPaymentsService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AirdropNowPaymentsWebhookController extends Controller
{
    public function handleIpn(Request $request): Response
    {
        $rawBody = $request->getContent();
        $sigHeader = $request->header('x-nowpayments-sig', '');

        $nowPayments = app(NowPaymentsService::class);
        if (!$nowPayments->verifyIpnSignature($rawBody, $sigHeader)) {
            Log::warning('AirdropNowPaymentsIPN: invalid signature', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            Log::warning('AirdropNowPaymentsIPN: malformed JSON body');
            return response('Bad Request', 400);
        }

        $paymentId = (string) ($data['payment_id'] ?? '');
        $paymentStatus = strtolower((string) ($data['payment_status'] ?? ''));
        $orderId = (string) ($data['order_id'] ?? '');

        if ($paymentId === '' || $paymentStatus === '') {
            return response('OK', 200);
        }

        $payAddress = (string) ($data['pay_address'] ?? '');
        $payAmount = (string) ($data['pay_amount'] ?? '');
        $payCurrency = strtolower((string) ($data['pay_currency'] ?? ''));
        $payinHash = (string) ($data['payin_hash'] ?? $data['tx_hash'] ?? $data['withdrawal_hash'] ?? '');

        $unlock = AirdropUnlock::where('nowpayments_payment_id', $paymentId)->first();
        if (!$unlock && $orderId !== '') {
            $unlock = $this->resolveUnlockByOrderId($orderId);
        }

        if (!$unlock) {
            Log::warning('AirdropNowPaymentsIPN: no unlock record found', [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
            ]);
            return response('OK', 200);
        }

        $updates = [
            'nowpayments_payment_id' => $paymentId,
            'nowpayments_payment_status' => $paymentStatus,
        ];

        if ($orderId !== '') {
            $updates['nowpayments_order_id'] = $orderId;
        }
        if ($payAddress !== '') {
            $updates['nowpayments_pay_address'] = $payAddress;
        }
        if ($payAmount !== '') {
            $updates['nowpayments_pay_amount'] = $payAmount;
        }
        if ($payCurrency !== '') {
            $updates['nowpayments_pay_currency'] = $payCurrency;
        }
        if ($this->isValidEvmTxHash($payinHash) && empty($unlock->tx_hash)) {
            $updates['tx_hash'] = strtolower($payinHash);
        }

        $unlock->update($updates);
        $unlock->refresh();

        if (in_array($paymentStatus, ['failed', 'expired', 'refunded'], true)) {
            Log::info('AirdropNowPaymentsIPN: payment failed', [
                'unlock_id' => $unlock->id,
                'payment_id' => $paymentId,
                'status' => $paymentStatus,
            ]);
            return response('OK', 200);
        }

        if ($paymentStatus !== 'finished') {
            return response('OK', 200);
        }

        $this->finalizeUnlock($unlock);

        return response('OK', 200);
    }

    private function resolveUnlockByOrderId(string $orderId): ?AirdropUnlock
    {
        if (str_starts_with($orderId, 'airdrop_unlock_')) {
            $id = (int) substr($orderId, strlen('airdrop_unlock_'));
            if ($id > 0) {
                return AirdropUnlock::find($id);
            }
        }

        return null;
    }

    private function finalizeUnlock(AirdropUnlock $unlock): void
    {
        DB::transaction(function () use ($unlock) {
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
                Log::warning('AirdropNowPaymentsIPN: invalid OBX amount', [
                    'unlock_id' => $locked->id,
                    'obx_released' => $locked->obx_released,
                ]);
                $locked->update(['nowpayments_payment_status' => 'finished']);
                return;
            }

            $targetWallet = $this->resolveUserEvmWallet((int) $locked->user_id);
            if (!$targetWallet) {
                Log::warning('AirdropNowPaymentsIPN: no EVM wallet available', [
                    'unlock_id' => $locked->id,
                    'user_id' => $locked->user_id,
                ]);
                $locked->update(['nowpayments_payment_status' => 'delivery_failed']);
                return;
            }

            $blockchain = app(BlockchainService::class);
            $tx = $blockchain->transferObxOnChain($targetWallet, $amount);
            if (!$tx || empty($tx['txHash'])) {
                $error = $blockchain->getLastSignerError() ?: 'OBX on-chain transfer failed';
                Log::warning('AirdropNowPaymentsIPN: OBX transfer failed', [
                    'unlock_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'wallet' => $targetWallet,
                    'error' => $error,
                ]);
                $locked->update(['nowpayments_payment_status' => 'delivery_failed']);
                return;
            }

            $updateData = [
                'status' => 'confirmed',
                'unlocked_at' => now(),
                'nowpayments_payment_status' => 'finished',
                'tx_hash' => strtolower((string) $tx['txHash']),
            ];

            $locked->update($updateData);
        });
    }

    private function resolveUserEvmWallet(int $userId): ?string
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
                    Log::warning('AirdropNowPaymentsIPN: wallet address generation failed', [
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

    private function isValidEvmTxHash(string $hash): bool
    {
        return (bool) preg_match('/^0x[a-fA-F0-9]{64}$/', $hash);
    }
}
