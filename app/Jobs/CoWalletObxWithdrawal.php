<?php

namespace App\Jobs;

use App\Model\Wallet;
use App\Model\WithdrawHistory;
use App\Services\BlockchainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $wallet = Wallet::find($data['wallet_id']);
        if (!$wallet) {
            Log::error('CoWalletObxWithdrawal: wallet not found', $data);
            return;
        }

        $amount  = (string) $data['amount'];
        $address = $data['address'];
        $userId  = $data['user_id'];

        // Validate the recipient is a valid 0x address (external on-chain address)
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            Log::error('CoWalletObxWithdrawal: invalid recipient address', ['address' => $address]);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'Invalid BSC address');
            return;
        }

        // Send on-chain via signer hot wallet → triggers OBXToken 0.05% burn
        $blockchain = app(BlockchainService::class);
        $result     = $blockchain->transferObxOnChain($address, $amount);

        if (!$result || empty($result['txHash'])) {
            Log::error('CoWalletObxWithdrawal: on-chain transfer failed', [
                'wallet_id' => $data['wallet_id'],
                'address'   => $address,
                'amount'    => $amount,
                'result'    => $result,
            ]);
            $this->recordFailedWithdrawal($wallet, $amount, $address, $userId, 'On-chain transfer failed');
            return;
        }

        $txHash = $result['txHash'];
        Log::info('CoWalletObxWithdrawal: on-chain transfer confirmed', [
            'txHash'    => $txHash,
            'wallet_id' => $data['wallet_id'],
            'amount'    => $amount,
            'address'   => $address,
        ]);

        DB::beginTransaction();
        try {
            // Deduct balance from platform wallet
            $fees = 0;
            $wallet->decrement('balance', $amount);

            WithdrawHistory::create([
                'wallet_id'        => $wallet->id,
                'address'          => $address,
                'amount'           => $amount,
                'address_type'     => ADDRESS_TYPE_EXTERNAL,
                'fees'             => $fees,
                'coin_type'        => $wallet->coin_type,
                'doller'           => 0,
                'btc'              => 0,
                'transaction_hash' => $txHash,
                'confirmations'    => 1,
                'status'           => STATUS_SUCCESS,
                'message'          => $data['message'] ?? '',
                'receiver_wallet_id' => 0,
                'user_id'          => $userId,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CoWalletObxWithdrawal: DB commit failed after chain tx', [
                'txHash' => $txHash,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function recordFailedWithdrawal(Wallet $wallet, string $amount, string $address, int $userId, string $reason): void
    {
        try {
            WithdrawHistory::create([
                'wallet_id'        => $wallet->id,
                'address'          => $address,
                'amount'           => $amount,
                'address_type'     => ADDRESS_TYPE_EXTERNAL,
                'fees'             => 0,
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
