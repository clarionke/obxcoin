<?php

namespace App\Http\Controllers\user;

use App\Model\AffiliationCode;
use App\Model\ReferralSignBonusHistory;
use App\Model\BuyCoinReferralHistory;
use App\Repository\AffiliateRepository;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    protected $affiliateRepository;

    public function __construct(AffiliateRepository $affiliateRepository)
    {
        $this->affiliateRepository = $affiliateRepository;
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();
    }

    /*
     * myReferral
     *
     */

    public function myReferral()
    {
        $data['title'] = __('My Referral');
        $data['user'] = Auth::user();
        if (!$data['user']->affiliate) {
            $created = $this->affiliateRepository->create($data['user']->id);
            if ($created < 1) {
                return redirect()->back()->with(['dismiss' => __('Failed to generate new referral code.')]);
            }
            $data['user'] = $data['user']->fresh();
        }

        $data['url'] = url('') . '/referral-reg?ref_code=' . $data['user']->affiliate->code;

        $maxReferralLevel = min(max_level(), AffiliateRepository::MAX_REFERRAL_LEVELS);
        $referralQuery = $this->affiliateRepository->childrenReferralQuery($maxReferralLevel);

        $referralAll = $referralQuery['referral_all']->where('ru1.parent_id', $data['user']->id)
            ->select('ru1.parent_id', DB::raw($referralQuery['select_query']))
            ->first();

        for ($i = 0; $i < $maxReferralLevel; $i++) {
            $level = 'level' . ($i + 1);
            $data['referralLevel'][($i + 1)] = $referralAll->{$level} ?? 0;
        }

        $downlineTree = $this->affiliateRepository->getDownlineTree($data['user']->id, $maxReferralLevel);
        $data['referrals'] = $this->flattenDownlineTree($downlineTree);

        $data['select'] = 'affiliate';
        $data['max_referral_level'] = $maxReferralLevel;

        $monthlyEarningData = $this->buildMonthlyReferralEarnings((int) $data['user']->id);
        $affiliationKeys = array_flip(array_keys($monthlyEarningData));
        $data['monthArray'] = $affiliationKeys;
        $data['monthlyEarningHistories'] = $monthlyEarningData;

        return view('user.referral.index', $data);
    }

    public function myReferralTree()
    {
        $user = Auth::user();
        if (!$user->affiliate) {
            $created = $this->affiliateRepository->create($user->id);
            if ($created < 1) {
                return redirect()->back()->with(['dismiss' => __('Failed to generate new referral code.')]);
            }
            $user = Auth::user()->fresh();
        }

        $maxReferralLevel = min(max_level(), AffiliateRepository::MAX_REFERRAL_LEVELS);
        $data['title'] = __('Referral Tree');
        $data['user'] = $user;
        $data['menu'] = 'referral';
        $data['sub_menu'] = 'referral_tree';
        $data['max_referral_level'] = $maxReferralLevel;
        $data['url'] = url('') . '/referral-reg?ref_code=' . $user->affiliate->code;
        $data['upline'] = $this->affiliateRepository->getUpline($user->id, $maxReferralLevel);
        $data['downline_tree'] = $this->affiliateRepository->getDownlineTree($user->id, $maxReferralLevel);

        return view('user.referral.tree', $data);
    }

    public function __destruct()
    {
        config()->set('database.connections.mysql.strict', true);
        DB::reconnect();
    }

    /*
     * signup
     *
     * It's for referral signup.
     *
     *
     *
     *
     */

    public function signup(Request $request)
    {
        $code = $request->get('ref_code');

        if ($code) {
            $parentUser = AffiliationCode::where('code', $code)->first();
            if ($parentUser) {
                return view('auth.signup');
            } else {
                return redirect()->route('signUp')->with('dismiss', __('Invalid referral code.'));
            }
        }

        return redirect()->route('signUp')->with('dismiss', __('Invalid referral code.'));
    }

    // my referral earning
    public function myReferralEarning(Request $request)
    {
        $data['title'] = __('My referral Earning History');
        if ($request->ajax()) {
            $signupItems = ReferralSignBonusHistory::where('parent_id', Auth::id())->get()->map(function ($item) {
                $child = User::find($item->user_id);

                return (object) [
                    'source' => __('Signup'),
                    'created_at' => $item->created_at,
                    'child_id' => $child ? trim($child->first_name . ' ' . $child->last_name) : (string) $item->user_id,
                    'amount' => $item->amount,
                    'coin_type' => find_coin_type(DEFAULT_COIN_TYPE),
                    'status' => deposit_status($item->status ?? STATUS_ACTIVE),
                ];
            });

            $buyItems = BuyCoinReferralHistory::where(['user_id' => Auth::id(), 'status' => STATUS_ACTIVE])->get()->map(function ($item) {
                $child = User::find($item->child_id);

                return (object) [
                    'source' => __('Presale'),
                    'created_at' => $item->created_at,
                    'child_id' => $child ? trim($child->first_name . ' ' . $child->last_name) : (string) $item->child_id,
                    'amount' => $item->amount,
                    'coin_type' => find_coin_type(DEFAULT_COIN_TYPE),
                    'status' => deposit_status($item->status),
                ];
            });

            $items = $signupItems->concat($buyItems)->sortByDesc('created_at')->values();

            return datatables()->of($items)
                ->make(true);
        }

        return view('user.referral.earning_history', $data);
    }

    private function flattenDownlineTree(array $tree): array
    {
        $rows = [];

        foreach ($tree as $node) {
            $rows[] = [
                'id' => $node['user']->id,
                'full_name' => trim($node['user']->first_name . ' ' . $node['user']->last_name),
                'email' => $node['user']->email,
                'joining_date' => $node['user']->created_at,
                'level' => __('Level') . ' ' . $node['level'],
            ];

            $rows = array_merge($rows, $this->flattenDownlineTree($node['children']));
        }

        return $rows;
    }

    private function buildMonthlyReferralEarnings(int $userId): array
    {
        $signupRows = ReferralSignBonusHistory::query()
            ->where('parent_id', $userId)
            ->get(['created_at', 'amount']);

        $buyRows = BuyCoinReferralHistory::query()
            ->where('user_id', $userId)
            ->where('status', STATUS_ACTIVE)
            ->get(['created_at', 'amount']);

        $signupMonthly = [];
        foreach ($signupRows as $row) {
            $month = date('Y-m', strtotime((string) $row->created_at));
            $signupMonthly[$month] = (float) ($signupMonthly[$month] ?? 0) + (float) ($row->amount ?? 0);
        }

        $buyMonthly = [];
        foreach ($buyRows as $row) {
            $month = date('Y-m', strtotime((string) $row->created_at));
            $buyMonthly[$month] = (float) ($buyMonthly[$month] ?? 0) + (float) ($row->amount ?? 0);
        }

        $allMonths = array_unique(array_merge(array_keys($signupMonthly), array_keys($buyMonthly)));
        rsort($allMonths);

        $result = [];
        foreach ($allMonths as $month) {
            $signup = (float) ($signupMonthly[$month] ?? 0);
            $buy = (float) ($buyMonthly[$month] ?? 0);
            $result[$month] = [
                'year_month' => $month,
                'total_amount' => $signup + $buy,
            ];
        }

        return $result;
    }
}
