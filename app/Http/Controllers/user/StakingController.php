<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Model\StakingPool;
use App\Model\StakingPosition;
use App\Model\StakingTransaction;
use App\Repository\StakingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $data['gasless_enabled']      = (int) (settings('gasless_enabled') ?? 0) === 1;
        $data['gasless_quote_url']    = url('/api/gasless/quote');
        $data['gasless_sponsor_url']  = url('/api/gasless/sponsor');
        $data['gasless_actions']      = settings('gasless_allowed_actions') ?: 'buy,stake,unstake,unlock,transfer,withdraw';

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

        $pool = StakingPool::findOrFail((int)$request->pool_id);
        $verification = $this->verifyStakeTransaction(
            strtolower($request->tx_hash),
            strtolower($request->wallet_address),
            $pool,
            (string)$request->gross_amount
        );

        if (!$verification['ok']) {
            $result = ['success' => false, 'message' => $verification['message']];
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($result, 422);
            }
            return redirect()->back()->with('dismiss', $verification['message']);
        }

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

        $position = StakingPosition::where('id', $request->position_id)
            ->where('user_id', Auth::id())
            ->first();
        if (!$position) {
            $result = ['success' => false, 'message' => __('Staking position not found.')];
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($result, 422);
            }
            return redirect()->back()->with('dismiss', $result['message']);
        }

        $verification = $this->verifyUnstakeTransaction(strtolower($request->tx_hash), $position);
        if (!$verification['ok']) {
            $result = ['success' => false, 'message' => $verification['message']];
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($result, 422);
            }
            return redirect()->back()->with('dismiss', $verification['message']);
        }

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

    private function verifyStakeTransaction(string $txHash, string $walletAddress, StakingPool $pool, string $grossAmount): array
    {
        $contract = strtolower(trim((string)(settings('staking_contract') ?: '')));
        if ($contract === '' || !preg_match('/^0x[0-9a-f]{40}$/', $contract)) {
            return ['ok' => false, 'message' => __('Staking contract is not configured.')];
        }

        $txPayload = $this->fetchOnchainTransaction($txHash);
        if (!$txPayload['ok']) {
            return $txPayload;
        }

        $tx = $txPayload['tx'];
        $receipt = $txPayload['receipt'];
        if (strtolower((string)($receipt['to'] ?? '')) !== $contract) {
            return ['ok' => false, 'message' => __('Stake transaction contract mismatch.')];
        }
        if (strtolower((string)($tx['from'] ?? '')) !== $walletAddress) {
            return ['ok' => false, 'message' => __('Stake transaction sender mismatch.')];
        }

        $input = strtolower((string)($tx['input'] ?? ''));
        if (!str_starts_with($input, '0x7b0472f0')) {
            return ['ok' => false, 'message' => __('Transaction is not a staking call.')];
        }

        $payload = substr($input, 10);
        if (strlen($payload) < 128) {
            return ['ok' => false, 'message' => __('Invalid staking transaction payload.')];
        }

        $poolIdOnchain = (int)$this->hexToDec(substr($payload, 0, 64));
        $amountWei = $this->hexToDec(substr($payload, 64, 64));
        $amountHuman = $this->weiToDecimal($amountWei, (int)(settings('obx_token_decimals') ?: 18));

        $expectedPoolId = $pool->pool_id_onchain !== null ? (int)$pool->pool_id_onchain : ((int)$pool->id - 1);
        if ($poolIdOnchain !== $expectedPoolId) {
            return ['ok' => false, 'message' => __('Stake transaction pool mismatch.')];
        }

        if (bccomp($amountHuman, (string)$grossAmount, 8) !== 0) {
            return ['ok' => false, 'message' => __('Stake amount mismatch with on-chain transaction.')];
        }

        return ['ok' => true, 'message' => __('Verified')];
    }

    private function verifyUnstakeTransaction(string $txHash, StakingPosition $position): array
    {
        $contract = strtolower(trim((string)(settings('staking_contract') ?: '')));
        if ($contract === '' || !preg_match('/^0x[0-9a-f]{40}$/', $contract)) {
            return ['ok' => false, 'message' => __('Staking contract is not configured.')];
        }

        $txPayload = $this->fetchOnchainTransaction($txHash);
        if (!$txPayload['ok']) {
            return $txPayload;
        }

        $tx = $txPayload['tx'];
        $receipt = $txPayload['receipt'];
        if (strtolower((string)($receipt['to'] ?? '')) !== $contract) {
            return ['ok' => false, 'message' => __('Unstake transaction contract mismatch.')];
        }
        if (strtolower((string)($tx['from'] ?? '')) !== strtolower((string)$position->wallet_address)) {
            return ['ok' => false, 'message' => __('Unstake transaction sender mismatch.')];
        }

        $input = strtolower((string)($tx['input'] ?? ''));
        if (!str_starts_with($input, '0x2e17de78')) {
            return ['ok' => false, 'message' => __('Transaction is not an unstake call.')];
        }

        if ($position->contract_stake_idx !== null) {
            $payload = substr($input, 10);
            if (strlen($payload) < 64) {
                return ['ok' => false, 'message' => __('Invalid unstake transaction payload.')];
            }
            $stakeIdx = (int)$this->hexToDec(substr($payload, 0, 64));
            if ($stakeIdx !== (int)$position->contract_stake_idx) {
                return ['ok' => false, 'message' => __('Unstake stake index mismatch.')];
            }
        }

        return ['ok' => true, 'message' => __('Verified')];
    }

    private function fetchOnchainTransaction(string $txHash): array
    {
        try {
            $rpcUrl = trim((string)(settings('chain_link') ?: config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/')));
            if ($rpcUrl === '') {
                return ['ok' => false, 'message' => __('RPC endpoint is not configured.')];
            }

            $receiptRes = Http::timeout(20)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1,
            ])->json();
            $txRes = Http::timeout(20)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionByHash',
                'params' => [$txHash],
                'id' => 2,
            ])->json();

            $receipt = $receiptRes['result'] ?? null;
            $tx = $txRes['result'] ?? null;
            if (!$receipt || !$tx) {
                return ['ok' => false, 'message' => __('Transaction not found on-chain yet. Please wait and retry.')];
            }

            if (strtolower((string)($receipt['status'] ?? '0x0')) !== '0x1') {
                return ['ok' => false, 'message' => __('Transaction failed on-chain.')];
            }

            return ['ok' => true, 'tx' => $tx, 'receipt' => $receipt, 'message' => __('Verified')];
        } catch (\Throwable $e) {
            Log::warning('Staking tx verification failed: ' . $e->getMessage(), ['tx_hash' => $txHash]);
            return ['ok' => false, 'message' => __('Unable to verify on-chain transaction right now.')];
        }
    }

    private function hexToDec(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '0x');
        if ($hex === '') {
            return '0';
        }

        $dec = '0';
        foreach (str_split($hex) as $char) {
            $value = (string)hexdec($char);
            $dec = bcadd(bcmul($dec, '16', 0), $value, 0);
        }
        return $dec;
    }

    private function weiToDecimal(string $wei, int $decimals = 18): string
    {
        $base = '1' . str_repeat('0', max(0, $decimals));
        $value = bcdiv($wei, $base, $decimals);
        $trimmed = rtrim(rtrim($value, '0'), '.');
        return $trimmed === '' ? '0' : $trimmed;
    }
}
