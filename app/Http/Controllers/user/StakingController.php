<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Model\StakingPool;
use App\Model\StakingPosition;
use App\Model\StakingTransaction;
use App\Repository\StakingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StakingController extends Controller
{
    private StakingRepository $repo;

    public function __construct()
    {
        $this->repo = new StakingRepository();
    }

    /**
     * Main staking page – show all active pools + user's current positions.
     */
    public function index()
    {
        $data['title']     = __('Web3 Staking');
        $data['pools']     = StakingPool::where('status', STATUS_ACTIVE)->get();
        $data['positions'] = StakingPosition::where('user_id', Auth::id())
            ->whereIn('status', ['active', 'pending'])
            ->with('pool')
            ->latest('staked_at')
            ->get();

        // Pass on-chain config to view
        $data['wc_project_id']        = settings('walletconnect_project_id') ?? '';
        $data['wc_chain_id']          = (int) (settings('walletconnect_chain_id') ?? 56);
        $data['obx_token_contract']   = settings('obx_token_contract') ?? '';
        $data['staking_contract']     = settings('staking_contract') ?? '';
        $data['obx_token_symbol']     = settings('coin_symbol') ?? 'OBX';
        $data['obx_token_decimals']   = (int) (settings('obx_token_decimals') ?? 18);
        $data['obx_token_logo_url']   = settings('obx_token_logo_url') ?? '';

        return view('user.staking.index', $data);
    }

    /**
     * Staking transaction history – DataTables AJAX + page render.
     */
    public function history(Request $request)
    {
        $data['title'] = __('Staking History');

        if ($request->ajax()) {
            $items = StakingPosition::where('user_id', Auth::id())
                ->with('pool')
                ->select('staking_positions.*');

            return datatables($items)
                ->addColumn('pool_name', fn($p) => $p->pool ? $p->pool->name : 'N/A')
                ->addColumn('apy', fn($p) => $p->pool ? $p->pool->apy_percent : '—')
                ->addColumn('tx_hash_stake', fn($p) => $p->tx_hash_stake ?? '')
                ->addColumn('tx_hash_unstake', fn($p) => $p->tx_hash_unstake ?? '')
                ->addColumn('status_badge', function ($p) {
                    return match($p->status) {
                        'active'   => '<span class="badge badge-success">Active</span>',
                        'unstaked' => '<span class="badge badge-secondary">Unstaked</span>',
                        'pending'  => '<span class="badge badge-warning">Pending</span>',
                        'failed'   => '<span class="badge badge-danger">Failed</span>',
                        default    => ucfirst($p->status),
                    };
                })
                ->rawColumns(['status_badge'])
                ->make(true);
        }

        return view('user.staking.history', $data);
    }

    /**
     * Transaction-level audit log – DataTables AJAX.
     */
    public function transactions(Request $request)
    {
        $data['title'] = __('Staking Transactions');

        if ($request->ajax()) {
            $items = StakingTransaction::where('user_id', Auth::id())
                ->select('staking_transactions.*');

            return datatables($items)
                ->addColumn('tx_hash', fn($t) => $t->tx_hash ?? '')
                ->make(true);
        }

        return view('user.staking.transactions', $data);
    }

    /**
     * POST – frontend calls this right after the on-chain stake tx is mined.
     * Validates input and persists via repository.
     */
    public function confirmStake(Request $request)
    {
        $request->validate([
            'pool_id'        => 'required|integer|exists:staking_pools,id',
            'wallet_address' => 'required|string|size:42',
            'gross_amount'   => 'required|numeric|min:0.000001',
            'tx_hash'        => 'required|string|size:66|regex:/^0x[0-9a-fA-F]{64}$/',
        ]);

        $result = $this->repo->saveStake($request->only([
            'pool_id', 'wallet_address', 'gross_amount', 'tx_hash',
            'contract_stake_idx', 'locked_at', 'lock_until', 'burn_on_stake_bps',
        ]), Auth::id());

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->route('user.staking.index')->with('success', $result['message']);
        }
        return redirect()->back()->with('dismiss', $result['message']);
    }

    /**
     * POST – frontend calls this right after the on-chain unstake tx is mined.
     */
    public function confirmUnstake(Request $request)
    {
        $request->validate([
            'position_id'       => 'required|integer',
            'tx_hash'           => 'required|string|size:66|regex:/^0x[0-9a-fA-F]{64}$/',
            'reward_earned'     => 'nullable|numeric|min:0',
            'burned_on_unstake' => 'nullable|numeric|min:0',
            'returned_amount'   => 'nullable|numeric|min:0',
        ]);

        $result = $this->repo->saveUnstake($request->only([
            'position_id', 'tx_hash', 'reward_earned', 'burned_on_unstake', 'returned_amount',
        ]), Auth::id());

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->route('user.staking.index')->with('success', $result['message']);
        }
        return redirect()->back()->with('dismiss', $result['message']);
    }
}
