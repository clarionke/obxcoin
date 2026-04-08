<?php
/**
 * Created by PhpStorm.
 * User: rana
 * Date: 8/30/17
 * Time: 5:19 PM
 */

namespace App\Repository;

use App\Http\Services\CommonService;
use App\Jobs\GiveCoin;
use App\Model\BuyCoinHistory;
use App\Model\CoinRequest;
use App\Model\Wallet;
use App\Services\NowPaymentsService;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CoinRepository
{
    // send coin amount request to user
    public function sendCoinAmountRequest($request)
    {
        try {
            $user = User::where(['email'=> $request->email, 'role'=> USER_ROLE_USER, 'status'=> STATUS_ACTIVE])->first();
            if (isset($user)) {
                if ($user->email == Auth::user()->email) {
                    $response = ['success' => false, 'message' => __('You can not send request to your own email')];
                    return $response;
                }
                $myWallet = get_primary_wallet(Auth::id(), 'Default');
                $userWallet = get_primary_wallet($user->id, 'Default');
                $data = [
                    'amount' => $request->amount,
                    'sender_user_id' => $user->id,
                    'sender_wallet_id' => $userWallet->id,
                    'receiver_user_id' => Auth::id(),
                    'receiver_wallet_id' => $myWallet->id
                ];
                CoinRequest::create($data);

                $response = ['success' => true, 'message' => __('Request sent successfully. Please wait for user approval')];
            } else {
                $response = ['success' => false, 'message' => __('User not found')];
            }

        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => __('Something went wrong')];
            return $response;
        }

        return $response;
    }

    // give coin amount request to user
    public function giveCoinToUser($request)
    {
        try {
            $user = User::where(['email'=> $request->email, 'role'=> USER_ROLE_USER, 'status'=> STATUS_ACTIVE])->first();
            if (isset($user)) {
                if ($user->email == Auth::user()->email) {
                    $response = ['success' => false, 'message' => __('You can not give coin to your own email')];
                    return $response;
                }
                $myWallet = Wallet::where(['id' => $request->wallet_id])->first();
                if (isset($myWallet)) {
                    $userWallet = get_primary_wallet($user->id, $myWallet->coin_type);;
                    if ($myWallet->balance < $request->amount) {
                        $response = ['success' => false, 'message' => __('Your selected wallet has not enough coin to give')];
                        return $response;
                    }
                    $data = [
                        'amount' => $request->amount,
                        'receiver_wallet_id' => $userWallet->id,
                        'sender_wallet_id' => $myWallet->id,
                        'receiver_user_id' => $user->id,
                        'sender_user_id' => Auth::id(),
                        'update_id' => ''
                    ];

//                    $this->sendCoinToUser($data);
                    dispatch(new GiveCoin($data))->onQueue('give-coin');

                    $response = ['success' => true, 'message' => __('Coin sent successfully.')];
                } else {
                    $response = ['success' => false, 'message' => __('Wallet not found')];
                }

            } else {
                $response = ['success' => false, 'message' => __('User not found')];
            }

        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => __('Something went wrong')];
            return $response;
        }

        return $response;
    }

    // accept coin request process
    public function acceptCoinRequest($request_id)
    {
        try {
            $request_coin = CoinRequest::where(['id' => $request_id, 'status'=> STATUS_PENDING])->first();

            if (isset($request_coin)) {
                $user = User::where(['id'=> $request_coin->sender_user_id])->first();

                $myWallet = Wallet::where(['id' => $request_coin->sender_wallet_id])->first();
                $userWallet = Wallet::where(['id' => $request_coin->receiver_wallet_id])->first();
                if (isset($myWallet)) {
                    if ($myWallet->balance < $request_coin->amount) {
                        $response = ['success' => false, 'message' => __('Your wallet has not enough coin to give')];
                        return $response;
                    }
                    $data = [
                        'amount' => $request_coin->amount,
                        'receiver_wallet_id' => $request_coin->receiver_wallet_id,
                        'sender_wallet_id' => $request_coin->sender_wallet_id,
                        'receiver_user_id' => $request_coin->receiver_user_id,
                        'sender_user_id' => $request_coin->sender_user_id,
                        'update_id' => $request_coin->id
                    ];

//                    $this->sendCoinToUser($data);
                    dispatch(new GiveCoin($data))->onQueue('give-coin');

                    $response = ['success' => true, 'message' => __('Coin request accepted successfully.')];
                } else {
                    $response = ['success' => false, 'message' => __('Wallet not found')];
                }

            } else {
                $response = ['success' => false, 'message' => __('Request not found')];
            }

        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => __('Something went wrong')];
            return $response;
        }

        return $response;
    }
//  coin request rejected process
    public function rejectCoinRequest($request_id)
    {
        try {
            $request_coin = CoinRequest::where(['id' => $request_id, 'status'=> STATUS_PENDING])->first();

            if (isset($request_coin)) {
                $request_coin->update(['status'=> STATUS_REJECTED]);

                $response = ['success' => true, 'message' => __('Coin request rejected successfully.')];
            } else {
                $response = ['success' => false, 'message' => __('Request not found')];
            }

        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => __('Something went wrong')];
            return $response;
        }

        return $response;
    }

    // send coin to user
    public function sendCoinToUser($data)
    {
        DB::beginTransaction();
        try {
            Log::info('give coin process started');
            $myWallet = Wallet::where(['id' => $data['sender_wallet_id']])->first();
            $userWallet = Wallet::where(['id' => $data['receiver_wallet_id']])->first();
            if ($myWallet->balance < $data['amount']) {
                $response = ['success' => false, 'message' => __('Your selected wallet has not enough coin to give')];
                Log::info('Your selected wallet has not enough coin to give');
                Log::info('give coin process failed');

                return $response;
            }
            if (!empty($data['update_id'])) {
                CoinRequest::where('id',$data['update_id'])->update(['status' => STATUS_SUCCESS]);
                Log::info('give coin = '.$data['amount']);
                Log::info('sender wallet id = '.$data['sender_wallet_id']);
                Log::info('receiver wallet id = '.$data['receiver_wallet_id']);
                $myWallet->decrement('balance',$data['amount']);
                $userWallet->increment('balance',$data['amount']);
            } else {
                $save = CoinRequest::create($data);
                if ($save) {
                    CoinRequest::where('id',$save->id)->update(['status' => STATUS_SUCCESS]);
                    Log::info('give coin = '.$data['amount']);
                    Log::info('sender wallet id = '.$data['sender_wallet_id']);
                    Log::info('receiver wallet id = '.$data['receiver_wallet_id']);
                    $myWallet->decrement('balance',$data['amount']);
                    $userWallet->increment('balance',$data['amount']);
                }
            }

            DB::commit();

            Log::info('give coin process success');
            $response = ['success' => true, 'message' => __('Coin sent successfully.')];

        } catch (\Exception $e) {
            Log::info('give coin process exception');
            Log::info($e->getMessage());
            DB::rollBack();
            $response = ['success' => false, 'message' => __('Something went wrong')];
            return $response;
        }
    }


    /**
     * Buy OBX tokens via NOWPayments (crypto payment processor).
     *
     * Creates a pending BuyCoinHistory record, calls the NOWPayments API to
     * generate a payment address, and stores the payment details. The IPN
     * webhook (NowPaymentsWebhookController) later credits OBX when the
     * payment reaches 'finished' status.
     *
     * @return array{success: bool, message: string, data: BuyCoinHistory|object}
     */
    public function buyCoinWithNowPayments(
        $request,
        float  $coin_amount,
        float  $coin_price_doller,
        mixed  $phase_id,
        mixed  $referral_level,
        float  $phase_fees,
        float  $bonus,
        float  $affiliation_percentage
    ): array {
        $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];

        DB::beginTransaction();
        try {
            // Create a pending record first so we have an ID for the order reference
            $purchase = new BuyCoinHistory();
            $purchase->type             = NOWPAYMENTS;
            $purchase->address          = 'pending';
            $purchase->user_id          = Auth::id();
            $purchase->phase_id         = $phase_id;
            $purchase->referral_level   = $referral_level;
            $purchase->fees             = $phase_fees;
            $purchase->bonus            = $bonus;
            $purchase->referral_bonus   = $affiliation_percentage;
            $purchase->requested_amount = $coin_amount;
            $purchase->coin             = $request->coin;
            $purchase->doller           = $coin_price_doller;
            $purchase->btc              = 0;
            $purchase->coin_type        = strtolower($request->pay_currency ?? 'btc');
            $purchase->status           = STATUS_PENDING;
            $purchase->save();

            // Call NOWPayments API
            $nowPayments = new NowPaymentsService();
            $ipnUrl      = route('nowpayments.ipn');
            $payCurrency = strtolower($request->pay_currency ?? 'btc');

            $npResponse = $nowPayments->createPayment(
                priceAmount:    (float) $coin_price_doller,
                payCurrency:    $payCurrency,
                orderId:        $purchase->id,
                ipnCallbackUrl: $ipnUrl,
                description:    settings('coin_name') . " Purchase #{$purchase->id}"
            );

            if (empty($npResponse['payment_id'])) {
                DB::rollBack();
                return ['success' => false, 'message' => __('NOWPayments did not return a payment address. Please try again.'), 'data' => (object)[]];
            }

            // Persist NOWPayments details
            $purchase->nowpayments_payment_id  = (string) $npResponse['payment_id'];
            $purchase->nowpayments_pay_address = $npResponse['pay_address'] ?? null;
            $purchase->nowpayments_pay_amount  = $npResponse['pay_amount']  ?? null;
            $purchase->nowpayments_pay_currency= $npResponse['pay_currency'] ?? $payCurrency;
            $purchase->address                 = $npResponse['pay_address']  ?? 'pending';
            $purchase->save();

            DB::commit();

            $response = [
                'success' => true,
                'message' => __('Order placed. Please send the exact amount to the address shown.'),
                'data'    => $purchase,
            ];

        } catch (\RuntimeException $e) {
            DB::rollBack();
            Log::error('buyCoinWithNowPayments NowPayments API error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => __('Payment gateway error. Please try again later.'), 'data' => (object)[]];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('buyCoinWithNowPayments exception: ' . $e->getMessage());
            $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];
        }

        return $response;
    }

    /**
     * Record a WalletConnect on-chain purchase initiation.
     *
     * The actual OBX credit happens via the existing PresaleWebhookController
     * once the TokensPurchased event is detected on-chain. This method only
     * creates a pending record so the user can see a "waiting for confirmation"
     * state in their history. The tx_hash is supplied by the frontend after
     * the wallet signs the transaction.
     *
     * @return array{success: bool, message: string, data: BuyCoinHistory|object}
     */
    public function buyCoinWithWalletConnect(
        $request,
        float  $coin_amount,
        float  $coin_price_doller,
        mixed  $phase_id,
        mixed  $referral_level,
        float  $phase_fees,
        float  $bonus,
        float  $affiliation_percentage
    ): array {
        $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];

        DB::beginTransaction();
        try {
            $purchase = new BuyCoinHistory();
            $purchase->type             = WALLETCONNECT;
            $purchase->address          = $request->wc_buyer_address ?? 'N/A';
            $purchase->wc_buyer_address = $request->wc_buyer_address ?? null;
            $purchase->tx_hash          = $request->tx_hash ?? null;
            $purchase->user_id          = Auth::id();
            $purchase->phase_id         = $phase_id;
            $purchase->referral_level   = $referral_level;
            $purchase->fees             = $phase_fees;
            $purchase->bonus            = $bonus;
            $purchase->referral_bonus   = $affiliation_percentage;
            $purchase->requested_amount = $coin_amount;
            $purchase->coin             = $request->coin;
            $purchase->doller           = $coin_price_doller;
            $purchase->btc              = 0;
            $purchase->coin_type        = 'USDT';
            $purchase->status           = STATUS_PENDING;
            $purchase->save();

            DB::commit();

            $response = [
                'success' => true,
                'message' => __('Transaction submitted. Your OBX tokens will be credited once the block is confirmed.'),
                'data'    => $purchase,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('buyCoinWithWalletConnect exception: ' . $e->getMessage());
            $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];
        }

        return $response;
    }

}
    {
        $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];

        DB::beginTransaction();
        try {
            $response['data'] = (object)[];
            $response['success'] = false;
            $response['message'] = __('Invalid operation');

            $coin_payment = new CoinPaymentsAPI();

            $coin_type = isset($request->payment_coin_type) ? $request->payment_coin_type : allsetting('base_coin_type');
            $address = $coin_payment->GetCallbackAddress($coin_type);

            if ( isset($address['error']) && ($address['error'] == 'ok') ) {

                $api_rate = $coin_payment->GetRates('');
                $coin_price_btc = converts_currency($coin_price_doller, $coin_type,$api_rate);

                if ( $address ) {
                    if (isset($coin_price_btc) && $coin_price_btc > 0) {
                        $btc_transaction = new BuyCoinHistory();
                        $btc_transaction->address = $address['result']['address'];
                        $btc_transaction->type = BTC;
                        $btc_transaction->user_id = Auth::id();
                        $btc_transaction->phase_id = $phase_id;
                        $btc_transaction->referral_level = $referral_level;
                        $btc_transaction->fees  = $phase_fees ;
                        $btc_transaction->bonus = $bonus;
                        $btc_transaction->referral_bonus = $affiliation_percentage;
                        $btc_transaction->requested_amount = $coin_amount;
                        $btc_transaction->coin = $request->coin;
                        $btc_transaction->doller = $coin_price_doller;
                        $btc_transaction->btc = $coin_price_btc;
                        $btc_transaction->coin_type = $coin_type;
                        $btc_transaction->save();

                        $response['data'] = $btc_transaction;
                        $response['success'] = true;
                        $response['message'] = __('Order placed successfully');

                        DB::commit();
                    } else {
                        $response['data'] = (object)[];
                        $response['success'] = false;
                        $response['message'] = __('Coin payment not working properly');
                    }
                } else {
                    $response['data'] = (object)[];
                    $response['success'] = false;
                    $response['message'] = __('Coin payment address not generated');
                }
            } else {
                $response['data'] = (object)[];
                $response['success'] = false;
                $response['message'] = __('Coin payment not working properly');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::info('buy coin with coin payment exception '.$e->getMessage());
            $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];
        }

        return $response;
    }



    // buy coin with bank transfer
    public function buyCoinWithBank($request, $coin_amount, $coin_price_doller, $coin_price_btc, $phase_id, $referral_level, $phase_fees, $bonus, $affiliation_percentage)
    {
        $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];

        DB::beginTransaction();
        try {
            $baseCoin = strtoupper(allsetting('base_coin_type')) ?? 'BTC';
            $bank = Bank::where(['status' => STATUS_ACTIVE, 'id' => $request->bank_id])->first();
            if (isset($bank)) {
                $btc_transaction = new BuyCoinHistory();
                $btc_transaction->type = BANK_DEPOSIT;
                $btc_transaction->address = 'N/A';
                $btc_transaction->user_id = Auth::id();
                $btc_transaction->doller = $coin_price_doller;
                $btc_transaction->btc = $coin_price_btc;
                $btc_transaction->phase_id = $phase_id;
                $btc_transaction->referral_level = $referral_level;
                $btc_transaction->fees  = $phase_fees ;
                $btc_transaction->bonus = $bonus;
                $btc_transaction->referral_bonus = $affiliation_percentage;
                $btc_transaction->requested_amount = $coin_amount;
                $btc_transaction->coin = $request->coin;
                $btc_transaction->coin_type = $baseCoin;
                $btc_transaction->bank_id = $request->bank_id;
                $btc_transaction->bank_sleep = uploadFile($request->file('sleep'), IMG_SLEEP_PATH);
                $btc_transaction->save();

                DB::commit();

                $response = ['success' => true, 'message' => __('Request submitted successful,Please wait for admin approval.'), 'data' => $btc_transaction];
            } else {
                $response = ['success' => false, 'message' => __('No active bank found'), 'data' => (object)[]];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info('buy coin with bank exception '.$e->getMessage());
            $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];
        }

        return $response;
    }


    // buy coin with stripe

    public function buyCoinWithStripe($request, $coin_amount, $coin_price_doller, $coin_price_btc, $phase_id, $referral_level, $phase_fees, $bonus, $affiliation_percentage)
    {
        $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];

        try {

            $stripe_secret = '';
            if (!empty(settings()['STRIPE_SECRET'])) {
                $stripe_secret = settings()['STRIPE_SECRET'];
            }

            Stripe::setApiKey($stripe_secret);
            $charge = Charge::create ([
                "amount" => (int)$coin_price_doller * 100,
                "currency" => "usd",
                "source" => $request->stripeToken,
                "description" => "Payment from ".Auth::user()->email. ' for '.$coin_price_doller. ' usd'
            ]);
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage(), 'data' => (object)[]];
            return $response;
        }
        DB::beginTransaction();
        try {
            $baseCoin = strtoupper(allsetting('base_coin_type')) ?? 'BTC';
            if (isset($charge) && $charge['status'] == 'succeeded') {
                $btc_transaction = new BuyCoinHistory();
                $btc_transaction->type = STRIPE;
                $btc_transaction->address = 'N/A';
                $btc_transaction->user_id = Auth::id();
                $btc_transaction->doller = $coin_price_doller;
                $btc_transaction->btc = $coin_price_btc;
                $btc_transaction->phase_id = $phase_id;
                $btc_transaction->referral_level = $referral_level;
                $btc_transaction->fees  = $phase_fees ;
                $btc_transaction->bonus = $bonus;
                $btc_transaction->referral_bonus = $affiliation_percentage;
                $btc_transaction->requested_amount = $coin_amount;
                $btc_transaction->coin = $request->coin;
                $btc_transaction->coin_type = $baseCoin;
                $btc_transaction->stripe_token = $charge['id'];
                $btc_transaction->save();

                DB::commit();
                $response = ['success' => true, 'message' =>  __("Request submitted successful,Please wait for admin approval"), 'data' => $btc_transaction];
            } else {
                $response = ['success' => false, 'message' =>  __("Payment failed"), 'data' => (object)[]];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info('buy coin with stripe exception '.$e->getMessage());
            $response = ['success' => false, 'message' => __('Something went wrong'), 'data' => (object)[]];
        }

        return $response;
    }

}
