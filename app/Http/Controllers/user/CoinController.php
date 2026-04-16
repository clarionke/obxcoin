<?php

namespace App\Http\Controllers\user;

use App\Http\Requests\btcDepositeRequest;
use App\Http\Services\TransactionService;
use App\Model\BuyCoinHistory;
use App\Model\BuyCoinReferralHistory;
use App\Model\Coin;
use App\Model\CoinRequest;
use App\Model\DepositeTransaction;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Model\WithdrawHistory;
use App\Repository\AffiliateRepository;
use App\Repository\CoinRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Model\IcoPhase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class CoinController extends Controller
{
    private function resolvedPresaleContract(): string
    {
        return trim((string) (settings('presale_contract') ?: config('blockchain.presale_contract', '')));
    }

    private function explorerBaseUrl(): string
    {
        $chainId = (int)(settings('walletconnect_chain_id') ?: settings('presale_chain_id') ?: settings('chain_id') ?: config('blockchain.presale_chain_id', 56));

        return match ($chainId) {
            1   => 'https://etherscan.io',
            56  => 'https://bscscan.com',
            97  => 'https://testnet.bscscan.com',
            137 => 'https://polygonscan.com',
            default => 'https://bscscan.com',
        };
    }

    // buy coin
    public function buyCoinPage()
    {
        try {
            $data['title']    = __('Buy Coin');
            $data['settings'] = allsetting();

            $data['coin_price'] = settings('coin_price');

            $activePhases = checkAvailableBuyPhase();

            $data['no_phase'] = false;
            if ($activePhases['status'] == false) {
                $data['no_phase'] = true;
            } else {
                if ($activePhases['futurePhase'] == false) {
                    $phase_info = $activePhases['pahse_info'];
                    if (isset($phase_info)) {
                        $data['coin_price'] = number_format($phase_info->rate, 4);
                    }
                }
            }
            $data['activePhase'] = $activePhases;

            // Production buy flow: NOWPayments (processor) then backend credits OBX wallet.
            $data['nowpayments_enabled']  = (int) (settings('nowpayments_enabled') ?? 0) === 1;
            $data['walletconnect_enabled']= $this->resolvedPresaleContract() !== '';
            $data['wc_project_id']        = settings('walletconnect_project_id') ?? '';
            $data['wc_chain_id']          = (int)(settings('walletconnect_chain_id') ?? 56);
            $data['presale_contract']     = $this->resolvedPresaleContract();
            $data['usdt_address']         = config('blockchain.usdt_addresses.' . $data['wc_chain_id'], '');
            // OBX token details for wallet_watchAsset — read from Admin > OBXCoin Send Settings
            // so the admin can update them without touching .env
            $data['obx_token_contract']   = settings('contract_address')  ?: config('blockchain.obx_token_contract', '');
            $data['obx_token_decimals']   = (int)(settings('contract_decimal') ?: 18);
            $data['obx_token_symbol']     = settings('coin_name')          ?: 'OBX';
            $data['obx_token_logo_url']   = settings('obx_token_logo_url') ?: '';

            $data['gasless_enabled']      = (int) (settings('gasless_enabled') ?? 0) === 1;
            $data['gasless_quote_url']    = url('/api/gasless/quote');
            $data['gasless_sponsor_url']  = url('/api/gasless/sponsor');
            $data['gasless_actions']      = settings('gasless_allowed_actions') ?: 'buy,stake,unstake,unlock,transfer,withdraw';

            return view('user.buy_coin.index', $data);
        } catch (\Exception $e) {
            Log::info('buy coin page: ' . $e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong. Please try again.'));
        }
    }

    public function buyCoinRate(Request $request)
    {
        if ($request->ajax()) {
            $data['amount']   = $request->amount ?? 0;
            $data['no_phase'] = false;
            $data['phase_fees'] = 0;
            $data['bonus']    = 0;

            $coin_price   = settings('coin_price');
            $activePhases = checkAvailableBuyPhase();

            if ($activePhases['status'] == false) {
                $data['no_phase'] = true;
            } elseif ($activePhases['futurePhase'] == false) {
                $phase_info = $activePhases['pahse_info'];
                if (isset($phase_info)) {
                    $coin_price           = customNumberFormat($phase_info->rate);
                    $data['phase_fees']   = customNumberFormat(calculate_phase_percentage($data['amount'], $phase_info->fees));
                    $data['bonus']        = calculate_phase_percentage($data['amount'], $phase_info->bonus);
                    $data['amount']       = $data['amount'] - $data['bonus'];
                }
            }

            $data['coin_price'] = customNumberFormat(bcmul($coin_price, $data['amount'], 8));

            return response()->json($data);
        }
    }


    // buy coin process
    public function buyCoin(btcDepositeRequest $request)
    {
        try {
            $coinRepo = new CoinRepository();

            $phase_fees             = 0;
            $affiliation_percentage = 0;
            $bonus                  = 0;
            $coin_amount            = (float) $request->coin;
            $phase_id               = '';
            $referral_level         = '';
            $coin_price_doller      = bcmul($coin_amount, settings('coin_price'), 8);

            if (isset($request->phase_id)) {
                $phase = IcoPhase::where('id', $request->phase_id)->first();
                if (isset($phase)) {
                    $total_sell = BuyCoinHistory::where('phase_id', $phase->id)->sum('coin');
                    if (($total_sell + $coin_amount) > $phase->amount) {
                        return redirect()->back()->with('dismiss', __('Insufficient phase amount'));
                    }
                    $phase_id           = $phase->id;
                    $referral_level     = $phase->affiliation_level;
                    $phase_fees         = calculate_phase_percentage($request->coin, $phase->fees);
                    $affiliation_percentage = 0;
                    $bonus              = calculate_phase_percentage($request->coin, $phase->bonus);
                    $coin_amount        = $request->coin - $bonus;
                    $coin_price_doller  = bcmul($coin_amount, $phase->rate, 8);
                }
            }

            if ((int) $request->payment_type === NOWPAYMENTS) {
                if ((int) (settings('nowpayments_enabled') ?? 0) !== 1) {
                    return redirect()->back()->with('dismiss', __('NOWPayments is currently disabled.'));
                }

                $result = $coinRepo->buyCoinWithNowPayments(
                    $request, $coin_amount, (float) $coin_price_doller,
                    $phase_id, $referral_level, $phase_fees, $bonus, $affiliation_percentage
                );

                if ($result['success']) {
                    return redirect()
                        ->route('buyCoinByAddress', $result['data']->nowpayments_payment_id ?: $result['data']->id)
                        ->with('success', $result['message']);
                }

                return redirect()->back()->with('dismiss', $result['message']);
            }

            if ((int) $request->payment_type === WALLETCONNECT) {
                return redirect()->back()->with('dismiss', __('Please use NOWPayments for purchase.'));
            }

            return redirect()->back()->with('dismiss', __('Invalid payment method selected.'));

        } catch (\Exception $e) {
            Log::error('buyCoin: ' . $e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong'));
        }
    }

    //bank details
    public function bankDetails(Request $request)
    {
        $data = ['success' => false, 'message' => __('Invalid request'), 'data_genetare'=> ''];
        $data_genetare = '';
        if(isset($request->val)) {
            $bank = Bank::where('id', $request->val)->first();
            if (isset($bank)) {
                $data_genetare = '<h3 class="text-center">'.__('Bank Details').'</h3><table class="table">';
                $data_genetare .= '<tr><td>'.__("Bank Name").' :</td> <td>'.$bank->bank_name.'</td></tr>';
                $data_genetare .= '<tr><td>'.__("Account Holder Name").' :</td> <td>'.$bank->account_holder_name.'</td></tr>';
                $data_genetare .= '<tr><td>'.__("Bank Address").' :</td> <td>'.$bank->bank_address.'</td></tr>';
                $data_genetare .= '<tr><td>'.__("Country").' :</td> <td>'.country($bank->country).'</td></tr>';
                $data_genetare .= '<tr><td>'.__("IBAN").' :</td> <td>'.$bank->iban.'</td></tr>';
                $data_genetare .= '<tr><td>'.__("Swift Code").' :</td> <td>'.$bank->swift_code.'</td></tr>';
                $data_genetare .= '</table>';
                $data['data_genetare'] = $data_genetare;
                $data['success'] = true;
                $data['message'] = __('Data get successfully.');
            }
        }

        return response()->json($data);
    }

    // coin payment success / pending page
    public function buyCoinByAddress($address)
    {
        $data['title'] = __('Payment Details');

        // Look up by numeric DB id
        if (is_numeric($address)) {
            $coinAddress = BuyCoinHistory::where(['user_id' => Auth::id(), 'id' => $address])->first();
        } else {
            // NOWPayments payment_id (UUID string)
            $coinAddress = BuyCoinHistory::where(['user_id' => Auth::id(), 'nowpayments_payment_id' => $address])->first();
            // Fallback: plain address
            if (!$coinAddress) {
                $coinAddress = BuyCoinHistory::where(['user_id' => Auth::id(), 'address' => $address])->first();
            }
        }

        if (isset($coinAddress)) {
            $data['coinAddress'] = $coinAddress;
            return view('user.buy_coin.payment_success', $data);
        }
        return redirect()->back()->with('dismiss', __('Payment record not found'));
    }

    // buy coin history
    public function buyCoinHistory(Request $request)
    {
        $data['title'] = __('Buy Coin History');
        if ($request->ajax()) {
            $items = BuyCoinHistory::where(['user_id'=>Auth::id()]);
            return datatables($items)
                ->addColumn('type', function ($item) {
                    return byCoinType($item->type);
                })
                ->addColumn('status', function ($item) {
                    return deposit_status($item->status);
                })
                ->addColumn('tx_hash', function ($item) {
                    return $item->tx_hash ?? '';
                })
                ->addColumn('tx_url', function ($item) {
                    $hash = trim((string)($item->tx_hash ?? ''));
                    if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $hash)) {
                        return '';
                    }

                    return rtrim($this->explorerBaseUrl(), '/') . '/tx/' . $hash;
                })
                ->rawColumns(['status', 'type'])
                ->make(true);
        }

        return view('user.buy_coin.buy_history', $data);
    }

    // buy coin history
    public function buyCoinReferralHistory(Request $request)
    {
        $data['title'] = __('Buy Coin referral History');
        if ($request->ajax()) {
            $items = BuyCoinReferralHistory::where(['user_id'=>Auth::id(), 'status' => STATUS_ACTIVE]);
            return datatables($items)
                ->addColumn('created_at', function ($item) {
                    return $item->created_at;
                })
                ->addColumn('status', function ($item) {
                    return deposit_status($item->status);
                })
                ->addColumn('wallet_id', function ($item) {
                    return check_default_coin_type($item->wallet->coin_type);
                })
                ->make(true);
        }

        return view('user.buy_coin.buy_referral_history', $data);
    }

    // give or request coin
    public function requestCoin(Request $request)
    {
        $data['title'] = __('Request or Give Coin ');
        $data['wallets'] = Wallet::where('user_id', Auth::id())
            ->whereRaw('LOWER(coin_type) = ?', [strtolower(DEFAULT_COIN_TYPE)])
            ->get();
        $data['coin'] = Coin::whereRaw('LOWER(type) = ?', [strtolower(DEFAULT_COIN_TYPE)])->first();
        $data['qr'] = (!empty($request->qr)) ? $request->qr : 'requests';

        return view ('user.request_coin.coin_request', $data);
    }


    // send coin request
    public function sendCoinRequest(Request $request)
    {
        $coin = Coin::whereRaw('LOWER(type) = ?', [strtolower(DEFAULT_COIN_TYPE)])->first();
        $rules = [
            'email' => 'required|exists:users,email',
            'amount' => ['required','numeric','min:'.$coin->minimum_withdrawal,'max:'.$coin->maximum_withdrawal]
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $errors = [];
            $e = $validator->errors()->all();
            foreach ($e as $error) {
                $errors[] = $error;
            }
            $data['message'] = $errors;
            return redirect()->route('requestCoin', ['qr' => 'requests'])->with(['dismiss' => $errors[0]]);
        }

        try {
            $response = app(CoinRepository::class)->sendCoinAmountRequest($request);
            if ($response['success'] == true) {
                return redirect()->route('requestCoin', ['qr' => 'requests'])->with('success', $response['message']);
            } else {
                return redirect()->route('requestCoin', ['qr' => 'requests'])->withInput()->with('success', $response['message']);
            }
        } catch(\Exception $e) {
            Log::error('sendCoinRequest: ' . $e->getMessage());
            return redirect()->back()->with(['dismiss' => __('Something went wrong. Please try again.')]);
        }
    }

    // send coin request
    public function giveCoin(Request $request)
    {
        $coin = Coin::whereRaw('LOWER(type) = ?', [strtolower(DEFAULT_COIN_TYPE)])->first();
        $rules = [
            'wallet_id' => 'required|exists:wallets,id',
            'amount' => ['required','numeric','min:'.$coin->minimum_withdrawal,'max:'.$coin->maximum_withdrawal],
            'email' => 'required|exists:users,email'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $errors = [];
            $e = $validator->errors()->all();
            foreach ($e as $error) {
                $errors[] = $error;
            }
            $data['message'] = $errors;
            return redirect()->route('requestCoin', ['qr' => 'give'])->with(['dismiss' => $errors[0]]);
        }

        try {
            $response = app(CoinRepository::class)->giveCoinToUser($request);
            if ($response['success'] == true) {
                return redirect()->route('requestCoin', ['qr' => 'give'])->with('success', $response['message']);
            } else {
                return redirect()->route('requestCoin', ['qr' => 'give'])->withInput()->with('success', $response['message']);
            }
        } catch(\Exception $e) {
            Log::error('giveCoin: ' . $e->getMessage());
            return redirect()->back()->with(['dismiss' => __('Something went wrong. Please try again.')]);
        }
    }

    // send coin history
    public function giveCoinHistory(Request $request)
    {
        $data['title'] = __('Send Coin History');
        if ($request->ajax()) {
            $items = CoinRequest::where(['sender_user_id'=>Auth::id()]);
            return datatables($items)
                ->editColumn('sender_user_id', function ($item) {
                    return $item->sender->email;
                })
                ->editColumn('coin_type', function ($item) {
                    return settings('coin_name');
                })
                ->editColumn('receiver_user_id', function ($item) {
                    return $item->receiver->email;
                })
                ->editColumn('status', function ($item) {
                    return deposit_status($item->status);
                })
                ->make(true);
        }

        return view('user.request_coin.coin_give_history', $data);
    }


    // send coin history
    public function receiveCoinHistory(Request $request)
    {
        $data['title'] = __('Received Coin History');
        if ($request->ajax()) {
            $items = CoinRequest::where(['receiver_user_id'=>Auth::id()]);
            return datatables($items)
                ->editColumn('sender_user_id', function ($item) {
                    return $item->sender->email;
                })
                ->editColumn('coin_type', function ($item) {
                    return settings('coin_name');;
                })
                ->editColumn('receiver_user_id', function ($item) {
                    return $item->receiver->email;
                })
                ->editColumn('status', function ($item) {
                    return deposit_status($item->status);
                })
                ->make(true);
        }

        return view('user.request_coin.coin_receive_history', $data);
    }

    // pending request coin history
    public function pendingRequest(Request $request)
    {
        $data['title'] = __('Pending Request');
        if ($request->ajax()) {
            $items = CoinRequest::where(['sender_user_id'=>Auth::id(), 'status'=> STATUS_PENDING]);
            return datatables($items)
                ->editColumn('receiver_user_id', function ($item) {
                    return $item->receiver->email;
                })
                ->editColumn('coin_type', function ($item) {
                    return settings('coin_name');;
                })
                ->addColumn('action', function ($wdrl) {
                    $action = '<ul>';
                    $action .= accept_html('acceptCoinRequest',encrypt($wdrl->id));
                    $action .= reject_html('declineCoinRequest',encrypt($wdrl->id));
                    $action .= '<ul>';
                    return $action;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('user.request_coin.coin_pending_request', $data);
    }

    // coin request accept process

    public function acceptCoinRequest($id)
    {
        try {
            $request_id = decrypt($id);
        } catch (\Exception $e) {
            return redirect()->back();
        }
        try {
            $response = app(CoinRepository::class)->acceptCoinRequest($request_id);
            if ($response['success'] == true) {
                return redirect()->back()->with('success', $response['message']);
            } else {
                return redirect()->back()->withInput()->with('success', $response['message']);
            }
        } catch(\Exception $e) {
            Log::error('acceptCoinRequest: ' . $e->getMessage());
            return redirect()->back()->with(['dismiss' => __('Something went wrong. Please try again.')]);
        }
    }

    // pending coin reject process
    public function declineCoinRequest($id)
    {
        if (isset($id)) {
            try {
                $request_id = decrypt($id);
            } catch (\Exception $e) {
                return redirect()->back();
            }
            try {
                $response = app(CoinRepository::class)->rejectCoinRequest($request_id);
                if ($response['success'] == true) {
                    return redirect()->back()->with('success', $response['message']);
                } else {
                    return redirect()->back()->withInput()->with('success', $response['message']);
                }
            } catch(\Exception $e) {
                Log::error('declineCoinRequest: ' . $e->getMessage());
                return redirect()->back()->with(['dismiss' => __('Something went wrong. Please try again.')]);
            }

        }
    }

    // withdrawal coin history
    public function defaultWithdrawalHistory(Request $request)
    {
        $data['title'] = __('Withdrawal Coin History');
        if ($request->ajax()) {
            $items =WithdrawHistory::where('user_id',Auth::id());
            return datatables($items)

                ->editColumn('status', function ($item) {
                    return deposit_status($item->status);
                })
                ->make(true);
        }

        return view('user.withdrawal_coin.withdrawal_history', $data);
    }

    // when withdrawal failed then it should be called
    public function withdrawalCancelCallback(Request $request)
    {
        Log::info('withdrawalCancelCallback called');
        DB::beginTransaction();
        try {
            $temp = WithdrawHistory::find($request->temp_id);
            if ($temp) {
                $temp->delete();

                DB::commit();
                $data['success'] = true;
                $data['message'] = __('Withdrawal cancelled');
                Log::info('Default Withdrawal cancelled');
            } else {
                $this->logger->log('withdrawalCancelCallback','temp withdrawal not found '.$request->temp_id);
                $data['success'] = false;
                $data['message'] = __('Temp withdrawal not found');
            }
        } catch (\Exception $exception) {
            $this->logger->log('withdrawalCancelCallback',$exception->getMessage());
            DB::rollBack();
            $data['success'] = false;
            $data['message'] = __('Something went wrong');
        }
        return response()->json($data);
    }

    // OBXCoin deposit
    public function depositCallback(Request $request)
    {

        Log::info('call deposit wallet');
        $data = ['success'=>false,'message'=>'something went wrong'];

        DB::beginTransaction();
        try {
            $request = (object)$request;
            Log::info(json_encode($request));

            $walletAddress = DepositeTransaction::where('transaction_id', $request->transactionHash)->first();
            $wallet = Wallet::where("user_id",Auth::id())->where("coin_type", DEFAULT_COIN_TYPE)->first();

            if (empty($walletAddress) && !empty($wallet)) {

                //    $wallet =  $walletAddress->wallet;
                $data['user_id'] = $wallet->user_id;
                if (!empty($wallet)){
                    $checkDeposit = DepositeTransaction::where('transaction_id', $request->transactionHash)->first();
                    if (isset($checkDeposit)) {
                        $data = ['success'=>false,'message'=>'Transaction id already exists in deposit'];
                        Log::info('Transaction id already exists in deposit');
                        return $data;
                    }
                    $depositData = [
                        'address' => $request->from,
                        'address_type' => ADDRESS_TYPE_EXTERNAL,
                        'amount' => $request->value,
                        'fees' => 0,
                        'doller' => $request->transactionIndex * settings('coin_price'),
                        'btc' => 0,
                        'type' => $wallet->coin_type,
                        'transaction_id' => $request->transactionHash,
                        'confirmations' => $request->blockNumber,
                        'status' => STATUS_SUCCESS,
                        'receiver_wallet_id' => $wallet->id
                    ];

                    $depositCreate = DepositeTransaction::create($depositData);
                    Log::info(json_encode($depositCreate));

                    if (($depositCreate)) {
                        Log::info('Balance before deposit '.$wallet->balance);
                        $wallet->increment('balance', $depositCreate->amount);

                        Log::info('Balance after deposit '.$wallet->balance);
                        $data['message'] = 'Deposited successfully';
                        $data['hash'] = $request->transactionHash;
                        $data['success'] = true;
                    } else {
                        Log::info('Deposit not created ');
                        $data['message'] = 'Deposit not created';
                        $data['success'] = false;
                    }

                } else {
                    $data = ['success'=>false,'message'=>'No wallet found'];
                    Log::info('No wallet found');
                }
            }

            DB::commit();
            return $data;
        } catch (\Exception $e) {
            $data['message'] = __('Something went wrong. Please try again.');
            Log::error('checkBalance: ' . $e->getMessage() . ' line ' . $e->getLine());
            DB::rollback();

            return $data;
        }
    }

    // withdrawal coin
    public function withdrawalCoin(Request $request)
    {
        $data['title'] = __('Withdrawal Coin ');
        $data['wallet'] = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.user_id' => Auth::id(), 'wallets.coin_type' => DEFAULT_COIN_TYPE])
            ->select('wallets.*', 'coins.status as coin_status', 'coins.is_withdrawal', 'coins.minimum_withdrawal',
                'coins.maximum_withdrawal', 'coins.withdrawal_fees')
            ->first();
        $data['qr'] = (!empty($request->qr)) ? $request->qr : 'requests';

        return view ('user.withdrawal_coin.withdrawal_coin', $data);
    }

    // withdrawal callback
    public function callback(Request $request)
    {
        try {
            $temp = WithdrawHistory::find($request->temp);
            $temp->status = STATUS_ACCEPTED;
            $temp->transaction_hash = $request->hash;
            $temp->save();

            $wallet = Wallet::find($temp->wallet_id);
            $deductAmount = $temp->amount + $temp->fees;
            $wallet->decrement('balance', $deductAmount);

            $data['success'] = true;
            $data['message'] = __('Withdrawal is now completed');
            $data['hash'] = $request->hash;
        }catch (\Exception $exception){
            $data['success'] = false;
            $data['message'] = __('Something went wrong');
        }
        return response()->json($data);

    }

    // check default balance
    public function checkBalance($balance,Request $request)
    {
        Log::info('withdrawal balance '. $balance);
        Log::info(json_encode($request->all()));
        $transactionService = new TransactionService();
        $wallet = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.user_id' => Auth::id(), 'wallets.coin_type' => DEFAULT_COIN_TYPE])
            ->select('wallets.*', 'coins.status as coin_status', 'coins.is_withdrawal', 'coins.minimum_withdrawal',
                'coins.maximum_withdrawal', 'coins.withdrawal_fees')
            ->first();
        $user = Auth::user();
        if ($wallet) {
            $data['success'] = $balance < $wallet->balance;
            Log::info('with bl '. $balance);
            Log::info('wallet bl '. $wallet->balance);
            if ($data['success']){
                $checkKyc = $transactionService->kycValidationCheck($user->id);
                if ($checkKyc['success'] == false) {
                    return response()->json(['success' => $checkKyc['success'], 'message' => $checkKyc['message']]);
                }
                $checkValidate = $transactionService->checkWithdrawalValidation( $request, $user, $wallet);
                Log::info(json_encode($checkValidate));
                if ($checkValidate['success'] == false) {
                    return response()->json(['success' => $checkValidate['success'], 'message' => $checkValidate['message']]);
                } else {
                    $result = $transactionService->sendChainExternal($wallet->id,$request->address,$request->amount);
                    Log::info('withdrawal result '.json_encode($result));
                    if ($result['success']){
                        // Return only what the frontend JS needs to complete the transaction
                        // NEVER expose private keys, admin wallet address, or contract credentials to the browser
                        $data['cl']      = allsetting('chain_link');
                        $data['ca']      = allsetting('contract_address');
                        $data['chain_link'] = allsetting('chain_link');
                        $data['success'] = true;
                        $data['message'] = $result['message'];
                        $data['temp']    = $result['temp'];
                        Log::info('chain '.json_encode($data));
                        return $data;
                    } else {
                        return response()->json($result);
                    }
                }
            }else{
                $data['message'] = __("Amount can't be more then available balance");
                $data['success'] = false;
                Log::info(json_encode($data));
                return response()->json($data);
            }
        } else {
            $data['message'] = __("Wallet not found");
            $data['success'] = false;
            Log::info(($data));
            return response()->json($data);
        }
    }
}
