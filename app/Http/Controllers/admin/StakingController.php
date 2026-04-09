<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Model\StakingPool;
use App\Model\StakingPosition;
use App\Model\StakingTransaction;
use App\Repository\StakingRepository;
use Illuminate\Http\Request;

class StakingController extends Controller
{
    private StakingRepository $repo;

    public function __construct()
    {
        $this->repo = new StakingRepository();
    }

    /**
     * Admin overview – all positions DataTable.
     */
    public function index(Request $request)
    {
        $data['title'] = __('Staking – All Positions');

        if ($request->ajax()) {
            $items = StakingPosition::join('users', 'users.id', '=', 'staking_positions.user_id')
                ->join('staking_pools', 'staking_pools.id', '=', 'staking_positions.pool_id')
                ->select(
                    'staking_positions.*',
                    'users.email as email',
                    'staking_pools.name as pool_name',
                    'staking_pools.apy_bps as apy_bps'
                );

            return datatables($items)
                ->addColumn('apy', fn($p) => number_format($p->apy_bps / 100, 2) . ' %')
                ->addColumn('tx_hash_stake',   fn($p) => $p->tx_hash_stake ?? '')
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

        $data['total_staked']  = StakingPosition::where('status', 'active')->sum('net_amount');
        $data['total_burned']  = StakingTransaction::whereIn('type', ['burn_stake', 'burn_unstake'])->sum('amount');
        $data['total_rewards'] = StakingTransaction::where('type', 'reward')->sum('amount');
        $data['active_count']  = StakingPosition::where('status', 'active')->count();

        $data['stats'] = [
            'total_staked'  => $data['total_staked'],
            'total_burned'  => $data['total_burned'],
            'total_rewards' => $data['total_rewards'],
            'active_count'  => $data['active_count'],
        ];

        return view('admin.staking.index', $data);
    }

    /**
     * Pool management.
     */
    public function pools(Request $request)
    {
        $data['title'] = __('Staking Pools');
        $data['pools'] = StakingPool::orderBy('id')->get();
        return view('admin.staking.pools', $data);
    }

    /**
     * Persist pool (create or update).
     */
    public function savePool(Request $request)
    {
        $request->validate([
            'name'                 => 'required|string|max:100',
            'min_amount'           => 'required|numeric|min:0',
            'duration_days'        => 'required|integer|min:1',
            'apy_bps'              => 'required|integer|min:1|max:100000',
            'burn_on_stake_bps'    => 'required|integer|min:0|max:1000',
            'burn_on_unstake_bps'  => 'required|integer|min:0|max:1000',
            'status'               => 'required|in:0,1',
        ]);

        $payload = $request->only([
            'name', 'min_amount', 'duration_days', 'apy_bps',
            'burn_on_stake_bps', 'burn_on_unstake_bps', 'description', 'status',
            'pool_id_onchain',
        ]);

        // Accept either 'id' or 'edit_id' to identify an existing pool
        $editId = $request->filled('id') ? $request->id
                : ($request->filled('edit_id') ? $request->edit_id : null);
        if ($editId) {
            $payload['edit_id'] = $editId;
        }

        $result = $this->repo->savePool($payload);

        return redirect()->route('admin.staking.pools')
            ->with($result['success'] ? 'success' : 'dismiss', $result['message']);
    }

    /**
     * All staking transactions DataTable – includes burns visible on BSCScan.
     */
    public function transactions(Request $request)
    {
        $data['title'] = __('Staking Transactions (including burns)');

        if ($request->ajax()) {
            $items = StakingTransaction::join('users', 'users.id', '=', 'staking_transactions.user_id')
                ->select('staking_transactions.*', 'users.email as email');

            return datatables($items)
                ->addColumn('tx_hash', fn($t) => $t->tx_hash ?? '')
                ->addColumn('type_label', function ($t) {
                    return match($t->type) {
                        'stake_in'      => '<span class="badge badge-primary">Stake In</span>',
                        'unstake_out'   => '<span class="badge badge-info">Unstake Out</span>',
                        'burn_stake'    => '<span class="badge badge-danger">Burn (stake)</span>',
                        'burn_unstake'  => '<span class="badge badge-danger">Burn (unstake)</span>',
                        'reward'        => '<span class="badge badge-success">Reward</span>',
                        default         => ucfirst(str_replace('_', ' ', $t->type)),
                    };
                })
                ->rawColumns(['type_label'])
                ->make(true);
        }

        return view('admin.staking.transactions', $data);
    }
}
