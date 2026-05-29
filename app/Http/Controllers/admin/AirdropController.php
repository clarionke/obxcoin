<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use App\Model\AdminSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AirdropController extends Controller
{
    // ─── Campaign list ────────────────────────────────────────────────────────

    public function index()
    {
        $data['title']      = __('Airdrop Campaigns');
        $data['menu']       = 'airdrop';
        $data['sub_menu']   = 'airdrop_list';
        $data['campaigns']  = AirdropCampaign::latest()->get();

        return view('admin.airdrop.index', $data);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'airdrop_withdraw_pay_currency' => 'required|string|max:30|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        AdminSetting::updateOrCreate(
            ['slug' => AIRDROP_WITHDRAW_ENABLED_SLUG],
            ['value' => $request->has('airdrop_withdraw_enabled') ? '1' : '0']
        );

        AdminSetting::updateOrCreate(
            ['slug' => AIRDROP_WITHDRAW_PAY_CURRENCY_SLUG],
            ['value' => strtolower(trim((string) $request->airdrop_withdraw_pay_currency))]
        );

        return redirect()->back()
            ->with('success', __('Airdrop withdrawal settings updated.'));
    }

    // ─── Create form ──────────────────────────────────────────────────────────

    public function create()
    {
        $data['title']    = __('Create Airdrop Campaign');
        $data['menu']     = 'airdrop';
        $data['sub_menu'] = 'airdrop_create';
        $data['campaign'] = null;
        $this->appendGlobalSetupSettings($data);

        return view('admin.airdrop.form', $data);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'required|string|max:100',
            'start_date'          => 'required|date|after:now',
            'end_date'            => 'required|date|after:start_date',
            'daily_claim_amount'  => 'required|numeric|min:0.000000000000000001',
            'streak_days'         => 'required|integer|min:1|max:365',
            'streak_bonus_amount' => 'required|numeric|min:0',
            'unlock_fee_usdt'     => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_lt_100_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_lt_500_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_lt_1000_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_gte_1000_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'contract_address'    => 'nullable|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_id'            => 'nullable|integer|min:1',
        ]);

        $tierFees = $this->resolveTieredFees($request);
        if ($this->hasInvalidTierFee($tierFees)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['unlock_fee_lt_100_usdt' => __('All withdrawal fee tiers are required and must be greater than zero.')]);
        }

        AirdropCampaign::create([
            'name'                => $request->name,
            'start_date'          => $request->start_date,
            'end_date'            => $request->end_date,
            'daily_claim_amount'  => bcmul($request->daily_claim_amount, '1', 18),
            'streak_days'         => $request->streak_days,
            'streak_bonus_amount' => bcmul($request->streak_bonus_amount, '1', 18),
            'unlock_fee_usdt'     => $tierFees['unlock_fee_lt_100_usdt'],
            'unlock_fee_lt_100_usdt' => $tierFees['unlock_fee_lt_100_usdt'],
            'unlock_fee_lt_500_usdt' => $tierFees['unlock_fee_lt_500_usdt'],
            'unlock_fee_lt_1000_usdt' => $tierFees['unlock_fee_lt_1000_usdt'],
            'unlock_fee_gte_1000_usdt' => $tierFees['unlock_fee_gte_1000_usdt'],
            'fee_revealed'        => $request->boolean('fee_revealed', false),
            'contract_address'    => $request->contract_address,
            'chain_id'            => $request->chain_id,
            'is_active'           => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.airdrop.index')
            ->with('success', __('Airdrop campaign created successfully.'));
    }

    // ─── Edit form ────────────────────────────────────────────────────────────

    public function edit($id)
    {
        $campaign = AirdropCampaign::findOrFail($id);

        if ($campaign->hasStarted()) {
            return redirect()->route('admin.airdrop.index')
                ->with('dismiss', __('Cannot edit a campaign that has already started.'));
        }

        $data['title']    = __('Edit Airdrop Campaign');
        $data['menu']     = 'airdrop';
        $data['sub_menu'] = 'airdrop_list';
        $data['campaign'] = $campaign;
        $this->appendGlobalSetupSettings($data);

        return view('admin.airdrop.form', $data);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $campaign = AirdropCampaign::findOrFail($id);

        if ($campaign->hasStarted()) {
            return redirect()->route('admin.airdrop.index')
                ->with('dismiss', __('Cannot edit a campaign that has already started.'));
        }

        $request->validate([
            'name'                => 'required|string|max:100',
            'start_date'          => 'required|date|after:now',
            'end_date'            => 'required|date|after:start_date',
            'daily_claim_amount'  => 'required|numeric|min:0.000000000000000001',
            'streak_days'         => 'required|integer|min:1|max:365',
            'streak_bonus_amount' => 'required|numeric|min:0',
            'unlock_fee_usdt'     => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_lt_100_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_lt_500_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_lt_1000_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'unlock_fee_gte_1000_usdt' => 'nullable|numeric|min:0.01|max:99999',
            'contract_address'    => 'nullable|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_id'            => 'nullable|integer|min:1',
        ]);

        $tierFees = $this->resolveTieredFees($request);
        if ($this->hasInvalidTierFee($tierFees)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['unlock_fee_lt_100_usdt' => __('All withdrawal fee tiers are required and must be greater than zero.')]);
        }

        $campaign->update([
            'name'                => $request->name,
            'start_date'          => $request->start_date,
            'end_date'            => $request->end_date,
            'daily_claim_amount'  => bcmul($request->daily_claim_amount, '1', 18),
            'streak_days'         => $request->streak_days,
            'streak_bonus_amount' => bcmul($request->streak_bonus_amount, '1', 18),
            'unlock_fee_usdt'     => $tierFees['unlock_fee_lt_100_usdt'],
            'unlock_fee_lt_100_usdt' => $tierFees['unlock_fee_lt_100_usdt'],
            'unlock_fee_lt_500_usdt' => $tierFees['unlock_fee_lt_500_usdt'],
            'unlock_fee_lt_1000_usdt' => $tierFees['unlock_fee_lt_1000_usdt'],
            'unlock_fee_gte_1000_usdt' => $tierFees['unlock_fee_gte_1000_usdt'],
            'fee_revealed'        => $request->boolean('fee_revealed', false),
            'contract_address'    => $request->contract_address,
            'chain_id'            => $request->chain_id,
            'is_active'           => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.airdrop.index')
            ->with('success', __('Airdrop campaign updated successfully.'));
    }

    // ─── Reveal unlock fee ────────────────────────────────────────────────────

    /**
     * Admin reveals the unlock fee after the campaign ends.
     * The fee was intentionally hidden from users during the campaign.
     */
    public function revealFee(Request $request, $id)
    {
        $campaign = AirdropCampaign::findOrFail($id);

        if (!$campaign->hasEnded()) {
            return redirect()->route('admin.airdrop.index')
                ->with('dismiss', __('Campaign has not ended yet. You can only reveal the fee after it ends.'));
        }

        if ($campaign->fee_revealed) {
            return redirect()->route('admin.airdrop.index')
                ->with('dismiss', __('Unlock fee has already been revealed.'));
        }

        $request->validate([
            'unlock_fee_usdt' => 'required|numeric|min:0.01|max:99999',
        ]);

        $fee = number_format((float) $request->unlock_fee_usdt, 2, '.', '');

        $campaign->update([
            'unlock_fee_usdt' => $fee,
            'unlock_fee_lt_100_usdt' => $fee,
            'unlock_fee_lt_500_usdt' => $fee,
            'unlock_fee_lt_1000_usdt' => $fee,
            'unlock_fee_gte_1000_usdt' => $fee,
            'fee_revealed'    => true,
        ]);

        Log::info('Airdrop unlock fee revealed', [
            'campaign_id' => $campaign->id,
            'fee_usdt'    => $request->unlock_fee_usdt,
        ]);

        return redirect()->route('admin.airdrop.index')
            ->with('success', __('Unlock fee revealed. Users can now pay to unlock their airdrop tokens.'));
    }

    // ─── Toggle active ────────────────────────────────────────────────────────

    public function toggleActive($id)
    {
        $campaign = AirdropCampaign::findOrFail($id);
        $campaign->update(['is_active' => !$campaign->is_active]);

        $status = $campaign->is_active ? __('activated') : __('deactivated');
        return redirect()->route('admin.airdrop.index')
            ->with('success', __("Campaign :status.", ['status' => $status]));
    }

    // ─── Campaign stats ──────────────────────────────────────────────────────

    public function stats(Request $request, $id)
    {
        $campaign = AirdropCampaign::findOrFail($id);

        [$selectedRange, $rangeStart, $rangeEnd, $rangeLabel] = $this->resolveStatsDateRange($request);

        $claimsBase = AirdropClaim::where('campaign_id', $id);
        $unlocksBase = AirdropUnlock::where('campaign_id', $id);

        if ($rangeStart && $rangeEnd) {
            $claimsBase->whereBetween('claim_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
            $unlocksBase->whereBetween('created_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()]);
        }

        $perUserStatsQuery = AirdropClaim::query()
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_claim_count'),
                DB::raw('SUM(CASE WHEN is_bonus = 1 THEN 1 ELSE 0 END) as bonus_claim_count'),
                DB::raw('SUM(amount_obx) as total_claimed_obx'),
                DB::raw('SUM(CASE WHEN is_bonus = 1 THEN amount_obx ELSE 0 END) as total_bonus_obx'),
                DB::raw('MAX(claim_date) as last_claim_date')
            )
            ->where('campaign_id', $id);

        if ($rangeStart && $rangeEnd) {
            $perUserStatsQuery->whereBetween('claim_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
        }

        $perUserStats = $perUserStatsQuery
            ->groupBy('user_id')
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc(DB::raw('SUM(amount_obx)'))
            ->paginate(50)
            ->appends($request->query());

        $userIds = $perUserStats->getCollection()->pluck('user_id')->all();
        $unlockByUser = collect();

        if (!empty($userIds)) {
            $unlockByUserQuery = AirdropUnlock::query()
                ->select(
                    'user_id',
                    DB::raw('SUM(usdt_paid) as total_usdt_paid'),
                    DB::raw('SUM(obx_released) as total_obx_released'),
                    DB::raw("MAX(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as has_confirmed_unlock"),
                    DB::raw("MAX(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as has_pending_unlock")
                )
                ->where('campaign_id', $id)
                ->whereIn('user_id', $userIds);

            if ($rangeStart && $rangeEnd) {
                $unlockByUserQuery->whereBetween('created_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()]);
            }

            $unlockByUser = $unlockByUserQuery
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $perUserStats->setCollection(
            $perUserStats->getCollection()->map(function ($item) use ($unlockByUser) {
                $unlockStats = $unlockByUser->get($item->user_id);

                $item->total_usdt_paid = $unlockStats ? (float) ($unlockStats->total_usdt_paid ?? 0) : 0.0;
                $item->total_obx_released = $unlockStats ? (string) ($unlockStats->total_obx_released ?? '0') : '0';

                if ($unlockStats && (int) $unlockStats->has_confirmed_unlock === 1) {
                    $item->unlock_status = 'confirmed';
                } elseif ($unlockStats && (int) $unlockStats->has_pending_unlock === 1) {
                    $item->unlock_status = 'pending';
                } else {
                    $item->unlock_status = 'not_requested';
                }

                return $item;
            })
        );

        $data['title'] = __('Airdrop Stats — :name', ['name' => $campaign->name]);
        $data['menu'] = 'airdrop';
        $data['sub_menu'] = 'airdrop_list';
        $data['campaign'] = $campaign;
        $data['total_claim_entries'] = (clone $claimsBase)->count();
        $data['total_participants'] = (clone $claimsBase)->distinct('user_id')->count('user_id');
        $data['total_bonus_claim_entries'] = (clone $claimsBase)->where('is_bonus', true)->count();
        $data['total_claimed_obx'] = (clone $claimsBase)->sum('amount_obx');
        $data['total_bonus_obx'] = (clone $claimsBase)->where('is_bonus', true)->sum('amount_obx');
        $data['total_unlock_requests'] = (clone $unlocksBase)->count();
        $data['total_confirmed_unlocks'] = (clone $unlocksBase)->where('status', 'confirmed')->count();
        $data['total_pending_unlocks'] = (clone $unlocksBase)->where('status', 'pending')->count();
        $data['total_usdt_paid'] = (clone $unlocksBase)->where('status', 'confirmed')->sum('usdt_paid');
        $data['total_obx_released'] = (clone $unlocksBase)->where('status', 'confirmed')->sum('obx_released');
        $data['perUserStats'] = $perUserStats;
        $data['selectedRange'] = $selectedRange;
        $data['rangeLabel'] = $rangeLabel;
        $data['filterStartDate'] = $rangeStart ? $rangeStart->toDateString() : '';
        $data['filterEndDate'] = $rangeEnd ? $rangeEnd->toDateString() : '';

        return view('admin.airdrop.stats', $data);
    }

    private function resolveStatsDateRange(Request $request): array
    {
        $selectedRange = strtolower(trim((string) $request->query('range', 'all')));
        $allowed = ['all', 'today', '7d', '30d', 'custom'];

        if (!in_array($selectedRange, $allowed, true)) {
            $selectedRange = 'all';
        }

        $start = null;
        $end = null;
        $label = __('Lifetime');

        if ($selectedRange === 'today') {
            $start = Carbon::today();
            $end = Carbon::today();
            $label = __('Today');
        }

        if ($selectedRange === '7d') {
            $start = Carbon::today()->subDays(6);
            $end = Carbon::today();
            $label = __('Last 7 Days');
        }

        if ($selectedRange === '30d') {
            $start = Carbon::today()->subDays(29);
            $end = Carbon::today();
            $label = __('Last 30 Days');
        }

        if ($selectedRange === 'custom') {
            $rawStart = trim((string) $request->query('start_date', ''));
            $rawEnd = trim((string) $request->query('end_date', ''));

            if ($rawStart !== '' && $rawEnd !== '') {
                try {
                    $customStart = Carbon::createFromFormat('Y-m-d', $rawStart)->startOfDay();
                    $customEnd = Carbon::createFromFormat('Y-m-d', $rawEnd)->startOfDay();

                    if ($customStart->lte($customEnd)) {
                        $start = $customStart;
                        $end = $customEnd;
                        $label = __('Custom Range') . ': ' . $customStart->toDateString() . ' - ' . $customEnd->toDateString();
                    } else {
                        $selectedRange = 'all';
                    }
                } catch (\Throwable $e) {
                    $selectedRange = 'all';
                }
            } else {
                $selectedRange = 'all';
            }
        }

        return [$selectedRange, $start, $end, $label];
    }

    // ─── Claims list ──────────────────────────────────────────────────────────

    public function claims($id)
    {
        $campaign = AirdropCampaign::findOrFail($id);

        $data['title']    = __('Airdrop Claims — :name', ['name' => $campaign->name]);
        $data['menu']     = 'airdrop';
        $data['sub_menu'] = 'airdrop_list';
        $data['campaign'] = $campaign;
        $data['claims']   = AirdropClaim::where('campaign_id', $id)
            ->with('user')
            ->latest()
            ->paginate(50);

        return view('admin.airdrop.claims', $data);
    }

    // ─── Unlocks list ─────────────────────────────────────────────────────────

    public function unlocks($id)
    {
        $campaign = AirdropCampaign::findOrFail($id);

        $data['title']    = __('Airdrop Unlocks — :name', ['name' => $campaign->name]);
        $data['menu']     = 'airdrop';
        $data['sub_menu'] = 'airdrop_list';
        $data['campaign'] = $campaign;
        $data['unlocks']  = AirdropUnlock::where('campaign_id', $id)
            ->with('user')
            ->latest()
            ->paginate(50);

        return view('admin.airdrop.unlocks', $data);
    }

    private function resolveTieredFees(Request $request): array
    {
        $legacyFee = is_numeric($request->input('unlock_fee_usdt'))
            ? (float) $request->input('unlock_fee_usdt')
            : null;

        return [
            'unlock_fee_lt_100_usdt' => $this->formatTierFee($request->input('unlock_fee_lt_100_usdt'), $legacyFee),
            'unlock_fee_lt_500_usdt' => $this->formatTierFee($request->input('unlock_fee_lt_500_usdt'), $legacyFee),
            'unlock_fee_lt_1000_usdt' => $this->formatTierFee($request->input('unlock_fee_lt_1000_usdt'), $legacyFee),
            'unlock_fee_gte_1000_usdt' => $this->formatTierFee($request->input('unlock_fee_gte_1000_usdt'), $legacyFee),
        ];
    }

    private function formatTierFee($rawValue, ?float $fallback): string
    {
        if (is_numeric($rawValue) && (float) $rawValue > 0) {
            return number_format((float) $rawValue, 2, '.', '');
        }

        if ($fallback !== null && $fallback > 0) {
            return number_format($fallback, 2, '.', '');
        }

        return '0.00';
    }

    private function hasInvalidTierFee(array $tierFees): bool
    {
        foreach ($tierFees as $fee) {
            if (!is_numeric($fee) || (float) $fee <= 0) {
                return true;
            }
        }

        return false;
    }

    private function appendGlobalSetupSettings(array &$data): void
    {
        $data['airdropWithdrawEnabled'] = (int) (settings(AIRDROP_WITHDRAW_ENABLED_SLUG) ?: 0) === 1;
        $data['airdropWithdrawPayCurrency'] = strtolower((string) (settings(AIRDROP_WITHDRAW_PAY_CURRENCY_SLUG) ?: 'usdtbsc'));
    }
}
