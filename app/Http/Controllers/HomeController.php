<?php

namespace App\Http\Controllers;

use App\Model\Coin;
use App\Model\ContactUs;
use App\Model\CustomPage;
use App\Model\DepositeTransaction;
use App\Model\Faq;
use App\Model\IcoPhase;
use App\Model\StakingPool;
use App\Model\StakingPosition;
use App\Model\WithdrawHistory;
use App\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    // landing home page
    public function home()
    {
        $data['content']      = allsetting();
        $data['custom_links'] = CustomPage::orderBy('data_order', 'asc')->get();
        $data['faqs']         = Faq::where('status', 1)->orderBy('created_at', 'desc')->get();
        $data['coins']        = Coin::where('status', 1)->orderBy('created_at', 'desc')->get();

        // Platform statistics
        $data['stats'] = [
            'total_users'        => User::count(),
            'total_coins'        => Coin::where('status', 1)->count(),
            'total_transactions' => DepositeTransaction::count() + WithdrawHistory::count(),
            'total_staked'       => (float) StakingPosition::where('status', STAKING_STATUS_ACTIVE)->sum('net_amount'),
        ];

        // Live OBX price from admin settings (updated by FetchCMCPrice command)
        $data['obx_price']  = (float) (settings('coin_price') ?: 0);
        $data['obx_change'] = (float) (settings('obx_price_change_24h') ?: 0);

        // Active staking pools
        $data['staking_pools'] = StakingPool::where('status', STATUS_ACTIVE)
            ->orderByDesc('apy_bps')
            ->get();

        // Active ICO phase (if any)
        $data['active_phase'] = IcoPhase::where('status', STATUS_ACTIVE)
            ->orderBy('start_date', 'asc')
            ->first();

        return view('landing.landing', $data);
    }

    // custom page
    public function getCustomPage($id,$key){
        $data['content'] = allsetting();
        $data['custom_links'] = CustomPage::orderBy('data_order','asc')->get();
        $data['item'] = CustomPage::find($id);

        return view('landing.custom_page',$data);
    }

    public function contactUs(Request $request){
        $validatedData = $request->validate([
           'name'=>'required',
           'email'=>'required|email',
           'phone'=>'required',
           'address'=>'required',
           'description' => 'required',
            'g-recaptcha-response' => isset(allsetting()['google_recapcha']) && (allsetting()['google_recapcha'] == STATUS_ACTIVE) ? 'required|captcha' : ''
        ]);

        $data = [
            'name'=> $request->name,
            'email'=> $request->email,
            'phone' => $request->phone,
            'address'=> $request->address,
            'description'=> $request->description
        ];
        try{
            ContactUs::create($data);
            return redirect()->back()->with('success', __("Contact form submitted successfully"));
        }catch (\Exception $e){
            return redirect()->back()->with('dismiss',$e->getMessage());
        }
    }

}
