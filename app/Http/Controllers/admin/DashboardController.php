<?php

namespace App\Http\Controllers\admin;

use App\Http\Requests\resetPasswordRequest;
use App\Http\Requests\UserProfileUpdate;
use App\Http\Services\AuthService;
use App\Http\Services\CommonService;
use App\Jobs\SendMail;
use App\Model\ActivityLog;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use App\Model\BuyCoinHistory;
use App\Model\DepositeTransaction;
use App\Model\MembershipBonusDistributionHistory;
use App\Model\MembershipClub;
use App\Model\Wallet;
use App\Model\WithdrawHistory;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ERC20TokenApi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        $data['title'] = __('Admin Dashboard');
        $data['total_income'] = WithdrawHistory::sum('fees');
        $data['total_coin'] = Wallet::sum('balance');
        $data['total_sold_coin'] = BuyCoinHistory::sum('coin');
        $data['total_blocked_coin'] = MembershipClub::sum('amount');
        $data['total_member'] = MembershipClub::where('status',STATUS_ACTIVE)->count();
        $data['bonus_distribution'] = MembershipBonusDistributionHistory::where('status',STATUS_ACTIVE)->sum('bonus_amount');
        $data['total_user'] = User::count();
        $data['presale_start_block'] = (int) (settings('presale_start_block') ?: 0);
        $total_active_user = User::where('status', STATUS_ACTIVE)->count();
        $total_inactive_user = User::where('status','<>', STATUS_ACTIVE)->count();
        if ($data['total_user'] > 0) {
            $data['active_percentage'] = ($total_active_user * 100) / $data['total_user'];
            $data['inactive_percentage'] = ($total_inactive_user * 100) / $data['total_user'];
        } else {
            $data['active_percentage'] = 0;
            $data['inactive_percentage'] = 0;
        }

        $today = Carbon::today();
        $last24HoursStart = Carbon::now()->subDay();

        $data['total_usdt_paid'] = BuyCoinHistory::where('status', STATUS_SUCCESS)->sum('doller');
        $data['today_usdt_paid'] = BuyCoinHistory::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->sum('doller');
        $data['last_24h_usdt_paid'] = BuyCoinHistory::where('status', STATUS_SUCCESS)
            ->where('created_at', '>=', $last24HoursStart)
            ->sum('doller');
        $data['successful_buy_count'] = BuyCoinHistory::where('status', STATUS_SUCCESS)->count();

        $data['today_buy_activity_count'] = BuyCoinHistory::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->count();
        $data['today_buy_activity_usdt'] = BuyCoinHistory::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->sum('doller');
        $data['today_deposit_activity_count'] = DepositeTransaction::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->count();
        $data['today_deposit_activity_amount'] = DepositeTransaction::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->sum('amount');
        $data['today_withdraw_activity_count'] = WithdrawHistory::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->count();
        $data['today_withdraw_activity_amount'] = WithdrawHistory::where('status', STATUS_SUCCESS)
            ->whereDate('created_at', $today)
            ->sum('amount');
        $data['activity_last_24h_count'] = ActivityLog::where('created_at', '>=', $last24HoursStart)->count();
        $data['recent_user_activities'] = ActivityLog::with('user:id,first_name,last_name,email')
            ->orderByDesc('id')
            ->limit(12)
            ->get();
        $data['user_activity_labels'] = userActivity();

        $data['total_airdrop_campaigns'] = AirdropCampaign::count();
        $data['live_airdrop_campaigns'] = AirdropCampaign::where('is_active', true)
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>', Carbon::now())
            ->count();
        $data['total_airdrop_participants'] = AirdropClaim::distinct('user_id')->count('user_id');
        $data['total_airdrop_claimed_obx'] = AirdropClaim::sum('amount_obx');
        $data['total_airdrop_confirmed_unlock_users'] = AirdropUnlock::where('status', 'confirmed')
            ->distinct('user_id')
            ->count('user_id');
        $data['total_airdrop_pending_unlocks'] = AirdropUnlock::where('status', 'pending')->count();
        $data['total_airdrop_usdt_paid'] = AirdropUnlock::where('status', 'confirmed')->sum('usdt_paid');

        $allMonths = all_months();

        // usdt paid from successful buys
        $monthlyUsdtPaid = BuyCoinHistory::select(DB::raw('sum(doller) as totalUsdPaid'), DB::raw('MONTH(created_at) as months'))
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', STATUS_SUCCESS)
            ->groupBy('months')
            ->get();

        if (isset($monthlyUsdtPaid[0])) {
            foreach ($monthlyUsdtPaid as $paid) {
                $data['usdt_paid'][$paid->months] = $paid->totalUsdPaid;
            }
        }

        $allUsdtPaid = [];
        foreach ($allMonths as $month) {
            $allUsdtPaid[] = isset($data['usdt_paid'][$month]) ? $data['usdt_paid'][$month] : 0;
        }
        $data['monthly_usdt_paid'] = $allUsdtPaid;

        // deposit
        $monthlyDeposits = DepositeTransaction::select(DB::raw('sum(amount) as totalDepo'), DB::raw('MONTH(created_at) as months'))
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', STATUS_SUCCESS)
            ->groupBy('months')
            ->get();

        if (isset($monthlyDeposits[0])) {
            foreach ($monthlyDeposits as $depsit) {
                $data['deposit'][$depsit->months] = $depsit->totalDepo;
            }
        }
        $allDeposits = [];
        foreach ($allMonths as $month) {
            $allDeposits[] =  isset($data['deposit'][$month]) ? $data['deposit'][$month] : 0;
        }
        $data['monthly_deposit'] = $allDeposits;

        // withdrawal
        $monthlyWithdrawals = WithdrawHistory::select(DB::raw('sum(amount) as totalWithdraw'), DB::raw('MONTH(created_at) as months'))
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', STATUS_SUCCESS)
            ->groupBy('months')
            ->get();

        if (isset($monthlyWithdrawals[0])) {
            foreach ($monthlyWithdrawals as $withdraw) {
                $data['withdrawal'][$withdraw->months] = $withdraw->totalWithdraw;
            }
        }
        $allWithdrawal = [];
        foreach ($allMonths as $month) {
            $allWithdrawal[] =  isset($data['withdrawal'][$month]) ? $data['withdrawal'][$month] : 0;
        }
        $data['monthly_withdrawal'] = $allWithdrawal;

        return view('admin.dashboard', $data);
    }

    // admin profile
    public function adminProfile(Request $request)
    {
        $data['title'] = __('Profile');
        $data['tab']='profile';
        $data['user']= User::where('id', Auth::id())->first();
        $data['settings'] = allsetting();

        return view('admin.profile.index',$data);
    }

    // update user profile
    public function UserProfileUpdate(UserProfileUpdate $request)
    {
        if (strpos($request->phone, '+') !== false) {
            return redirect()->back()->with('dismiss',__("Don't put plus sign with phone number"));
        }
        if(!isset($request->email))
        {
            return redirect()->back()->with('dismiss',__("Email must be required"));
        }
        $data['first_name'] = $request->first_name;
        $data['last_name'] = $request->last_name;
        $data['email'] = $request->email;
        $user = (!empty($request->id)) ? User::find(decrypt($request->id)) : Auth::user();

        if ($user->phone != $request->phone){
            $data['phone'] =  $request->phone;
            $data['phone_verified'] = null;
        }
        $user->update($data);

        return redirect()->back()->with('success',__('Profile updated successfully'));
    }

    // profile upload image
    public function uploadProfileImage(Request $request)
    {
        $rules['file_one'] = 'required|image|max:2024|mimes:jpg,jpeg,png,jpg,gif,svg|max:2048|dimensions:max_width=500,max_height=500';
        $validator = Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            $message = $validator->getMessageBag()->getMessages()['file_one'][0];
            if ($message == 'The file one has invalid image dimensions.')
                $message =  __('Image size must be less than (height:500,width:500)');

            return redirect()->back()->with('dismiss',$message);
        }
        try {
            $img = $request->file('file_one');
            $user_data = (!empty($request->id) ) ? User::find(decrypt($request->id)) : Auth::user();

            if ($img !== null) {
                $photo = uploadFile($img, IMG_USER_PATH, !empty($user_data->photo) ? $user_data->photo : '');
                $user = User::find($user_data->id);
                $user->photo  = $photo;
                $user->save();
                return redirect()->back()->with('success',__('Profile picture uploaded successfully'));
            } else {
                return redirect()->back()->with('dismiss',__('Please input a image'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('dismiss', $e->getMessage());
        }

    }

    public function changePasswordSave(resetPasswordRequest $request)
    {
        $service = new AuthService();
        $change = $service->changePassword($request);
        if ($change['success']) {
            return redirect()->back()->with('success',$change['message']);
        } else {
            return redirect()->back()->with('dismiss',$change['message']);
        }
    }

    // send email
    public function sendEmail()
    {
        $data['title'] = __('Send Email');

        return view('admin.notification.email', $data);
    }

    //send notification
    public function sendNotification()
    {
        $data['title'] = __('Send Notification');

        return view('admin.notification.notification', $data);
    }

    // send mail process
    public function sendEmailProcess(Request $request)
    {
        $rules = [
            'subject' => 'required',
            'email_message' => 'required',
            'email_type' => 'required'
        ];
        $messages = [
            'subject.required' => __('Subject field can not be empty'),
            'email_message.required' => __('Message field can not be empty'),
            'email_type.required' => __('Email type field can not be empty'),
        ];
        $validator = Validator::make( $request->all(), $rules, $messages );
        if ($validator->fails()) {
            return redirect()->back()->withInput()->with(['dismiss' => $validator->errors()->first() ]);
        } else {
            $data['subject'] = $request->subject;
            $data['email_message'] = $request->email_message;
            $data['type'] = $request->email_type;
            $data['mailTemplate'] = 'email.genericemail';

            if (!empty($request->email_headers)) {
                $data['email_header'] = $request->email_headers;
            }
            if (!empty($request->footers)) {
                $data['email_footer'] = $request->footers;
            }

//            app(CommonService::class)->sendEmailToAlUser($data);
            dispatch(new SendMail($data))->onQueue('send-email');

            return redirect()->back()->with('success',__('Mail sent successfully'));
        }
    }

    // send notification process
    public function sendNotificationProcess(Request $request)
    {
        $rules = [
            'title' => 'required',
            'notification_body' => 'required',
        ];

        $messages = [
            'title.required' => 'Notification title can not be empty',
            'notification_body.required' => 'Notification body can not be empty',
        ];

        $this->validate($request, $rules, $messages);

        $service = new CommonService();
        try {
            $response = $service->sendNotificationProcess($request);
            return redirect()->back()->with(['success' => 'Notification sent successfully']);

        } catch (\Exception $exception) {
            return redirect()->back()->with(['dismiss' => 'Something went wrong. Please try again']);
        }
    }

    // test
    public function adminTest()
    {
        // $api = new ERC20TokenApi();
        // $address = "0x1D6B26a5D73DCA565Ca0c77d3837D8df1F88222c";
        // $requestData = array(
        //         "type" => 3,
        //         "address" => '0x0F4eE4DcAceE91aA62f672DFf6780f9F03c1AF9b',
        //     );
        // $result = $api->checkWalletBalance($requestData);
        // $settings = allsetting();
        // $coinApi = new ERC20TokenApi();
        // $requestData = [
        //     "amount_value" => 1,
        //     "from_address" => $settings['wallet_address'] ?? '',
        //     "to_address" => $address,
        //     "contracts" => $settings['private_key'] ?? ''
        // ];
        // $result2 = $api->sendCustomToken($requestData);
        // dd($result,$result2);
    }
}
