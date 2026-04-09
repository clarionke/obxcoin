<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    // ─── Create form ──────────────────────────────────────────────────────────

    public function create()
    {
        $data['title']    = __('Create Airdrop Campaign');
        $data['menu']     = 'airdrop';
        $data['sub_menu'] = 'airdrop_create';
        $data['campaign'] = null;

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
            'contract_address'    => 'nullable|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_id'            => 'nullable|integer|min:1',
        ]);

        AirdropCampaign::create([
            'name'                => $request->name,
            'start_date'          => $request->start_date,
            'end_date'            => $request->end_date,
            'daily_claim_amount'  => bcmul($request->daily_claim_amount, '1', 18),
            'streak_days'         => $request->streak_days,
            'streak_bonus_amount' => bcmul($request->streak_bonus_amount, '1', 18),
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
            'contract_address'    => 'nullable|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_id'            => 'nullable|integer|min:1',
        ]);

        $campaign->update([
            'name'                => $request->name,
            'start_date'          => $request->start_date,
            'end_date'            => $request->end_date,
            'daily_claim_amount'  => bcmul($request->daily_claim_amount, '1', 18),
            'streak_days'         => $request->streak_days,
            'streak_bonus_amount' => bcmul($request->streak_bonus_amount, '1', 18),
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

        $campaign->update([
            'unlock_fee_usdt' => $request->unlock_fee_usdt,
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
}
