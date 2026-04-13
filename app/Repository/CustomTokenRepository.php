<?php
/**
 * Created by PhpStorm.
 * User: bacchu
 * Date: 1/25/22
 * Time: 5:19 PM
 */

namespace App\Repository;

use App\Model\AdminReceiveTokenTransactionHistory;
use App\Model\DepositeTransaction;
use App\Model\EstimateGasFeesTransactionHistory;
use App\Model\WalletAddressHistory;
use App\Model\Wallet;
use App\Services\ERC20TokenApi;
use App\Services\Logger;
use App\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;


class CustomTokenRepository
{
    private $tokenApi;
    private $logger;
    private $settings;
    private $walletAddress;
    private $walletPk;
    private $contractCoinName;

    public function __construct()
    {
        $this->tokenApi = new ERC20TokenApi();
        $this->logger = new Logger();
        $this->settings = allsetting();
        $this->walletAddress = $this->settings['wallet_address'] ?? '';
        $this->walletPk = $this->settings['private_key'] ?? '';
        $this->contractCoinName = $this->settings['contract_coin_name'] ?? 'ETH';
    }

    public function depositCustomToken()
    {
        try {
            $latestTransactions = $this->getLatestTransactionFromBlock();
            storeDetailsException('depositCustomToken latest transaction', json_encode($latestTransactions));
            if ($latestTransactions['success'] == true) {
                foreach($latestTransactions['data'] as $transaction) {
                    storeDetailsException('$transaction ',json_encode($transaction));
                    $this->checkAddressAndDeposit($transaction->to_address,$transaction->tx_hash,$transaction->amount,$transaction->from_address);
                }
            } else {
                storeDetailsException('depositCustomToken', $latestTransactions['message']);
            }
            return $latestTransactions;
        } catch (\Exception $e) {
            storeException('depositCustomToken', $e->getMessage());
        }
    }

    // update wallet
    public function updateUserWallet($deposit,$hash)
    {
        try {
            DepositeTransaction::where(['id' => $deposit->id])
                ->update([
                    'status' => STATUS_SUCCESS,
//                    'transaction_id' => $hash
                    ]);
            $userWallet = $deposit->receiverWallet;
            storeException('depositCustomToken', 'before update wallet balance => '. $userWallet->balance);
            $userWallet->increment('balance',$deposit->amount);
            storeException('depositCustomToken', 'after update wallet balance => '. $userWallet->balance);
            storeException('depositCustomToken', 'update one wallet id => '. $deposit->receiver_wallet_id);
            storeException('depositCustomToken', 'Deposit process success');
        } catch (\Exception $e) {
            storeException('updateUserWallet', $e->getMessage());
        }
    }

    // check address and deposit
    public function checkAddressAndDeposit($address,$hash,$amount,$fromAddress)
    {
        try {
            $checkAddress = WalletAddressHistory::where(['address' => $address, 'coin_type' => DEFAULT_COIN_TYPE])->first();
            if(!empty($checkAddress)) {
                $checkDeposit = DepositeTransaction::where(['address' => $address, 'transaction_id' => $hash])->first();
                if ($checkDeposit) {
                    storeDetailsException('checkAddressAndDeposit', 'deposit already in db '.$hash);
                    $response = ['success' => false, 'message' => __('This hash already in db'), 'data' => []];
                } else {
                    $amount = floatval($amount);
                    $createDeposit = DepositeTransaction::create([
                        'address' => $address,
                        'from_address' => $fromAddress,
                        'receiver_wallet_id' => $checkAddress->wallet_id,
                        'address_type' => ADDRESS_TYPE_EXTERNAL,
                        'type' => $checkAddress->coin_type,
                        'amount' => $amount,
                        'doller' => bcmul($amount,settings('coin_price'),8),
                        'transaction_id' => $hash,
                        'unique_code' => uniqid().date('').time(),
                        'coin_id' => $checkAddress->wallet->coin_id
                    ]);
                    if ($createDeposit) {
                        storeException('deposit created', $createDeposit);
                        $wallet = Wallet::where(['id' => $createDeposit->receiver_wallet_id])->first();
                        if ($wallet) {
                            storeException('deposit amount', ($amount));
                            storeException('balance before', $wallet->balance);
                            $wallet->increment('balance', $amount);
                            $createDeposit->status = STATUS_ACTIVE;
                            $createDeposit->save();
                            storeException('balance after', $wallet->balance);
                        }
                        $response = ['success' => true, 'message' => __('New deposit'), 'data' => $createDeposit, 'pk' => $checkAddress->wallet_key];
                    } else {
                        $response = ['success' => false, 'message' => 'deposit credited failed', 'data' => []];
                    }
                }
            } else {
                storeDetailsException('checkAddressAndDeposit', 'address not found in db '.$address);
                $response = ['success' => false, 'message' => __('This address not found in db'), 'data' => []];
            }
        } catch (\Exception $e) {
            storeException('checkAddressAndDeposit', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // get latest transaction block data
    public function getLatestTransactionFromBlock()
    {
        $response = ['success' => false, 'message' => 'failed', 'data' => []];
        try {
            $result = $this->tokenApi->getContractTransferEvent();
            storeDetailsException('getLatestTransactionFromBlock',$result);
            if ($result['success'] == true) {
                $response = ['success' => $result['success'], 'message' => $result['message'], 'data' => $result['data']->result];
            } else {
                $response = ['success' => false, 'message' => __('No transaction found'), 'data' => []];
            }

        } catch (\Exception $e) {
            storeException('getLatestTransactionFromBlock', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // check estimate gas for sending token
    public function checkEstimateGasFees($address,$amount)
    {
        $response = ['success' => false, 'message' => 'failed', 'data' => []];
        try {
            $requestData = [
                "amount_value" => $amount,
                "from_address" => $address,
                "to_address" => $this->walletAddress
            ];
            storeException('checkEstimateGasFees', json_encode($requestData));

            $check = $this->tokenApi->checkEstimateGas($requestData);
            storeException('checkEstimateGasFees', $check);
            if ($check['success'] == true) {
                $response = ['success' => true, 'message' => $check['message'], 'data' => $check['data']];
            } else {
                $response = ['success' => false, 'message' => $check['message'], 'data' => []];
            }
        } catch (\Exception $e) {
            storeException('checkEstimateGasFees', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }

        return $response;
    }

    // send estimate gas fees to address
    public function sendFeesToUserAddress($address,$amount,$wallet_id,$depositId)
    {
        try {
            $requestData = [
            "amount_value" => $amount,
            "from_address" => $this->walletAddress,
            "to_address" => $address,
            "contracts" => $this->walletPk
        ];

        $result = $this->tokenApi->sendEth($requestData);
        storeException('sendFeesToUserAddress result ', $result);
        if ($result['success'] == true) {
            $this->saveEstimateGasFeesTransaction($wallet_id,$result['data']->hash,$amount,$this->walletAddress,$address,$depositId);
            $response = ['success' => true, 'message' => __('Fess send successfully'), 'data' => []];
        } else {
            storeException('sendFeesToUserAddress', $result['message']);
            $response = ['success' => false, 'message' => $result['message'], 'data' => []];
        }
        } catch (\Exception $e) {
            storeException('sendFeesToUserAddress', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // save estimate gas fees transaction
    public function saveEstimateGasFeesTransaction($wallet_id,$hash,$amount,$adminAddress,$userAddress,$depositId)
    {
        try {
           $data = EstimateGasFeesTransactionHistory::create([
                'unique_code' => uniqid().date('').time(),
                'wallet_id' => $wallet_id,
               'deposit_id' => $depositId,
                'amount' => $amount,
                'coin_type' => $this->contractCoinName,
                'admin_address' => $adminAddress,
                'user_address' => $userAddress,
                'transaction_hash' => $hash,
                'status' => STATUS_PENDING
            ]);
           storeException('saveEstimateGasFeesTransaction', json_encode($data));
        } catch (\Exception $e) {
            storeException('saveEstimateGasFeesTransaction', $e->getMessage());
        }
    }

    // receive token from user address
    public function receiveTokenFromUserAddress($address,$amount,$userPk,$depositId)
    {
        try {
            $requestData = [
                "amount_value" => $amount,
                "from_address" => $address,
                "to_address" => $this->walletAddress,
                "contracts" => $userPk
            ];

            $checkAddressBalanceAgain = $this->checkWalletAddressAllBalance($address);
            storeException('receiveTokenFromUserAddress  $check Address All Balance ',$checkAddressBalanceAgain);

            $result = $this->tokenApi->sendCustomToken($requestData);
            storeException('receiveTokenFromUserAddress $result', $result);
            if ($result['success'] == true) {
                $this->saveReceiveTransaction($result['data']->used_gas,$result['data']->hash,$amount,$this->walletAddress,$address,$depositId);
                $response = ['success' => true, 'message' => __('Token received successfully'), 'data' => $result['data']];
            } else {
                storeException('receiveTokenFromUserAddress', $result['message']);
                $response = ['success' => false, 'message' => $result['message'], 'data' => []];
            }
        } catch (\Exception $e) {
            storeException('receiveTokenFromUserAddress', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // save receive token transaction
    public function saveReceiveTransaction($fees,$hash,$amount,$adminAddress,$userAddress,$depositId)
    {
        try {
            $data = AdminReceiveTokenTransactionHistory::create([
                'unique_code' => uniqid().date('').time(),
                'amount' => $amount,
                'deposit_id' => $depositId,
                'fees' => $fees,
                'to_address' => $adminAddress,
                'from_address' => $userAddress,
                'transaction_hash' => $hash,
                'status' => STATUS_SUCCESS
            ]);
            storeException('saveReceiveTransaction', json_encode($data));
        } catch (\Exception $e) {
            storeException('saveReceiveTransaction', $e->getMessage());
        }
    }

    // check wallet balance
    public function checkWalletAddressBalance($address)
    {
        try {
            $requestData = array(
                "type" => 1,
                "address" => $address,
            );
            $result = $this->tokenApi->checkWalletBalance($requestData);
            if ($result['success'] == true) {
                $response = ['success' => true, 'message' => __('Get balance'), 'data' => $result['data'] ];
            } else {
                storeException('sendFeesToUserAddress', $result['message']);
                $response = ['success' => false, 'message' => $result['message'], 'data' => []];
            }
        } catch (\Exception $e) {
            storeException('checkWalletAddressBalance', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // check wallet balance
    public function checkWalletAddressAllBalance($address)
    {
        try {
            $requestData = array(
                "type" => 3,
                "address" => $address,
            );
            $result = $this->tokenApi->checkWalletBalance($requestData);
            if ($result['success'] == true) {
                $response = ['success' => true, 'message' => __('Get balance'), 'data' => $result['data'] ];
            } else {
                storeException('sendFeesToUserAddress', $result['message']);
                $response = ['success' => false, 'message' => $result['message'], 'data' => []];
            }
        } catch (\Exception $e) {
            storeException('checkWalletAddressBalance', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // token receive manually by admin
    public function tokenReceiveManuallyByAdmin($transaction,$adminId)
    {
        try {
            if ($transaction->is_admin_receive == STATUS_PENDING) {
                $this->tokenReceiveManuallyByAdminProcess($transaction,$adminId);
            }
        } catch (\Exception $e) {
            storeException('tokenReceiveManuallyByAdmin', $e->getMessage());
        }
    }

    // token Receive Manually By Admin process
    public function tokenReceiveManuallyByAdminProcess($transaction,$adminId)
    {
        try {
            if ($transaction->is_admin_receive == STATUS_PENDING) {
                $sendAmount = (float)$transaction->amount;
                $checkAddress = $this->checkAddress($transaction->address);
                $userPk = get_wallet_personal_add($transaction->address,$checkAddress->pk);
                if (settings('network_type') == TRC20_TOKEN) {
                    $checkGasFees['success'] = true;
                    $gas = TRC20ESTFEE;
                } else {
                    $checkGasFees = $this->checkEstimateGasFees($transaction->address, $sendAmount);
                }
                if($checkGasFees['success'] == true) {
                    if (settings('network_type') != TRC20_TOKEN) {
                        $this->logger->log('Estimate gas ',$checkGasFees['data']->estimateGasFees);
                        $estimateFees = $checkGasFees['data']->estimateGasFees;
                        $gas = bcadd($estimateFees , (bcdiv(bcmul($estimateFees, 10,8),100,8)),8);
                        $this->logger->log('Gas',$gas);
                    }

                    $checkAddressBalance = $this->checkWalletAddressBalance($transaction->address);
                    if ($checkAddressBalance['success'] == true) {
                        $walletNetBalance = $checkAddressBalance['data']->net_balance;
                        storeException('$walletNetBalance',$walletNetBalance);
                        if ($walletNetBalance >= $gas) {
                            $estimateGas = 0;
                            storeException('$estimateGas 0 ',$estimateGas);
                        } else {
                            $estimateGas = bcsub($gas, $walletNetBalance,8);
                            storeException('$estimateGas have ',$estimateGas);
                        }
                        if ($estimateGas > 0) {
                            storeException('sendFeesToUserAddress ',$estimateGas);
                            $sendFees = $this->sendFeesToUserAddress($transaction->address,$estimateGas,$checkAddress->wallet_id,$transaction->id);
                            if ($sendFees['success'] == true) {
                                storeException('tokenReceiveManuallyByAdminProcess -> ', 'sendFeesToUserAddress success . the next process will held on getDepositBalanceFromUserJob');

                            } else {
                                storeException('tokenReceiveManuallyByAdminProcess', 'send fees process failed');
                            }
                        } else {
                            storeException('sendFeesToUserAddress ', 'no gas needed');
                        }
                        $receiveToken = $this->receiveTokenFromUserAddressByAdminPanel($transaction->address, $sendAmount, $userPk, $transaction->id);
                        if ($receiveToken['success'] == true) {
                            $this->updateUserWalletByAdmin($transaction, $adminId);
                        } else {
                            $this->logger->log('tokenReceiveManuallyByAdminProcess', 'token received process failed');
                        }
                    } else {
                        storeException('tokenReceiveManuallyByAdminProcess', 'get balance failed');
                    }
                } else {
                    storeException('tokenReceiveManuallyByAdminProcess', 'check gas fees calculate failed');
                }
            } else {
                storeException('tokenReceiveManuallyByAdminProcess', 'transaction is not pending');
            }
        } catch (\Exception $e) {
            storeException('tokenReceiveManuallyByAdminProcess', $e->getMessage());
        }
    }

    // check address
    public function checkAddress($address)
    {
        return WalletAddressHistory::where(['address' => $address, 'coin_type' => DEFAULT_COIN_TYPE])->first();
    }

    // receive token from user address by admin
    public function receiveTokenFromUserAddressByAdminPanel($address,$amount,$userPk,$depositId)
    {
        try {
            $requestData = [
                "amount_value" => $amount,
                "from_address" => $address,
                "to_address" => $this->walletAddress,
                "contracts" => $userPk
            ];

            $checkAddressBalanceAgain = $this->checkWalletAddressAllBalance($address);
            storeException('receiveTokenFromUserAddressByAdminPanel  $check Address All Balance ',$checkAddressBalanceAgain);

            $result = $this->tokenApi->sendCustomToken($requestData);
            storeException('receiveTokenFromUserAddressByAdminPanel $result', $result);
            if ($result['success'] == true) {
                $this->saveReceiveTransaction($result['data']->used_gas,$result['data']->hash,$amount,$this->walletAddress,$address,$depositId);
                $response = ['success' => true, 'message' => __('Token received successfully'), 'data' => $result['data']];
            } else {
                storeException('receiveTokenFromUserAddressByAdminPanel', $result['message']);
                $response = ['success' => false, 'message' => $result['message'], 'data' => []];
            }
        } catch (\Exception $e) {
            storeException('receiveTokenFromUserAddressByAdminPanel', $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        return $response;
    }

    // update wallet
    public function updateUserWalletByAdmin($deposit,$adminId)
    {
        try {
            DepositeTransaction::where(['id' => $deposit->id])
                ->update([
                    'is_admin_receive' => STATUS_SUCCESS,
                    'received_amount' => $deposit->amount,
                    'updated_by' => $adminId
                ]);
            storeException('updateUserWalletByAdmin', 'Deposit process success');
        } catch (\Exception $e) {
            storeException('updateUserWalletByAdmin', $e->getMessage());
        }
    }


    // get deposit token balance from user
    public function getDepositTokenFromUser()
    {
        storeDetailsException('getDepositTokenFromUser', 'called');
        try {
            $adminId = 1;
            $admin = User::where(['role' => USER_ROLE_ADMIN])->orderBy('id', 'asc')->first();
            if ($admin) {
                $adminId = $admin->id;
            }
            $transactions = DepositeTransaction::where(['deposite_transactions.address_type' => ADDRESS_TYPE_EXTERNAL])
                ->where('deposite_transactions.is_admin_receive', STATUS_PENDING)
                ->where('deposite_transactions.type', DEFAULT_COIN_TYPE)
                ->select('deposite_transactions.*')
                ->get();
            if (isset($transactions[0])) {
                foreach ($transactions as $transaction) {
                    $this->tokenReceiveManuallyByAdmin($transaction, $adminId);
                }
            }
        } catch (\Exception $e) {
            storeDetailsException('getDepositTokenFromUser', $e->getMessage());
        }
    }

}
