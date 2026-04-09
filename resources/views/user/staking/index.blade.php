@extends('user.master',['menu'=>'staking', 'sub_menu'=>'stake'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
.pool-card {
    border: 2px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
    padding: 20px;
    margin-bottom: 14px;
    background: var(--dark3);
}
.pool-card:hover        { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.pool-card.selected     { border-color: var(--accent); background: rgba(99,102,241,.08); }
.pool-card .pool-name   { font-size: 17px; font-weight: 700; }
.pool-card .pool-apy    { font-size: 22px; font-weight: 800; color: var(--success); }
.pool-card .pool-meta   { font-size: 13px; color: var(--muted); margin-top: 4px; }
.stake-steps            { counter-reset: step; list-style: none; padding: 0; }
.stake-steps li         { counter-increment: step; padding: 6px 0 6px 36px; position: relative; color: var(--muted); font-size: 13px; }
.stake-steps li::before { content: counter(step); position: absolute; left: 0; top: 4px; width: 22px; height: 22px;
                           border-radius: 50%; background: var(--accent); color: #fff; font-size: 12px;
                           display: flex; align-items: center; justify-content: center; }
.stake-steps li.active  { color: var(--text); font-weight: 600; }
#stake-status           { font-size: 13px; margin-top: 10px; }
.position-row           { background: var(--dark4); border-radius: 8px; padding: 12px 16px; margin-bottom: 8px; }
.position-row .pr-pool  { font-weight: 600; }
.position-row .pr-lock  { font-size: 12px; color: var(--muted); }
</style>
@endsection

@section('content')
<div class="row mx-0">

    {{-- ── LEFT: Stake Form ──────────────────────────────────────────────────── --}}
    <div class="col-xl-7 mb-4">
        <div class="card cp-user-custom-card">
            <div class="card-body">
                <div class="cp-user-card-header-area">
                    <h4><i class="fa fa-lock mr-2" style="color:var(--accent);"></i>{{__('Stake OBX')}}</h4>
                </div>

                @if(session('success'))
                    <div class="alert alert-success mt-2">{{ session('success') }}</div>
                @endif
                @if(session('dismiss'))
                    <div class="alert alert-danger mt-2">{{ session('dismiss') }}</div>
                @endif

                @if($pools->isEmpty())
                    <p class="text-warning mt-3"><i class="fa fa-exclamation-triangle mr-1"></i>{{__('No staking pools are currently active. Check back soon.')}}</p>
                @else

                {{-- Pool selection --}}
                <div class="mt-3 mb-3">
                    <label class="mb-2"><strong>{{__('Select Staking Pool')}}</strong></label>
                    @foreach($pools as $pool)
                    <div class="pool-card" id="pool-card-{{ $pool->id }}"
                         onclick="selectPool({{ $pool->id }}, '{{ $pool->name }}', {{ $pool->min_amount }}, {{ $pool->duration_days }}, {{ $pool->apy_bps }}, {{ $pool->burn_on_stake_bps }}, {{ $pool->burn_on_unstake_bps }})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="pool-name">{{ $pool->name }}</div>
                                <div class="pool-meta">
                                    <i class="fa fa-clock-o mr-1"></i>{{ $pool->duration_days }} {{__('days lock')}}
                                    &nbsp;•&nbsp;
                                    <i class="fa fa-fire mr-1" style="color:var(--danger);"></i>{{__('Burn on stake')}}: {{ $pool->burn_stake_pct }}
                                    &nbsp;•&nbsp; {{__('Burn on unstake')}}: {{ $pool->burn_unstake_pct }}
                                </div>
                                <div class="pool-meta">{{__('Min stake')}}: {{ number_format($pool->min_amount, 2) }} OBX</div>
                                @if($pool->description)
                                    <div class="pool-meta mt-1" style="color:var(--text-2);">{{ $pool->description }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="pool-apy">{{ $pool->apy_percent }}</div>
                                <div class="pool-meta">APY</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Amount --}}
                <div class="form-group">
                    <label>{{__('Amount to stake (OBX)')}}</label>
                    <input type="number" step="any" min="0.001" id="stake_amount"
                           class="form-control" placeholder="e.g. 1000"
                           oninput="updateStakePreview()" autocomplete="off">
                </div>

                {{-- Preview --}}
                <ul class="price-preview mb-3" id="stake-preview" style="display:none;list-style:none;padding:12px 16px;background:rgba(255,255,255,.04);border-radius:8px;">
                    <li>{{__('Pool')}}:         <strong id="pr_pool_name">—</strong></li>
                    <li>{{__('Lock period')}}:  <strong id="pr_duration">—</strong></li>
                    <li>{{__('APY')}}:          <strong id="pr_apy">—</strong></li>
                    <li>{{__('Gross stake')}}: <strong id="pr_gross">—</strong> OBX</li>
                    <li style="color:var(--danger);">{{__('Burn on stake')}}: <strong id="pr_burn_stake">—</strong> OBX</li>
                    <li>{{__('Net staked')}}:  <strong id="pr_net">—</strong> OBX</li>
                    <li style="color:var(--success);">{{__('Est. reward at maturity')}}: <strong id="pr_reward">—</strong> OBX</li>
                    <li style="color:var(--danger);">{{__('Burn on unstake')}}:   <strong id="pr_burn_unstake">—</strong> OBX</li>
                    <li style="color:var(--success);">{{__('Est. total return')}}:  <strong id="pr_return">—</strong> OBX</li>
                </ul>

                {{-- Connect & Stake flow --}}
                <div class="mt-3">
                    <button type="button" class="btn theme-btn mb-2" id="wc_connect_btn" onclick="wcConnect()">
                        <i class="fa fa-link mr-1"></i> {{__('Connect Wallet')}}
                    </button>
                    <div id="wc_connected" style="display:none;">
                        <p class="text-success mb-2" id="wc_address_display"></p>
                        <button type="button" class="btn theme-btn" id="wc_stake_btn" onclick="wcStake()" disabled>
                            <i class="fa fa-lock mr-1"></i> {{__('Approve & Stake')}}
                        </button>
                    </div>
                    <div id="stake-status"></div>
                </div>

                @endif
            </div>
        </div>
    </div>

    {{-- ── RIGHT: Active Positions ────────────────────────────────────────────── --}}
    <div class="col-xl-5">
        <div class="card cp-user-custom-card">
            <div class="card-body">
                <div class="cp-user-card-header-area">
                    <h4><i class="fa fa-list mr-2" style="color:var(--accent);"></i>{{__('My Active Stakes')}}</h4>
                </div>

                @if($positions->isEmpty())
                    <p class="text-muted mt-3">{{__('You have no active stakes.')}}</p>
                @else
                    @foreach($positions as $pos)
                    <div class="position-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="pr-pool">{{ $pos->pool ? $pos->pool->name : 'N/A' }}</div>
                                <div class="pr-lock">
                                    {{__('Staked')}}: {{ number_format($pos->net_amount, 4) }} OBX
                                    &nbsp;·&nbsp;
                                    @if($pos->isLocked())
                                        <span class="text-warning">{{__('Unlocks')}} {{ $pos->lock_until->diffForHumans() }}</span>
                                    @else
                                        <span class="text-success">{{__('Ready to unstake!')}}</span>
                                    @endif
                                </div>
                                <div class="pr-lock mt-1">
                                    @if($pos->tx_hash_stake)
                                        {{__('Stake tx:')}}
                                        <a href="{{ explorer_tx_url($pos->tx_hash_stake) }}" target="_blank" rel="noopener noreferrer" style="color:var(--accent);">
                                            {{ substr($pos->tx_hash_stake, 0, 14) }}…
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div>
                                @if(!$pos->isLocked())
                                    <button class="btn btn-sm btn-outline-success"
                                            onclick="wcUnstake({{ $pos->id }}, '{{ $pos->tx_hash_stake }}', {{ $pos->net_amount }}, {{ $pos->pool ? $pos->pool->burn_on_unstake_bps : 200 }}, {{ $pos->contract_stake_idx ?? 0 }})">
                                        <i class="fa fa-unlock mr-1"></i>{{__('Unstake')}}
                                    </button>
                                @else
                                    <span class="badge badge-warning">{{__('Locked')}}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif

                <div class="mt-3">
                    <a href="{{ route('user.staking.history') }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-history mr-1"></i> {{__('Full History')}}
                    </a>
                </div>
            </div>
        </div>

        {{-- How it works --}}
        <div class="card cp-user-custom-card mt-3">
            <div class="card-body">
                <h5><i class="fa fa-question-circle mr-1" style="color:var(--accent);"></i> {{__('How Staking Works')}}</h5>
                <ol class="stake-steps mt-3">
                    <li>{{__('Connect your BSC wallet (MetaMask / Trust Wallet)')}}</li>
                    <li>{{__('Choose a pool and enter the OBX amount')}}</li>
                    <li>{{__('Approve OBX spend in your wallet')}}</li>
                    <li>{{__('Confirm the stake transaction — burned tokens are visible on BSCScan')}}</li>
                    <li>{{__('After the lock period, return here and click Unstake')}}</li>
                    <li>{{__('You receive your principal (minus unstake burn) + APY reward')}}</li>
                </ol>
                <p class="mt-2" style="font-size:12px;color:var(--muted);">
                    <i class="fa fa-fire mr-1" style="color:var(--danger);"></i>
                    {{__('All burns are real on-chain burns via the OBX token contract — permanently visible on BSCScan.')}}
                </p>
            </div>
        </div>
    </div>

</div>
@endsection

@section('script')
<script>
// ── Chain config (mirrors buy coin page pattern) ───────────────────────────
const WC_PROJECT_ID     = @json($wc_project_id);
const WC_CHAIN_ID       = {{ $wc_chain_id }};
const OBX_TOKEN_ADDRESS = @json($obx_token_contract);
const STAKING_ADDRESS   = @json($staking_contract);
const OBX_SYMBOL        = @json($obx_token_symbol);
const OBX_DECIMALS      = {{ (int) $obx_token_decimals }};
const EXPLORER_TX_BASE  = WC_CHAIN_ID === 56  ? 'https://bscscan.com/tx/'
                        : WC_CHAIN_ID === 97  ? 'https://testnet.bscscan.com/tx/'
                        : WC_CHAIN_ID === 1   ? 'https://etherscan.io/tx/'
                        : 'https://bscscan.com/tx/';

const ERC20_ABI = [
    {"inputs":[{"name":"spender","type":"address"},{"name":"amount","type":"uint256"}],"name":"approve","outputs":[{"name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},
    {"inputs":[{"name":"owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"}
];

const STAKING_ABI = [
    {"inputs":[{"name":"poolId","type":"uint256"},{"name":"grossAmount","type":"uint256"}],"name":"stake","outputs":[],"stateMutability":"nonpayable","type":"function"},
    {"inputs":[{"name":"stakeIdx","type":"uint256"}],"name":"unstake","outputs":[],"stateMutability":"nonpayable","type":"function"},
    {"inputs":[{"name":"user","type":"address"},{"name":"stakeIdx","type":"uint256"}],"name":"calculateReward","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"},
    {"inputs":[{"name":"user","type":"address"},{"name":"idx","type":"uint256"}],"name":"getStake","outputs":[{"components":[{"name":"poolId","type":"uint256"},{"name":"netAmount","type":"uint256"},{"name":"stakedAt","type":"uint256"},{"name":"lockUntil","type":"uint256"},{"name":"rewardPaid","type":"uint256"},{"name":"active","type":"bool"}],"name":"","type":"tuple"}],"stateMutability":"view","type":"function"},
    {"inputs":[{"name":"user","type":"address"}],"name":"getStakeCount","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"}
];

// ── Selected pool state ──────────────────────────────────────────────────
let selectedPool = null;
let wcProvider   = null;
let wcSigner     = null;
let wcAddress    = null;

function selectPool(id, name, minAmt, days, apyBps, burnStakeBps, burnUnstakeBps) {
    document.querySelectorAll('.pool-card').forEach(el => el.classList.remove('selected'));
    const card = document.getElementById('pool-card-' + id);
    if (card) card.classList.add('selected');
    selectedPool = { id, name, minAmt, days, apyBps, burnStakeBps, burnUnstakeBps };
    updateStakePreview();
    const stakeBtn = document.getElementById('wc_stake_btn');
    if (stakeBtn) stakeBtn.disabled = false;
}

function updateStakePreview() {
    if (!selectedPool) return;
    const gross = parseFloat(document.getElementById('stake_amount').value) || 0;
    if (gross <= 0) { document.getElementById('stake-preview').style.display = 'none'; return; }

    const burnStake   = gross * selectedPool.burnStakeBps / 10000;
    const net         = gross - burnStake;
    const rewardRate  = selectedPool.apyBps / 10000;
    const reward      = net * rewardRate * selectedPool.days / 365;
    const burnUnstake = net * selectedPool.burnUnstakeBps / 10000;
    const totalReturn = (net - burnUnstake) + reward;

    document.getElementById('pr_pool_name').innerText  = selectedPool.name;
    document.getElementById('pr_duration').innerText   = selectedPool.days + ' days';
    document.getElementById('pr_apy').innerText        = (selectedPool.apyBps / 100).toFixed(2) + ' %';
    document.getElementById('pr_gross').innerText      = gross.toFixed(4);
    document.getElementById('pr_burn_stake').innerText = burnStake.toFixed(4);
    document.getElementById('pr_net').innerText        = net.toFixed(4);
    document.getElementById('pr_reward').innerText     = reward.toFixed(4);
    document.getElementById('pr_burn_unstake').innerText = burnUnstake.toFixed(4);
    document.getElementById('pr_return').innerText     = totalReturn.toFixed(4);
    document.getElementById('stake-preview').style.display = 'block';
}

// ── Wallet connect ──────────────────────────────────────────────────────
function loadScript(src) {
    return new Promise((res, rej) => {
        if (document.querySelector(`script[src="${src}"]`)) { res(); return; }
        const s = document.createElement('script');
        s.src = src; s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
    });
}

async function wcConnect() {
    if (!WC_PROJECT_ID) { setStatus('<span class="text-danger">WalletConnect Project ID not configured.</span>'); return; }
    try {
        setStatus('⏳ Loading libraries…');
        if (!window.ethers)
            await loadScript('{{ asset("js/vendor/ethers-5.7.2.umd.min.js") }}');
        if (!window.WalletConnectProvider)
            await loadScript('{{ asset("js/vendor/walletconnect-web3-provider-1.8.0.min.js") }}');

        const rpcMap = {};
        rpcMap[WC_CHAIN_ID] = WC_CHAIN_ID === 56
            ? 'https://bsc-dataseed.binance.org/'
            : 'https://data-seed-prebsc-1-s1.binance.org:8545/';

        wcProvider = new WalletConnectProvider.default({ projectId: WC_PROJECT_ID, rpc: rpcMap });
        await wcProvider.enable();

        const web3Provider = new ethers.providers.Web3Provider(wcProvider);
        const network      = await web3Provider.getNetwork();
        if (network.chainId !== WC_CHAIN_ID) {
            setStatus(`<span class="text-danger">Wrong network (chain ${network.chainId}). Switch to chain ID ${WC_CHAIN_ID}.</span>`);
            return;
        }
        wcSigner  = web3Provider.getSigner();
        wcAddress = await wcSigner.getAddress();

        document.getElementById('wc_connect_btn').style.display = 'none';
        document.getElementById('wc_connected').style.display   = 'block';
        document.getElementById('wc_address_display').innerText =
            '✅ ' + wcAddress.substring(0,6) + '…' + wcAddress.substring(38);
        setStatus('');
    } catch (e) {
        setStatus('<span class="text-danger">Connection failed: ' + e.message + '</span>');
    }
}

// ── Stake ────────────────────────────────────────────────────────────────
async function wcStake() {
    if (!selectedPool) { setStatus('<span class="text-danger">Select a pool first.</span>'); return; }
    const gross = parseFloat(document.getElementById('stake_amount').value);
    if (!gross || gross <= 0) { setStatus('<span class="text-danger">Enter a valid amount.</span>'); return; }
    if (!wcSigner)   { setStatus('<span class="text-danger">Connect wallet first.</span>'); return; }
    if (!STAKING_ADDRESS) { setStatus('<span class="text-danger">Staking contract not configured. Contact admin.</span>'); return; }

    try {
        const grossWei   = ethers.utils.parseUnits(gross.toFixed(8), OBX_DECIMALS);
        const obxContract    = new ethers.Contract(OBX_TOKEN_ADDRESS, ERC20_ABI, wcSigner);
        const stakingContract = new ethers.Contract(STAKING_ADDRESS, STAKING_ABI, wcSigner);

        const balance = await obxContract.balanceOf(wcAddress);
        if (balance.lt(grossWei)) {
            setStatus('<span class="text-danger">Insufficient OBX balance.</span>');
            return;
        }

        // Step 1: Approve
        setStatus('⏳ Step 1/2: Approving OBX spend… (confirm in wallet)');
        const approveTx = await obxContract.approve(STAKING_ADDRESS, grossWei);
        setStatus('⏳ Waiting for approval confirmation…');
        await approveTx.wait(1);

        // Step 2: Stake — contract pool ID = onchain index (0-based)
        const onchainPoolId = {{ isset($pools) ? 'window._poolOnchainIds && window._poolOnchainIds[' : '0' }};
        // Use pool_id_onchain if available (set at pool creation), fall back to pool DB id - 1
        const poolIdOnchain = selectedPool.id - 1; // default assumption: 0-indexed

        setStatus('⏳ Step 2/2: Staking OBX… (confirm in wallet)');
        const stakeTx = await stakingContract.stake(poolIdOnchain, grossWei);
        setStatus('⏳ Waiting for stake confirmation…');
        const receipt = await stakeTx.wait(1);

        // Get new stake index from contract
        let stakeIdx = null;
        try {
            const count = await stakingContract.getStakeCount(wcAddress);
            stakeIdx = count.toNumber() - 1;
        } catch (_) {}

        const now        = new Date();
        const lockUntil  = new Date(now.getTime() + selectedPool.days * 86400 * 1000);

        // Notify backend
        const res = await fetch('{{ route("user.staking.confirmStake") }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                pool_id:             selectedPool.id,
                wallet_address:      wcAddress,
                gross_amount:        gross,
                tx_hash:             stakeTx.hash,
                contract_stake_idx:  stakeIdx,
                burn_on_stake_bps:   selectedPool.burnStakeBps,
                locked_at:           now.toISOString(),
                lock_until:          lockUntil.toISOString(),
            })
        });
        const json = await res.json().catch(() => ({}));

        setStatus(
            '<span class="text-success">✅ Stake confirmed!<br>' +
            'Tx: <a href="' + EXPLORER_TX_BASE + stakeTx.hash + '" target="_blank" rel="noopener">' +
            stakeTx.hash.substring(0, 20) + '…</a><br>' +
            '🔥 Burn on stake is visible on BSCScan within this transaction.</span>'
        );
        document.getElementById('wc_stake_btn').disabled = true;
        // Reload positions section after short delay
        setTimeout(() => location.reload(), 4000);

    } catch (e) {
        setStatus('<span class="text-danger">Stake failed: ' + (e.reason || e.message) + '</span>');
    }
}

// ── Unstake ──────────────────────────────────────────────────────────────
async function wcUnstake(positionId, stakeHash, netAmount, burnUnstakeBps, contractIdx) {
    if (!wcSigner) { alert('{{ __("Connect your wallet first.") }}'); return; }
    if (!STAKING_ADDRESS) { alert('{{ __("Staking contract not configured.") }}'); return; }

    if (!confirm('{{ __("Unstake and receive your principal + reward? A burn will occur on unstake.") }}')) return;

    try {
        setStatus('⏳ Unstaking… (confirm in wallet)');
        const stakingContract = new ethers.Contract(STAKING_ADDRESS, STAKING_ABI, wcSigner);

        // Calculate reward estimate
        let reward = ethers.BigNumber.from(0);
        try { reward = await stakingContract.calculateReward(wcAddress, contractIdx); } catch (_) {}

        const unstakeTx = await stakingContract.unstake(contractIdx);
        setStatus('⏳ Waiting for unstake confirmation…');
        await unstakeTx.wait(1);

        const burnedOnUnstake = netAmount * burnUnstakeBps / 10000;
        const returnedAmt     = netAmount - burnedOnUnstake;
        const rewardHuman     = parseFloat(ethers.utils.formatUnits(reward, OBX_DECIMALS));

        // Notify backend
        await fetch('{{ route("user.staking.confirmUnstake") }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                position_id:        positionId,
                tx_hash:            unstakeTx.hash,
                reward_earned:      rewardHuman,
                burned_on_unstake:  burnedOnUnstake,
                returned_amount:    returnedAmt,
            })
        }).catch(() => {});

        setStatus(
            '<span class="text-success">✅ Unstaked!<br>' +
            'Tx: <a href="' + EXPLORER_TX_BASE + unstakeTx.hash + '" target="_blank" rel="noopener">' +
            unstakeTx.hash.substring(0, 20) + '…</a><br>' +
            '🔥 Burn on unstake visible on BSCScan.</span>'
        );
        setTimeout(() => location.reload(), 4000);
    } catch (e) {
        setStatus('<span class="text-danger">Unstake failed: ' + (e.reason || e.message) + '</span>');
    }
}

function setStatus(html) {
    const el = document.getElementById('stake-status');
    if (el) el.innerHTML = html;
}
</script>
@endsection
