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
#obx-price-bar          { background: rgba(99,102,241,.08); border-radius: 8px; padding: 10px 16px;
                           display: grid; grid-template-columns: repeat(4,1fr) auto;
                           align-items: center; gap: 8px 16px; margin-bottom: 16px;
                           border: 1px solid rgba(99,102,241,.2); font-size: 13px; }
#obx-price-bar .pbar-lbl{ color: var(--muted); font-size: 11px; white-space: nowrap; }
#obx-price-bar .pbar-val{ font-weight: 700; color: var(--text); font-size: 14px; }
#obx-price-bar .pbar-hint{ font-size:11px; color:var(--muted); white-space:nowrap; text-align:right; }
#wallet-balance-row     { background: rgba(99,102,241,.06); border-radius: 8px; padding: 8px 14px;
                           margin-bottom: 10px; font-size: 13px; display: none;
                           flex-wrap: wrap; gap: 6px; align-items: center; }
/* Pool card meta wraps safely */
.pool-card .pool-meta    { font-size: 12px; color: var(--muted); margin-top: 4px; flex-wrap: wrap; }
.pool-card .pool-meta .sep { color: var(--muted); margin: 0 4px; }
/* Position rows stack on mobile */
.position-row .pr-actions { flex-shrink: 0; }
/* Stake preview list */
.stake-preview-list      { list-style:none; padding: 12px 16px; background: rgba(255,255,255,.04); border-radius: 8px; }
.stake-preview-list li   { padding: 3px 0; font-size: 13px; }
/* Responsive */
@media (max-width: 991px) {
    #obx-price-bar { grid-template-columns: repeat(2,1fr); }
    #obx-price-bar .pbar-hint { display: none; }
}
@media (max-width: 575px) {
    #obx-price-bar { grid-template-columns: repeat(2,1fr); padding: 10px 12px; gap: 10px 10px; }
    .pool-card { padding: 14px 12px; }
    .pool-card .pool-name { font-size: 15px; }
    .pool-card .pool-apy  { font-size: 19px; }
    .pool-card .d-flex    { flex-direction: column; gap: 8px; }
    .pool-card .text-right{ text-align: left !important; }
    .position-row .d-flex { flex-wrap: wrap; gap: 8px; }
    .position-row .pr-actions { width: 100%; }
    #wallet-balance-row   { display: flex; }
}
</style>
@endsection

@section('content')
<div class="row mx-0">

    {{-- ── LEFT: Stake Form ──────────────────────────────────────────────────── --}}
    <div class="col-lg-7 mb-4">
        <div class="card cp-user-custom-card">
            <div class="card-body">
                <div class="cp-user-card-header-area">
                    <h4><i class="fa fa-lock mr-2" style="color:var(--accent);"></i>{{__('Stake OBX')}}</h4>
                </div>

                {{-- ── Live price ticker ──────────────────────────────── --}}
                <div id="obx-price-bar">
                    <div>
                        <div class="pbar-lbl">{{__('OBX Price')}}</div>
                        <div class="pbar-val" id="pbar_price">${{ settings('coin_price') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="pbar-lbl">{{__('24h Change')}}</div>
                        <div class="pbar-val" id="pbar_change">{{ settings('obx_price_change_24h') !== null ? settings('obx_price_change_24h').'%' : '—' }}</div>
                    </div>
                    <div>
                        <div class="pbar-lbl">{{__('Market Cap')}}</div>
                        <div class="pbar-val" id="pbar_mcap">{{ settings('obx_market_cap') ? '$'.number_format((float)settings('obx_market_cap'),0) : '—' }}</div>
                    </div>
                    <div>
                        <div class="pbar-lbl">{{__('24h Volume')}}</div>
                        <div class="pbar-val" id="pbar_vol">{{ settings('obx_volume_24h') ? '$'.number_format((float)settings('obx_volume_24h'),0) : '—' }}</div>
                    </div>
                    <div class="pbar-hint">
                        <i class="fa fa-refresh mr-1" id="price_refresh_icon"></i>
                        {{__('Auto-updates every 30s')}}
                    </div>
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
                                    <span class="sep">•</span>
                                    <i class="fa fa-fire mr-1" style="color:var(--danger);"></i>{{__('Burn on stake')}}: {{ $pool->burn_stake_pct }}
                                    <span class="sep">•</span> {{__('Burn on unstake')}}: {{ $pool->burn_unstake_pct }}
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
                    {{-- Wallet balance shown after connect --}}
                    <div id="wallet-balance-row">
                        <i class="fa fa-wallet mr-1" style="color:var(--accent);"></i>
                        {{__('Wallet OBX Balance:')}} <strong id="wallet_obx_balance">—</strong> OBX
                        &nbsp;
                        <button type="button" class="btn btn-xs btn-outline-info" onclick="useMaxBalance()" style="font-size:11px;padding:1px 8px;">Max</button>
                    </div>
                    <div class="input-group">
                        <input type="number" step="any" min="0.001" id="stake_amount"
                               class="form-control" placeholder="e.g. 1000"
                               oninput="updateStakePreview()" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text" id="stake_usd_val" style="font-size:12px;min-width:90px;">≈ $0.00</span>
                        </div>
                    </div>
                </div>

                {{-- Preview --}}
                <ul class="stake-preview-list mb-3" id="stake-preview" style="display:none;">
                    <li>{{__('Pool')}}:         <strong id="pr_pool_name">—</strong></li>
                    <li>{{__('Lock period')}}:  <strong id="pr_duration">—</strong></li>
                    <li>{{__('APY')}}:          <strong id="pr_apy">—</strong></li>
                    <li>{{__('Gross stake')}}: <strong id="pr_gross">—</strong> OBX <em id="pr_gross_usd" style="color:var(--muted);font-size:12px;"></em></li>
                    <li style="color:var(--danger);">{{__('Burn on stake')}}: <strong id="pr_burn_stake">—</strong> OBX</li>
                    <li>{{__('Net staked')}}:  <strong id="pr_net">—</strong> OBX</li>
                    <li style="color:var(--success);">{{__('Est. reward at maturity')}}: <strong id="pr_reward">—</strong> OBX</li>
                    <li style="color:var(--danger);">{{__('Burn on unstake')}}:   <strong id="pr_burn_unstake">—</strong> OBX</li>
                    <li style="color:var(--success);">{{__('Est. total return')}}:  <strong id="pr_return">—</strong> OBX <em id="pr_return_usd" style="color:var(--muted);font-size:12px;"></em></li>
                </ul>

                {{-- Connect & Stake flow --}}
                <div class="mt-3">
                    <button type="button" class="btn theme-btn mb-2 w-100" id="wc_connect_btn" onclick="wcConnect()">
                        <i class="fa fa-link mr-1"></i> {{__('Connect Wallet')}}
                    </button>
                    <div id="wc_connected" style="display:none;">
                        <p class="text-success mb-2" id="wc_address_display" style="font-size:12px;word-break:break-all;"></p>
                        <button type="button" class="btn theme-btn w-100" id="wc_stake_btn" onclick="wcStake()" disabled>
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
    <div class="col-lg-5">
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
                        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
                            <div style="min-width:0;flex:1;">
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
                                <div class="pr-lock mt-1" style="word-break:break-all;">
                                    @if($pos->tx_hash_stake)
                                        {{__('Stake tx:')}}
                                        <a href="{{ explorer_tx_url($pos->tx_hash_stake) }}" target="_blank" rel="noopener noreferrer" style="color:var(--accent);">
                                            {{ substr($pos->tx_hash_stake, 0, 14) }}…
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="pr-actions flex-shrink-0">
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
const OBX_PRICE_API     = '{{ url("/api/obx-price") }}';
const BUY_COIN_URL      = '{{ route("buyCoin") }}';
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
let selectedPool       = null;
let wcProvider         = null;
let wcSigner           = null;
let wcAddress          = null;
let liveObxPrice       = parseFloat('{{ settings("coin_price") ?: "0" }}') || 0;
let walletObxBalance   = 0;  // balance in OBX (human units)

// ── Live price auto-refresh ─────────────────────────────────────────────
function fetchLivePrice() {
    const icon = document.getElementById('price_refresh_icon');
    if (icon) icon.classList.add('fa-spin');

    fetch(OBX_PRICE_API)
        .then(r => r.json())
        .then(data => {
            liveObxPrice = data.price || 0;
            const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            set('pbar_price',  liveObxPrice > 0    ? '$' + liveObxPrice.toFixed(6) : '—');

            const change = data.change_24h || 0;
            const changeEl = document.getElementById('pbar_change');
            if (changeEl) {
                changeEl.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
                changeEl.style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
            }
            set('pbar_mcap', data.market_cap > 0 ? '$' + Number(data.market_cap).toLocaleString('en-US', {maximumFractionDigits:0}) : '—');
            set('pbar_vol',  data.volume_24h  > 0 ? '$' + Number(data.volume_24h).toLocaleString('en-US',  {maximumFractionDigits:0}) : '—');

            // Update USD value in stake amount
            updateStakePreview();
        })
        .catch(() => {}) // fail silently — cached price still shown
        .finally(() => { if (icon) icon.classList.remove('fa-spin'); });
}

// Refresh price every 30 seconds
setInterval(fetchLivePrice, 30_000);
document.addEventListener('DOMContentLoaded', fetchLivePrice);

// ── Pool selection ───────────────────────────────────────────────────────
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

    // Update USD value next to input
    const usdEl = document.getElementById('stake_usd_val');
    if (usdEl) usdEl.textContent = liveObxPrice > 0 ? '≈ $' + (gross * liveObxPrice).toFixed(2) : '';

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
    const grossUsdEl = document.getElementById('pr_gross_usd');
    if (grossUsdEl && liveObxPrice > 0) grossUsdEl.textContent = '(≈ $' + (gross * liveObxPrice).toFixed(2) + ')';
    document.getElementById('pr_burn_stake').innerText   = burnStake.toFixed(4);
    document.getElementById('pr_net').innerText          = net.toFixed(4);
    document.getElementById('pr_reward').innerText       = reward.toFixed(4);
    document.getElementById('pr_burn_unstake').innerText = burnUnstake.toFixed(4);
    document.getElementById('pr_return').innerText       = totalReturn.toFixed(4);
    const returnUsdEl = document.getElementById('pr_return_usd');
    if (returnUsdEl && liveObxPrice > 0) returnUsdEl.textContent = '(≈ $' + (totalReturn * liveObxPrice).toFixed(2) + ')';
    document.getElementById('stake-preview').style.display = 'block';
}

function useMaxBalance() {
    if (walletObxBalance > 0) {
        document.getElementById('stake_amount').value = walletObxBalance.toFixed(4);
        updateStakePreview();
    }
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

        // Fetch wallet OBX balance to show + enable Max button
        try {
            const obxReadOnly = new ethers.Contract(OBX_TOKEN_ADDRESS, ERC20_ABI, web3Provider);
            const balWei      = await obxReadOnly.balanceOf(wcAddress);
            walletObxBalance  = parseFloat(ethers.utils.formatUnits(balWei, OBX_DECIMALS));
            const balEl = document.getElementById('wallet_obx_balance');
            if (balEl) balEl.textContent = walletObxBalance.toLocaleString('en-US', {maximumFractionDigits: 4});
            const balRow = document.getElementById('wallet-balance-row');
            if (balRow) balRow.style.display = 'block';
        } catch (_) {}

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
            const have = parseFloat(ethers.utils.formatUnits(balance, OBX_DECIMALS));
            setStatus(
                '<span class="text-danger">Insufficient OBX balance. ' +
                'Your wallet holds <strong>' + have.toLocaleString('en-US', {maximumFractionDigits:4}) + ' OBX</strong>. ' +
                'Purchase OBX first via the <a href="' + BUY_COIN_URL + '">Buy Coin</a> page.</span>'
            );
            return;
        }

        // Step 1: Approve
        setStatus('⏳ Step 1/2: Approving OBX spend… (confirm in wallet)');
        const approveTx = await obxContract.approve(STAKING_ADDRESS, grossWei);
        setStatus('⏳ Waiting for approval confirmation…');
        await approveTx.wait(1);

        // Step 2: Stake — use 0-based pool index (DB id - 1)
        const poolIdOnchain = selectedPool.id - 1;

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
