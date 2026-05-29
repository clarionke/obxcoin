<?php

namespace App\Jobs;

use App\Model\Wallet;
use App\Model\TempWithdraw;
use App\Model\WalletAddressHistory;
use App\Model\WithdrawHistory;
use App\Services\BlockchainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes an on-chain OBXCoin transfer for a Team Wallet (CO_WALLET) withdrawal.
 *
 * The OBXToken.sol contract burns 0.05% of every transfer automatically.
 * The txHash returned by the signer is stored for BSCScan visibility.
 */
class CoWalletObxWithdrawal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries   = 3;

    private array $tempWithdraw;

    public function __construct(array $tempWithdraw)
    {
        $this->tempWithdraw = $tempWithdraw;
    }

    public function handle(): void
    {
        $data = $this->tempWithdraw;
        $tempWithdrawId = (int) ($data['id'] ?? 0);

        if ($tempWithdrawId > 0) {
            $tempStatus = (int) TempWithdraw::where('id', $tempWithdrawId)->value('status');
            if (!in_array($tempStatus, [STATUS_PENDING, STATUS_ACCEPTED], true)) {
                Log::info('CoWalletObxWithdrawal: skipped because temp withdraw is not pending', [
                    'temp_withdraw_id' => $tempWithdrawId,
                    'status' => $tempStatus,
                ]);
                return;
            }
        }

        $wallet = Wallet::find($data['wallet_id']);
        if (!$wallet) {
            Log::error('CoWalletObxWithdrawal: wallet not found', $data);
            $this->markTempWithdrawRejected($tempWithdrawId);
            return;
        }

        $amount  = (string) $data['amount'];
        $address = strtolower(trim((string) ($data['address'] ?? '')));
        $userId  = (int) ($data['user_id'] ?? 0);
        $fees    = $this->calculateWithdrawalFeeAmount($wallet, $amount);
        $senderAddress = $this->resolveWalletSenderAddress($wallet);

        if (function_exists('bcadd')) {
            $totalDebit = bcadd((string) $amount, (string) $fees, 8);
        } else {
            $totalDebit = (string) (((float) $amount) + ((float) $fees));
        }

        // Validate the recipient is a valid 0x address (external on-chain address)
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            Log::error('CoWalletObxWithdrawal: invalid recipient address', ['address' => $address]);
            $this->markTempWithdrawRejected($tempWithdrawId);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'Invalid BSC address', $fees);
            return;
        }

        if (!preg_match('/^0x[a-f0-9]{40}$/', $senderAddress)) {
            Log::error('CoWalletObxWithdrawal: sender wallet address is missing/invalid', [
                'wallet_id' => $wallet->id,
                'sender_address' => $senderAddress,
            ]);
            $this->markTempWithdrawRejected($tempWithdrawId);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'Wallet sender address not configured', $fees);
            return;
        }

        if (((float) $wallet->balance) < ((float) $totalDebit)) {
            Log::warning('CoWalletObxWithdrawal: insufficient wallet balance', [
                'wallet_id' => $wallet->id,
                'balance' => (string) $wallet->balance,
                'amount' => $amount,
                'fees' => $fees,
                'total_debit' => $totalDebit,
            ]);
            $this->markTempWithdrawRejected($tempWithdrawId);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'Insufficient balance for withdrawal + fee', $fees);
            return;
        }

        $blockchain = app(BlockchainService::class);
        $preflight = $blockchain->validateObxTransferFromPreconditions($senderAddress, $amount);
        if (empty($preflight['success'])) {
            $msg = (string) ($preflight['message'] ?? 'On-chain allowance check failed');
            if (!empty($preflight['spender'])) {
                $msg .= ' Spender wallet: ' . $preflight['spender'] . '.';
            }
            if (isset($preflight['allowance'], $preflight['required'])) {
                $msg .= ' Allowance: ' . $preflight['allowance'] . ' ' . DEFAULT_COIN_TYPE
                    . ', required: ' . $preflight['required'] . ' ' . DEFAULT_COIN_TYPE . '.';
            }

            Log::warning('CoWalletObxWithdrawal: preflight failed', [
                'wallet_id' => $wallet->id,
                'sender' => $senderAddress,
                'address' => $address,
                'message' => $msg,
            ]);

            $this->markTempWithdrawRejected($tempWithdrawId);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, $msg, $fees);
            return;
        }

        $txHash = null;

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            if (!$wallet) {
                DB::rollBack();
                Log::error('CoWalletObxWithdrawal: wallet disappeared during locked read', ['wallet_id' => $data['wallet_id']]);
                $this->markTempWithdrawRejected($tempWithdrawId);
                return;
            }

            if (((float) $wallet->balance) < ((float) $totalDebit)) {
                DB::rollBack();
                Log::warning('CoWalletObxWithdrawal: insufficient balance during locked read', [
                    'wallet_id' => $wallet->id,
                    'balance' => (string) $wallet->balance,
                    'total_debit' => $totalDebit,
                ]);
                $this->markTempWithdrawRejected($tempWithdrawId);
                $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'Insufficient balance for withdrawal + fee', $fees);
                return;
            }

            $wallet->decrement('balance', $totalDebit);

            $withdraw = WithdrawHistory::create([
                'wallet_id'        => $wallet->id,
                'address'          => $address,
                'amount'           => $amount,
                'address_type'     => ADDRESS_TYPE_EXTERNAL,
                'fees'             => $fees,
                'coin_type'        => $wallet->coin_type,
                'doller'           => 0,
                'btc'              => 0,
                'transaction_hash' => Str::random(32),
                'confirmations'    => 0,
                'status'           => STATUS_PENDING,
                'message'          => $data['message'] ?? '',
                'receiver_wallet_id' => 0,
                'user_id'          => $userId,
            ]);

            if ($tempWithdrawId > 0) {
                TempWithdraw::where('id', $tempWithdrawId)->update(['withdraw_id' => $withdraw->id]);
            }

            // Send on-chain from the user's own wallet address; admin wallet only pays network gas.
            $result = $blockchain->transferObxFromOnChain($senderAddress, $address, $amount);

            if (!$result || empty($result['txHash'])) {
                DB::rollBack();
                Log::error('CoWalletObxWithdrawal: on-chain transfer failed', [
                    'wallet_id' => $data['wallet_id'],
                    'sender'    => $senderAddress,
                    'address'   => $address,
                    'amount'    => $amount,
                    'fees'      => $fees,
                    'result'    => $result,
                ]);
                $this->markTempWithdrawRejected($tempWithdrawId);
                $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'On-chain transfer failed', $fees);
                return;
            }

            $txHash = $result['txHash'];

            $withdraw->transaction_hash = $txHash;
            $withdraw->confirmations = 1;
            $withdraw->status = STATUS_SUCCESS;
            $withdraw->save();

            if ($tempWithdrawId > 0) {
                TempWithdraw::where('id', $tempWithdrawId)->update([
                    'status' => STATUS_SUCCESS,
                    'withdraw_id' => $withdraw->id,
                ]);
            }

            Log::info('CoWalletObxWithdrawal: on-chain transfer confirmed', [
                'txHash'    => $txHash,
                'wallet_id' => $data['wallet_id'],
                'sender'    => $senderAddress,
                'amount'    => $amount,
                'fees'      => $fees,
                'address'   => $address,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CoWalletObxWithdrawal: failed', [
                'txHash' => $txHash,
                'wallet_id' => $data['wallet_id'] ?? null,
                'error'  => $e->getMessage(),
            ]);

            if (!empty($txHash)) {
                Log::critical('CoWalletObxWithdrawal: on-chain tx broadcast but DB update failed; manual reconciliation required', [
                    'txHash' => $txHash,
                    'wallet_id' => $data['wallet_id'] ?? null,
                ]);
                return;
            }

            $this->markTempWithdrawRejected($tempWithdrawId);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'Withdrawal processing failed', $fees);
        }
    }

    private function resolveWithdrawalFeePercent(Wallet $wallet): string
    {
        $feePercent = (string) ($wallet->withdrawal_fees ?? '0');
        $adminFeePercent = settings(OBX_WITHDRAWAL_FEE_PERCENT_SLUG);
        if ($adminFeePercent !== false && $adminFeePercent !== null && $adminFeePercent !== '') {
            $feePercent = (string) $adminFeePercent;
        }

        if (!is_numeric($feePercent)) {
            return '0.00000000';
        }

        $feePercentFloat = (float) $feePercent;
        if ($feePercentFloat < 0) {
            $feePercentFloat = 0;
        }

        return number_format($feePercentFloat, 8, '.', '');
    }

    private function resolveWalletSenderAddress(Wallet $wallet): string
    {
        return strtolower(trim((string) WalletAddressHistory::where('wallet_id', $wallet->id)
            ->orderBy('id', 'desc')
            ->value('address')));
    }

    private function calculateWithdrawalFeeAmount(Wallet $wallet, string $amount): string
    {
        $feePercent = $this->resolveWithdrawalFeePercent($wallet);
        if ((float) $amount <= 0 || (float) $feePercent <= 0) {
            return '0.00000000';
        }

        if (function_exists('bcmul') && function_exists('bcdiv')) {
            return bcdiv(bcmul($amount, $feePercent, 16), '100', 8);
        }

        return number_format((((float) $amount) * ((float) $feePercent)) / 100, 8, '.', '');
    }

    private function markTempWithdrawRejected(int $tempWithdrawId): void
    {
        if ($tempWithdrawId <= 0) {
            return;
        }

        TempWithdraw::where('id', $tempWithdrawId)->update(['status' => STATUS_REJECTED]);
    }

    private function recordFailedWithdrawal(Wallet $wallet, string $amount, string $address, int $userId, string $reason, string $fees = '0.00000000'): void
    {
        try {
            WithdrawHistory::create([
                'wallet_id'        => $wallet->id,
                'address'          => $address,
                'amount'           => $amount,
                'address_type'     => ADDRESS_TYPE_EXTERNAL,
                'fees'             => $fees,
                'coin_type'        => $wallet->coin_type,
                'doller'           => 0,
                'btc'              => 0,
                'transaction_hash' => '',
                'confirmations'    => 0,
                'status'           => STATUS_REJECTED,
                'message'          => $reason,
                'receiver_wallet_id' => 0,
                'user_id'          => $userId,
            ]);
        } catch (\Exception $e) {
            Log::error('CoWalletObxWithdrawal: could not record failure', ['error' => $e->getMessage()]);
        }
    }
}
