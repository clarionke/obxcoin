<?php

namespace App\Http\Controllers\user;

use App\Http\Requests\CoinSwapRequest;
use App\Http\Requests\WalletCreateRequest;
use App\Http\Requests\withDrawRequest;
use App\Http\Services\CommonService;
use App\Http\Services\TransactionService;
use App\Jobs\Withdrawal;
use App\Jobs\CoWalletObxWithdrawal;
use App\Model\Coin;
use App\Model\CoWalletWithdrawApproval;
use App\Model\CoWalletSignatoryChangeApproval;
use App\Model\CoWalletSignatoryChangeRequest;
use App\Model\DepositeTransaction;
use App\Model\TempWithdraw;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use App\Model\WalletCoUser;
use App\Model\WalletSwapHistory;
use App\Model\WithdrawHistory;
use App\Repository\WalletRepository;
use App\Services\BitCoinApiService;
use App\Services\CoinPaymentsAPI;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PragmaRX\Google2FA\Google2FA;


class WalletController extends Controller
{
    public $repo;

    public function __construct()
    {
        $this->repo = new WalletRepository();
    }

    // my wallet
    public function myPocket(Request $request)
    {
        $data['tab'] = $request->tab ?? null;
        $data['wallets'] = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.user_id'=> Auth::id(), 'wallets.type'=> PERSONAL_WALLET, 'coins.status' => STATUS_ACTIVE])
            ->orderBy('id', 'ASC')
            ->select('wallets.*')
            ->get();
        $data['coWallets'] = Wallet::select('wallets.*')
            ->join('wallet_co_users', 'wallet_co_users.wallet_id','=','wallets.id')
            ->join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.type'=> CO_WALLET, 'wallet_co_users.user_id'=>Auth::id(), 'coins.status' => STATUS_ACTIVE])
            ->orderBy('id', 'ASC')->get();
        $data['coins'] = Coin::where('status', STATUS_ACTIVE)->get();
        $data['title'] = __('My Wallet');

        return view('user.pocket.index', $data);
    }

    public function getCoinSwapDetails(Request $request)
    {
        if ($request->ajax()) {
            $wallet = Wallet::find($request->id);
            $data['wallets'] = Coin::select('coins.*', 'wallets.name as wallet_name', 'wallets.id as wallet_id')
                ->join('wallets', 'wallets.coin_type', '=', 'coins.type')
                ->where('coins.status', STATUS_ACTIVE)
                ->where('wallets.user_id', Auth::id())
                ->where('coins.type', '!=', $wallet->coin_type)
                ->where('coins.type', '<>', DEFAULT_COIN_TYPE)
                ->get();
            $html = '';
            $html .= View::make('user.pocket.swap_wallet_list', $data);

            return response()->json($html);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * get rate of coin
     */
    public function getRate(CoinSwapRequest $request)
    {
        $data = $this->repo->get_wallet_rate($request);

        $html = '';
        if ($data['success'] == true) {
            $html .= View::make('user.pocket.swap_data', $data);
        }

        return response()->json($html);
    }

    public function swapCoin(CoinSwapRequest $request)
    {
        $fromWallet = Wallet::where(['id'=>$request->from_coin_id])->first();
        if (!empty($fromWallet) && $fromWallet->type == CO_WALLET)
            return redirect()->back()->with(['dismiss' => __('Something went wrong')]);

        $response = $this->repo->get_wallet_rate($request);
        if ($response['success'] == false) {
            return redirect()->back()->with(['dismiss' => __('Something went wrong')]);
        }
        $swap_coin = $this->repo->coinSwap($response['from_wallet'], $response['to_wallet'], $response['convert_rate'], $response['amount'], $response['rate']);

        if ($swap_coin['success'] == true) {
            return redirect()->back()->with(['success' => $swap_coin['message']]);
        } else {
            return redirect()->back()->with(['dismiss' => $swap_coin['message']]);
        }
    }

    // make default account
    public function makeDefaultAccount($account_id, $coin_type)
    {
        $wallet = Wallet::where(['id'=>$account_id])->first();
        if (!empty($wallet) && $wallet->type == CO_WALLET)
            return redirect()->back()->with(['dismiss' => __('Something went wrong')]);

        Wallet::where(['user_id' => Auth::id(), 'coin_type' => $coin_type])->update(['is_primary' => 0]);
        Wallet::updateOrCreate(['id' => $account_id], ['is_primary' => 1]);

        return redirect()->back()->with('success', __('Default set successfully'));
    }

    public function createWallet(WalletCreateRequest $request)
    {
        if (!empty($request->wallet_name)) {
            $request->type = $request->type ?? PERSONAL_WALLET;
            $coin = Coin::where(['type' => strtoupper($request->coin_type)])->first();
            $alreadyWallet = Wallet::where([
                'coin_id' => $coin->id,
                'user_id' => Auth::id(),
                'type' => $request->type,
            ])->first();
            if ($request->type == PERSONAL_WALLET && $alreadyWallet) {
                return redirect()->back()->with('dismiss', __("You already have this type of wallet"));
            }
            try {
                DB::beginTransaction();
                $wallet = new Wallet();
                $wallet->user_id = Auth::id();
                $wallet->type = $request->type ?? PERSONAL_WALLET;
                $wallet->name = $request->wallet_name;
                $wallet->coin_type = strtoupper($request->coin_type);
                $wallet->status = STATUS_SUCCESS;
                $wallet->balance = 0;
                $wallet->coin_id = $coin->id;
                if (co_wallet_feature_active() && $request->type == CO_WALLET) {
                    $wallet->max_co_users = max(2, (int) $request->max_co_users);
                    if (Schema::hasColumn('wallets', 'approval_timeout_minutes')) {
                        $wallet->approval_timeout_minutes = max(5, (int) ($request->approval_timeout_minutes ?? 60));
                    }
                    if (Schema::hasColumn('wallets', 'team_wallet_uid')) {
                        $teamWalletUid = 'TW-' . strtoupper(Str::random(10));
                        while (Wallet::where('team_wallet_uid', $teamWalletUid)->exists()) {
                            $teamWalletUid = 'TW-' . strtoupper(Str::random(10));
                        }
                        $wallet->team_wallet_uid = $teamWalletUid;
                    }
                    $key = Str::random(64);
                    while (true) {
                        $keyExists = Wallet::where(['key' => $key])->first();
                        if (!empty($keyExists)) $key = Str::random(64);
                        else break;
                    }
                    $wallet->key = $key;
                }
                $wallet->save();

                if (co_wallet_feature_active() && $request->type == CO_WALLET) {
                    WalletCoUser::create([
                        'user_id' => Auth::id(),
                        'wallet_id' => $wallet->id,
                        'can_approve' => 1,
                    ]);
                }
                DB::commit();
                if (co_wallet_feature_active() && $request->type == CO_WALLET) {
                    return redirect()->route('myPocket', ['tab'=>'co-pocket'])->with('success', __("Wallet created successfully"));
                } else {
                    return redirect()->back()->with('success', __("Wallet created successfully"));
                }
            } catch (\Exception $e) {
                Log::alert($e->getMessage());
                DB::rollBack();
                return redirect()->back()->with('dismiss', __("Something went wrong."));
            }
        }
        return redirect()->back()->with('dismiss', __("Wallet name can't be empty"));
    }

    // create new wallet
    public function importWallet(Request $request)
    {
        if (!empty($request->key)) {
            $wallet = Wallet::where(['key' => $request->key, 'status' => STATUS_ACTIVE])->first();
            if (empty($wallet)) return back()->with('dismiss', __('Invalid Key'));

            $alreadyCoUser = WalletCoUser::where(['user_id'=>Auth::id(), 'wallet_id'=>$wallet->id])->first();
            if(!empty($alreadyCoUser)) return back()->with('dismiss', __('Already imported'));

            $maxCoUser = !empty($wallet->max_co_users) ? (int) $wallet->max_co_users : 2;
            $coUserCount = WalletCoUser::where(['wallet_id' => $wallet->id])->count();
            if($coUserCount >= $maxCoUser) return redirect()->back()->with('dismiss', __("Can't import this wallet. Max co user limit reached."));

            try {
                WalletCoUser::create([
                    'user_id' => Auth::id(),
                    'wallet_id' => $wallet->id,
                    'can_approve' => 0,
                ]);
            } catch (\Exception $e) {
                Log::alert($e->getMessage());
                return redirect()->back()->with('dismiss', __("Something went wrong."));
            }

            return redirect()->route('myPocket', ['tab'=>'co-pocket'])->with('success', __("Co-wallet imported successfully"));
        }
        return redirect()->back()->with('dismiss', __("Key can't be empty"));
    }

    // wallet details
    public function walletDetails(Request $request, $id)
    {
        $data['wallet_id'] = $id;
        $data['wallet'] = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.user_id' => Auth::id(), 'coins.status' => STATUS_ACTIVE, 'wallets.id' => $id])
            ->select('wallets.*', 'coins.status as coin_status', 'coins.is_withdrawal', 'coins.minimum_withdrawal',
                'coins.maximum_withdrawal', 'coins.withdrawal_fees')
            ->first();
        //checking if co-wallet
        if(co_wallet_feature_active() && empty($data['wallet'])) {
            $data['wallet'] = Wallet::select('wallets.*')
                ->join('wallet_co_users', 'wallet_co_users.wallet_id', '=', 'wallets.id')
                ->join('coins', 'coins.id', '=', 'wallets.coin_id')
                ->where(['wallets.id' => $id, 'wallets.type' => CO_WALLET, 'wallet_co_users.user_id' => Auth::id(), 'coins.status' => STATUS_ACTIVE])
                ->first();


            $data['ac_tab'] = $request->has('ac_tab') ? $request->ac_tab : null;
        }
        if(empty($data['wallet'])) return back();
        if (co_wallet_feature_active()) {
            $data['tempWithdraws'] = TempWithdraw::where(['wallet_id' => $id, 'status' => STATUS_PENDING])
                ->when(Schema::hasColumn('temp_withdraws', 'expires_at'), function ($q) {
                    return $q->where(function ($query) {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
                })
                ->orderBy('id', 'desc')
                ->get();
        }

        $exists = WalletAddressHistory::where('wallet_id',$id)->orderBy('created_at','desc')->first();
        $data['histories'] = DepositeTransaction::where('receiver_wallet_id', $id)->orderBy('id','desc')->get();
        $data['withdraws'] = WithdrawHistory::where('wallet_id', $id)->orderBy('id','desc')->get();
        $data['active'] = $request->q;
        $data['ac_tab'] = $request->q;
        $data['title'] = $request->q;
        $wallet = Wallet::find($id);
        if ($wallet->coin_type == DEFAULT_COIN_TYPE){
            $repo = new WalletRepository();
            $repo->generateTokenAddress($data['wallet']->id);
            $data['wallet_address'] = WalletAddressHistory::where('wallet_id',$id)->orderBy('created_at','desc')->first();

            return view('user.pocket.default_wallet_details', $data);
        }
        $data['address'] = (!empty($exists)) ? $exists->address : get_coin_payment_address($data['wallet']->coin_type);
        if (!empty($data['address'])) {
            if (empty($exists)) {
                $history = new \App\Services\wallet();
                $history->AddWalletAddressHistory($id, $data['address'], $data['wallet']->coin_type);
            }
            $data['address_histories'] = WalletAddressHistory::where('wallet_id', $id)->paginate(10);

            return view('user.pocket.wallet_details', $data);
        }

        return redirect()->back()->with('dismiss', __('Wallet address not found.'));
    }

    // generate new wallet address
    public function generateNewAddress(Request $request)
    {
        try {
            $wallet = new \App\Services\wallet();
            $myWallet = Wallet::where(['id' => $request->wallet_id, 'user_id' => Auth::id()])->first();

            if ($myWallet) {
                if ($myWallet->coin_type == DEFAULT_COIN_TYPE) {
                    $repo = new WalletRepository();
                    $response = $repo->generateTokenAddress($myWallet->id);
                    if ($response['success'] == true) {
                        return redirect()->back()->with('success', $response['message']);
                    } else {
                        return redirect()->back()->with('dismiss', $response['message']);
                    }
                } else {
                    $address = get_coin_payment_address($myWallet->coin_type);
                    if (!empty($address)) {
                        $wallet->AddWalletAddressHistory($request->wallet_id, $address, $myWallet->coin_type);
                        return redirect()->back()->with(['success' => __('Address generated successfully')]);
                    } else {
                        return redirect()->back()->with(['dismiss' => __('Address not generated ')]);
                    }
                }
            } else {
                return redirect()->back()->with(['dismiss'=>__('Wallet not found')]);
            }

        } catch (\Exception $e) {
            Log::error('generateNewAddress: ' . $e->getMessage());
            return redirect()->back()->with('dismiss', __('Address generation failed.'));
        }
    }

    // generate qr code
    public function qrCodeGenerate(Request $request)
    {
        $image = QRCode::text($request->address)->png();
        return response($image)->header('Content-type', 'image/png');
    }

    // withdraw balance
    public function WithdrawBalance(withDrawRequest $request)
    {
        $transactionService = new TransactionService();

        $wallet = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
            ->where(['wallets.id'=>$request->wallet_id, 'wallets.user_id'=>Auth::id()])
            ->select('wallets.*', 'coins.status as coin_status', 'coins.is_withdrawal', 'coins.minimum_withdrawal',
                'coins.maximum_withdrawal', 'coins.withdrawal_fees')
            ->first();

        //checking if co-wallet
        if(co_wallet_feature_active() && empty($wallet)) {
            $wallet = Wallet::join('wallet_co_users', 'wallet_co_users.wallet_id', '=', 'wallets.id')
                ->join('coins', 'coins.id', '=', 'wallets.coin_id')
                ->select('wallets.*','coins.status as coin_status', 'coins.is_withdrawal', 'coins.minimum_withdrawal',
                'coins.maximum_withdrawal', 'coins.withdrawal_fees')
                ->where(['wallets.id' => $request->wallet_id, 'wallets.type' => CO_WALLET, 'wallet_co_users.user_id' => Auth::id()])
                ->first();
        }
        $address = $request->address;
        $user = Auth::user();
        if ($request->ajax()) {
            if(empty($wallet)) return response()->json(['success'=>false,'message'=> __('Wallet not found.')]);
            if ($wallet->balance >= $request->amount) {
                $checkValidate = $transactionService->checkWithdrawalValidation( $request, $user, $wallet);

                if ($checkValidate['success'] == false) {
                    return response()->json(['success' => $checkValidate['success'], 'message' => $checkValidate['message']]);
                }
                $checkKyc = $transactionService->kycValidationCheck($user->id);

                if ($checkKyc['success'] == false) {
                    return response()->json(['success' => $checkKyc['success'], 'message' => $checkKyc['message']]);
                }
                return response()->json(['success' => true]);

            } else {
                return response()->json(['success' => false, 'message' => __('Wallet has no enough balance')]);
            }

        } else {
            if(empty($wallet)) return redirect()->back()->with('dismiss', __('Wallet not found.'));
            $checkValidate = $transactionService->checkWithdrawalValidation( $request, $user, $wallet);

            if ($checkValidate['success'] == false) {
                return redirect()->back()->with('dismiss', $checkValidate['message']);
            }
            $checkKyc = $transactionService->kycValidationCheck($user->id);
            if ($checkKyc['success'] == false) {
                return redirect()->back()->with('dismiss', $checkKyc['message']);
            }

            $google2fa = new Google2FA();
            if (empty($request->code)) {
                return redirect()->back()->with('dismiss', __('Verify code is required'));
            }
            $valid = $google2fa->verifyKey($user->google2fa_secret, $request->code);

            $data = $request->all();
            $data['user_id'] = Auth::id();
            $request = new Request();
            $request = $request->merge($data);

            if ($valid) {
                if ($wallet->balance >= $request->amount) {
                    try {
                        if ($wallet->type == PERSONAL_WALLET) {
                            dispatch(new Withdrawal($request->all()))->onQueue('withdrawal');
                            return redirect()->back()->with('success', __('Withdrawal placed successfully'));

                        } else if (co_wallet_feature_active() && $wallet->type == CO_WALLET) {
                            DB::beginTransaction();
                            $expiresAt = null;
                            if (Schema::hasColumn('temp_withdraws', 'expires_at')) {
                                $timeoutMinutes = max(5, (int) ($wallet->approval_timeout_minutes ?? 60));
                                $expiresAt = Carbon::now()->addMinutes($timeoutMinutes);
                            }
                            $tempWithdraw = TempWithdraw::create([
                                'user_id' => $user->id,
                                'wallet_id' => $wallet->id,
                                'amount' => $request->amount,
                                'address' => $request->address,
                                'message' => $request->message,
                                'expires_at' => $expiresAt,
                            ]);

                            CoWalletWithdrawApproval::create([
                                'temp_withdraw_id' => $tempWithdraw->id,
                                'wallet_id' => $wallet->id,
                                'user_id' => $user->id
                            ]);
                            DB::commit();

                            if ($transactionService->isAllApprovalDoneForCoWalletWithdraw($tempWithdraw)['success']) {
                                $this->dispatchCoWalletWithdrawal($wallet, $tempWithdraw->toArray());
                                return redirect()->back()->with('success', __('Withdrawal placed successfully'));
                            }
                            return redirect()->back()->with('success', __('Process successful. Need other co users approval.'));
                        } else {
                            return redirect()->back()->with('dismiss', __('Invalid wallet type.'));
                        }

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error($e->getMessage());
                        return redirect()->back()->with('dismiss', __('Something went wrong.'));
                    }
                } else
                    return redirect()->back()->with('dismiss', __('Wallet has no enough balance'));
            } else
                return redirect()->back()->with('dismiss', __('Google two factor authentication is invalid'));
        }
    }

    //check internal address
    private function isInternalAddress($address)
    {
        return WalletAddressHistory::where('address', $address)->with('wallet')->first();
    }

    // transaction history
    public function transactionHistories(Request $request)
    {
        if ($request->ajax()) {
            $tr = new TransactionService();
            if ($request->type == 'deposit') {
                $histories = $tr->depositTransactionHistories(Auth::id())->get();
            } else {
                $histories = $tr->withdrawTransactionHistories(Auth::id())->get();
            }
            return datatables($histories)
                ->addColumn('address', function ($item) {
                    return $item->address;
                })
                ->addColumn('amount', function ($item) {
                    return $item->amount;
                })
                ->addColumn('hashKey', function ($item) use ($request) {
                    if ($request->type == 'deposit')
                        return (!empty($item)) ? $item->transaction_id : '';
                    else
                        return (!empty($item)) ? $item->transaction_hash : '';
                })
                ->addColumn('status', function ($item) {
                    return statusAction($item->status);
                })
                ->rawColumns(['user'])
                ->make(true);
        }
    }

    // withdraw rate
    public function withdrawCoinRate(Request $request)
    {
        if ($request->ajax()) {
            $data['amount'] = isset($request->amount) ? $request->amount : 0;
            $wallet = Wallet::find($request->wallet_id);
            $data['coin_type'] = $wallet->coin_type;

            $data['coin_price'] = bcmul(settings('coin_price'), $request->amount, 8);
            $coinpayment = new CoinPaymentsAPI();
            $api_rate = $coinpayment->GetRates('');

            $data['btc_dlr'] = converts_currency($data['coin_price'], $data['coin_type'], $api_rate);
            $data['btc_dlr'] = custom_number_format($data['btc_dlr']);

            return response()->json($data);
        }
    }

    // coin swap history
    public function coinSwapHistory(Request $request)
    {
        if ($request->ajax()) {
            $list = WalletSwapHistory::where(['user_id' => Auth::id()])->get();

            return datatables($list)
                ->editColumn('from_wallet_id', function ($item) {
                    return $item->fromWallet->name;
                })
                ->editColumn('to_wallet_id', function ($item) {
                    return $item->toWallet->name;
                })
                ->editColumn('requested_amount', function ($item) {
                    return $item->requested_amount . ' ' . check_default_coin_type($item->from_coin_type);
                })
                ->editColumn('converted_amount', function ($item) {
                    return $item->converted_amount . ' ' . check_default_coin_type($item->to_coin_type);
                })
                ->make(true);
        }

        return view('user.pocket.swap_history');
    }

    // coin swap
    public function coinSwap()
    {
        $data['title'] = __('Coin Swap');
        $data['wallets'] = Wallet::where(['user_id' => Auth::id()])->where('coin_type', '<>', DEFAULT_COIN_TYPE)->get();

        return view('user.pocket.coin_swap', $data);
    }


    //co wallet users
    public function coWalletUsers(Request $request) {
        $data['title'] = __('Co-wallet Users');
        $data['wallet'] = Wallet::select('wallets.*')
            ->join('wallet_co_users', 'wallet_co_users.wallet_id','=','wallets.id')
            ->where(['wallets.id'=>$request->id, 'wallets.type'=> CO_WALLET, 'wallet_co_users.user_id'=>Auth::id()])
            ->first();
        if(empty($data['wallet'])) return back();

        $data['co_users'] = $data['wallet']->co_users;
        $memberCount = WalletCoUser::where(['wallet_id' => $data['wallet']->id, 'status' => STATUS_ACTIVE])->count();
        $data['required_signatory_minimum'] = $memberCount >= 3 ? 3 : 2;
        $data['required_signatory_change_approvals'] = $this->requiredSignatoryChangeApprovals($data['wallet']->id);
        $data['signatory_requests'] = CoWalletSignatoryChangeRequest::where([
                'wallet_id' => $data['wallet']->id,
                'status' => STATUS_PENDING,
            ])
            ->orderByDesc('id')
            ->get();

        $data['is_approver'] = WalletCoUser::where([
            'wallet_id' => $data['wallet']->id,
            'user_id' => Auth::id(),
            'status' => STATUS_ACTIVE,
            'can_approve' => 1,
        ])->exists();

        return view('user.pocket.co_users', $data);
    }

    // creator adds a co-user by email
    public function addCoWalletUser(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|max:191',
            'can_approve' => 'nullable|in:0,1',
        ]);

        $wallet = Wallet::where([
            'id' => $id,
            'type' => CO_WALLET,
            'user_id' => Auth::id(),
        ])->first();

        if (empty($wallet)) {
            return redirect()->back()->with('dismiss', __('Only the wallet creator can add co-users.'));
        }

        $targetUser = User::where('email', $request->email)->first();
        if (empty($targetUser)) {
            return redirect()->back()->with('dismiss', __('User not found for this email.'));
        }

        $alreadyCoUser = WalletCoUser::where([
            'wallet_id' => $wallet->id,
            'user_id' => $targetUser->id,
        ])->first();
        if (!empty($alreadyCoUser)) {
            return redirect()->back()->with('dismiss', __('This user is already a co-user of this wallet.'));
        }

        $maxCoUser = !empty($wallet->max_co_users) ? (int) $wallet->max_co_users : 2;
        $coUserCount = WalletCoUser::where(['wallet_id' => $wallet->id])->count();
        if ($coUserCount >= $maxCoUser) {
            return redirect()->back()->with('dismiss', __('Cannot add more co-users. Max co-user limit reached.'));
        }

        try {
            WalletCoUser::create([
                'wallet_id' => $wallet->id,
                'user_id' => $targetUser->id,
                'status' => STATUS_ACTIVE,
                'can_approve' => (int) ($request->can_approve ?? 0),
            ]);

            return redirect()->back()->with('success', __('Co-user added successfully.'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong.'));
        }
    }

    private function requiredSignatoryChangeApprovals(int $walletId): int
    {
        $totalMembers = WalletCoUser::where([
            'wallet_id' => $walletId,
            'status' => STATUS_ACTIVE,
        ])->count();

        $oneThirdRule = max(1, (int) ceil($totalMembers / 3));

        $adminMinimum = (int) settings(CO_WALLET_SIGNATORY_CHANGE_MIN_APPROVALS_SLUG);
        $adminMinimum = $adminMinimum > 0 ? $adminMinimum : 2;

        return max($oneThirdRule, $adminMinimum);
    }

    private function enforceMinimumSignatoryCount(int $walletId, int $futureSignatoryCount): bool
    {
        $totalMembers = WalletCoUser::where([
            'wallet_id' => $walletId,
            'status' => STATUS_ACTIVE,
        ])->count();

        $minimumSignatories = $totalMembers >= 3 ? 3 : 2;
        return $futureSignatoryCount >= $minimumSignatories;
    }

    // creator sets whether a co-user can approve transactions
    public function setCoWalletUserApprover(Request $request, $id, $coUserId)
    {
        $request->validate([
            'can_approve' => 'required|in:0,1',
        ]);

        $wallet = Wallet::where([
            'id' => $id,
            'type' => CO_WALLET,
            'user_id' => Auth::id(),
        ])->first();
        if (empty($wallet)) {
            return redirect()->back()->with('dismiss', __('Only wallet creator can assign signatories.'));
        }

        $coUser = WalletCoUser::where([
            'id' => $coUserId,
            'wallet_id' => $wallet->id,
        ])->first();
        if (empty($coUser)) {
            return redirect()->back()->with('dismiss', __('Invalid co-user.'));
        }

        if ((int) $request->can_approve === 0 && (int) $coUser->can_approve === 1) {
            $approverCount = WalletCoUser::where([
                'wallet_id' => $wallet->id,
                'status' => STATUS_ACTIVE,
                'can_approve' => 1,
            ])->count();

            if (!$this->enforceMinimumSignatoryCount($wallet->id, max(0, $approverCount - 1))) {
                return redirect()->back()->with('dismiss', __('Minimum required signatories would be violated.'));
            }
        }

        if ((int) $request->can_approve === 1 && (int) $coUser->can_approve === 0) {
            // Allowed, and helps satisfy group minimum signatory requirements.
        }

        $coUser->can_approve = (int) $request->can_approve;
        $coUser->save();

        return redirect()->back()->with('success', __('Signatory permission updated.'));
    }

    // co-wallet approver approves a signatory change request
    public function approveCoWalletSignatoryChange(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $changeRequest = CoWalletSignatoryChangeRequest::where('id', $id)
                ->where('status', STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (empty($changeRequest)) {
                DB::rollBack();
                return redirect()->back()->with('dismiss', __('Invalid or already processed signatory request.'));
            }

            $isApprover = WalletCoUser::where([
                'wallet_id' => $changeRequest->wallet_id,
                'user_id' => Auth::id(),
                'status' => STATUS_ACTIVE,
                'can_approve' => 1,
            ])->exists();

            if (!$isApprover) {
                DB::rollBack();
                return redirect()->back()->with('dismiss', __('Only assigned signatories can approve this request.'));
            }

            $exists = CoWalletSignatoryChangeApproval::where([
                'request_id' => $changeRequest->id,
                'user_id' => Auth::id(),
            ])->exists();

            if (!$exists) {
                CoWalletSignatoryChangeApproval::create([
                    'request_id' => $changeRequest->id,
                    'wallet_id' => $changeRequest->wallet_id,
                    'user_id' => Auth::id(),
                ]);
            }

            $approverCount = WalletCoUser::where([
                'wallet_id' => $changeRequest->wallet_id,
                'status' => STATUS_ACTIVE,
                'can_approve' => 1,
            ])->count();
            $requiredApproval = $this->requiredSignatoryChangeApprovals($changeRequest->wallet_id);

            if ($approverCount < $requiredApproval) {
                DB::rollBack();
                return redirect()->back()->with('dismiss', __('Not enough assigned signatories to satisfy approval threshold.'));
            }

            $approvedCount = CoWalletSignatoryChangeApproval::where('request_id', $changeRequest->id)
                ->distinct('user_id')
                ->count('user_id');

            if ($approvedCount >= $requiredApproval) {
                $targetCoUser = WalletCoUser::where('id', $changeRequest->target_wallet_co_user_id)
                    ->where('wallet_id', $changeRequest->wallet_id)
                    ->first();

                if (!empty($targetCoUser)) {
                    if ((int) $changeRequest->requested_can_approve === 0 && (int) $targetCoUser->can_approve === 1) {
                        $currentApproverCount = WalletCoUser::where([
                            'wallet_id' => $changeRequest->wallet_id,
                            'status' => STATUS_ACTIVE,
                            'can_approve' => 1,
                        ])->count();

                        if (!$this->enforceMinimumSignatoryCount($changeRequest->wallet_id, max(0, $currentApproverCount - 1))) {
                            DB::rollBack();
                            return redirect()->back()->with('dismiss', __('Minimum required signatories would be violated.'));
                        }
                    }

                    $targetCoUser->can_approve = $changeRequest->requested_can_approve;
                    $targetCoUser->save();
                }

                $changeRequest->status = STATUS_ACCEPTED;
                $changeRequest->save();
            }

            DB::commit();
            return redirect()->back()->with('success', __('Signatory change approval recorded successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong.'));
        }
    }

    //co wallet withdraw approval list
    public function coWalletApprovals(Request $request) {
        $data['title'] = __('Withdraw Approvals');
        $data['tempWithdraw'] = TempWithdraw::where(['status'=>STATUS_PENDING, 'id'=>$request->id])->first();
        if(empty($data['tempWithdraw'])) return redirect()->route('myPocket', ['tab'=>'co-pocket']);
        if ((new TransactionService())->isTempWithdrawExpired($data['tempWithdraw'])) {
            $data['tempWithdraw']->status = STATUS_REJECTED;
            $data['tempWithdraw']->save();
            return redirect()->route('myPocket', ['tab'=>'co-pocket'])
                ->with('dismiss', __('Withdrawal approval window expired. Request cancelled automatically.'));
        }
        $response = (new TransactionService())->approvalCounts($data['tempWithdraw']);
        $data['total_required_approval'] = $response['requiredUserApprovalCount'];
        $data['approved_count'] = $response['alreadyApprovedUserCount'];
        $data['wallet'] = Wallet::select('wallets.*')
            ->join('wallet_co_users', 'wallet_co_users.wallet_id','=','wallets.id')
            ->where(['wallets.id'=>$data['tempWithdraw']->wallet_id, 'wallets.type'=> CO_WALLET, 'wallet_co_users.user_id'=>Auth::id()])
            ->first();
        if(empty($data['wallet'])) return redirect()->route('myPocket', ['tab'=>'co-pocket']);

        $data['co_users'] = WalletCoUser::select(DB::raw('wallet_co_users.*,
                            (CASE WHEN wallet_co_users.user_id=co_wallet_withdraw_approvals.user_id THEN '
            .STATUS_ACCEPTED.' ELSE '.STATUS_PENDING.' END) approved'))
            ->leftJoin('co_wallet_withdraw_approvals', function ($join) use ($data) {
                $join->on('wallet_co_users.wallet_id', '=', 'co_wallet_withdraw_approvals.wallet_id')
                    ->on('wallet_co_users.user_id', '=', 'co_wallet_withdraw_approvals.user_id')
                    ->on('co_wallet_withdraw_approvals.temp_withdraw_id','=', DB::raw($data['tempWithdraw']->id));
            })
            ->where('wallet_co_users.wallet_id', $data['wallet']->id)
            ->get();
        return view('user.pocket.co_approvals', $data);
    }

    //approve co wallet withdraw
    public function approveCoWalletWithdraw(Request $request, $id) {
        DB::beginTransaction();
        try {
            $tempWithdraw = TempWithdraw::where(['status' => STATUS_PENDING, 'id' => $id])
                ->lockForUpdate()
                ->first();
            if (empty($tempWithdraw)) {
                DB::rollBack();
                return redirect()->route('myPocket', ['tab' => 'co-pocket'])->with('dismiss', __('Invalid withdrawal.'));
            }

            if ((new TransactionService())->isTempWithdrawExpired($tempWithdraw)) {
                $tempWithdraw->status = STATUS_REJECTED;
                $tempWithdraw->save();
                DB::commit();
                return redirect()->route('myPocket', ['tab' => 'co-pocket'])->with('dismiss', __('Withdrawal approval window expired. Request cancelled automatically.'));
            }

            $wallet = Wallet::select('wallets.*')
                ->join('wallet_co_users', 'wallet_co_users.wallet_id', '=', 'wallets.id')
                ->where([
                    'wallets.id' => $tempWithdraw->wallet_id,
                    'wallets.type' => CO_WALLET,
                    'wallet_co_users.user_id' => Auth::id(),
                    'wallet_co_users.status' => STATUS_ACTIVE,
                    'wallet_co_users.can_approve' => 1,
                ])
                ->first();
            if (empty($wallet)) {
                DB::rollBack();
                return redirect()->route('walletDetails', [$tempWithdraw->wallet_id, 'q' => 'activity', 'ac_tab' => 'co-withdraw'])
                    ->with('dismiss', __('Only assigned signatories can approve this withdrawal.'));
            }

            $userAlreadyApproved = CoWalletWithdrawApproval::where([
                'temp_withdraw_id' => $tempWithdraw->id,
                'user_id' => Auth::id(),
            ])->exists();

            if (!$userAlreadyApproved) {
                CoWalletWithdrawApproval::create([
                    'temp_withdraw_id' => $tempWithdraw->id,
                    'wallet_id' => $wallet->id,
                    'user_id' => Auth::id(),
                ]);
            }

            $approvalCheck = (new TransactionService())->isAllApprovalDoneForCoWalletWithdraw($tempWithdraw);
            DB::commit();

            if ($approvalCheck['success']) {
                $wallet = Wallet::find($tempWithdraw->wallet_id);
                $this->dispatchCoWalletWithdrawal($wallet, $tempWithdraw->toArray());
                return redirect()->route('myPocket', ['tab' => 'co-pocket'])->with('success', __('All approval done and withdrawal placed successfully.'));
            }

            return back()->with('success', __('Approved successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->route('walletDetails', [$id, 'q' => 'activity', 'ac_tab' => 'co-withdraw'])
                ->with('dismiss', __('Something went wrong.'));
        }
    }

    /**
     * Dispatch the appropriate withdrawal job for a co-wallet based on coin type.
     * DEFAULT_COIN_TYPE (OBXCoin) uses on-chain transfer via BlockchainService (burns 0.05%).
     * Other coin types use the standard off-chain Withdrawal job.
     */
    private function dispatchCoWalletWithdrawal(?Wallet $wallet, array $tempWithdrawData): void
    {
        if ($wallet && strcasecmp((string) $wallet->coin_type, DEFAULT_COIN_TYPE) === 0) {
            dispatch(new CoWalletObxWithdrawal($tempWithdrawData))->onQueue('withdrawal');
            TempWithdraw::where('id', $tempWithdrawData['id'] ?? 0)->update(['status' => STATUS_SUCCESS]);
        } else {
            dispatch(new Withdrawal($tempWithdrawData))->onQueue('withdrawal');
        }
    }

    //reject co wallet withdraw by withdraw requester
    public function rejectCoWalletWithdraw(Request $request, $id) {
        $tempWithdraw = TempWithdraw::where(['status'=>STATUS_PENDING, 'id'=>$id, 'user_id'=> Auth::id()])->first();
        if(empty($tempWithdraw)) return redirect()->route('myPocket', ['tab'=>'co-pocket'])->with('dismiss', __('Invalid withdrawal.'));

        try {
            $tempWithdraw->status = STATUS_REJECTED;
            $tempWithdraw->save();
            return redirect()->route('myPocket', ['tab'=>'co-pocket'])->with('success', __('Withdraw rejected successfully.'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->route('walletDetails', [$tempWithdraw->wallet_id, 'q'=> 'activity', 'ac_tab'=>'co-withdraw'])
                ->with('dismiss', __('Something went wrong.'));
        }

    }


}
