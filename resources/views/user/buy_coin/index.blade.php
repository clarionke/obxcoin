@extends('user.master',['menu'=>'coin', 'sub_menu'=>'buy_coin'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
    .payment-card {
        border: 2px solid transparent;
        border-radius: 12px;
        cursor: pointer;
        transition: border-color .2s, box-shadow .2s;
        padding: 18px;
        margin-bottom: 12px;
    }
    .payment-card:hover       { border-color: #4f8ef7; box-shadow: 0 0 0 3px rgba(79,142,247,.15); }
    .payment-card.selected    { border-color: #4f8ef7; background: rgba(79,142,247,.07); }
    .payment-card .pm-icon    { font-size: 22px; margin-right: 10px; }
    .payment-card .pm-label   { font-weight: 600; }
    .payment-card .pm-desc    { font-size: 12px; color: #888; }
    #wc-panel, #np-panel      { display: none; margin-top: 16px; }
    #wc-panel.show, #np-panel.show { display: block; }
    .price-preview            { background: rgba(255,255,255,.04); border-radius: 8px; padding: 12px 16px; list-style:none; }
    .price-preview li         { padding: 4px 0; }
    #wc-status                { font-size: 13px; margin-top: 8px; }
</style>
@endsection

@section('content')
<div class="row">

    {{-- ── LEFT: Buy Form ──────────────────────────────────────────────────────── --}}
    <div class="col-xl-6 mb-xl-0 mb-4">
        <div class="card cp-user-custom-card">
            <div class="card-body">
                <div class="cp-user-card-header-area">
                    <h4>{{__('Buy')}} {{ settings('coin_name') }}</h4>
                </div>

                @if(session('success'))
                    <div class="alert alert-success mt-2">{{ session('success') }}</div>
                @endif
                @if(session('dismiss'))
                    <div class="alert alert-danger mt-2">{{ session('dismiss') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger mt-2">
                        @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                    </div>
                @endif

                @if($no_phase)
                    <p class="text-danger mt-3"><i class="fa fa-exclamation-triangle"></i> {{__('No active ICO phase.')}}</p>
                @elseif($activePhase['futurePhase'] == true)
                    <p class="text-warning mt-3"><i class="fa fa-clock-o"></i> {{__('New ICO phase starting soon.')}}</p>
                @endif

                <form action="{{route('buyCoinProcess')}}" method="POST" id="buy_coin_form">
                    @csrf
                    @if(!$no_phase && !$activePhase['futurePhase'] && isset($activePhase['pahse_info']))
                        <input type="hidden" name="phase_id" value="{{$activePhase['pahse_info']->id}}">
                    @endif
                    <input type="hidden" name="payment_type" id="payment_type_input" value="">

                    {{-- Amount --}}
                    <div class="form-group mt-3">
                        <label>{{__('Amount of')}} {{ settings('coin_name') }} {{__('to buy')}}</label>
                        <input type="number" step="any" min="1" name="coin" id="coin_amount"
                               class="form-control" placeholder="e.g. 1000"
                               oninput="updatePreview()" autocomplete="off"
                               value="{{ old('coin') }}">
                    </div>

                    {{-- Price preview --}}
                    <ul class="price-preview mb-3">
                        <li>1 {{ settings('coin_name') }} = <strong id="pr_rate">{{ $coin_price }}</strong> USD</li>
                        <li>{{__('Total')}} ≈ <strong id="pr_total">—</strong> USD</li>
                        @if(!$no_phase && !$activePhase['futurePhase'] && isset($activePhase['pahse_info']))
                        <li>{{__('Bonus')}} = <span id="pr_bonus">0</span> {{ settings('coin_name') }}</li>
                        @endif
                    </ul>

                    {{-- Payment method selection --}}
                    <div class="cp-user-payment-type">
                        <h5 class="mb-2">{{__('Choose Payment Method')}}</h5>

                        @if($nowpayments_enabled)
                        <div class="payment-card dark-bg2" id="pm_nowpayments" onclick="selectPayment('nowpayments')">
                            <span class="pm-icon">💳</span>
                            <span class="pm-label">NOWPayments</span>
                            <span class="pm-desc d-block mt-1">{{__('Pay with BTC, ETH, USDT, or 300+ cryptocurrencies')}}</span>
                        </div>
                        @endif

                        @if($walletconnect_enabled)
                        <div class="payment-card dark-bg2" id="pm_walletconnect" onclick="selectPayment('walletconnect')">
                            <span class="pm-icon">🔗</span>
                            <span class="pm-label">WalletConnect</span>
                            <span class="pm-desc d-block mt-1">{{__('Connect MetaMask / Trust Wallet — pay USDT directly on-chain')}}</span>
                        </div>
                        @endif

                        @if(!$nowpayments_enabled && !$walletconnect_enabled)
                        <p class="text-warning">{{__('No payment methods are currently active. Please contact the administrator.')}}</p>
                        @endif
                    </div>

                    {{-- NOWPayments panel --}}
                    <div id="np-panel">
                        <div class="form-group">
                            <label>{{__('Currency to pay with')}}</label>
                            <select name="pay_currency" class="form-control" id="np_currency">
                                <option value="btc">BTC — Bitcoin</option>
                                <option value="eth">ETH — Ethereum</option>
                                <option value="usdtbsc" selected>USDT (BEP-20)</option>
                                <option value="usdterc20">USDT (ERC-20)</option>
                                <option value="bnbbsc">BNB</option>
                                <option value="ltc">LTC — Litecoin</option>
                                <option value="trx">TRX — Tron</option>
                                <option value="sol">SOL — Solana</option>
                                <option value="doge">DOGE</option>
                                <option value="xrp">XRP</option>
                            </select>
                        </div>
                        <button type="submit" class="btn theme-btn">
                            <i class="fa fa-credit-card mr-1"></i> {{__('Pay with NOWPayments')}}
                        </button>
                    </div>

                    {{-- WalletConnect panel --}}
                    <div id="wc-panel">
                        <div class="alert alert-info py-2 mb-3" style="font-size:13px;">
                            <strong>{{__('How it works')}}:</strong>
                            {{__('Connect your wallet → approve USDT → tokens are sent automatically on-chain.')}}
                        </div>
                        <button type="button" class="btn theme-btn mb-2" id="wc_connect_btn" onclick="wcConnect()">
                            <i class="fa fa-link mr-1"></i> {{__('Connect Wallet')}}
                        </button>
                        <div id="wc_connected" style="display:none;">
                            <p class="text-success" id="wc_address_display"></p>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-info mr-2" onclick="addOBXToWallet()">
                                    <i class="fa fa-plus-circle mr-1"></i> {{__('Add OBX to Wallet')}}
                                </button>
                                <small class="text-muted">{{__('Import OBX token into MetaMask / Trust Wallet')}}</small>
                            </div>
                            <button type="button" class="btn theme-btn" id="wc_buy_btn" onclick="wcBuyTokens()">
                                <i class="fa fa-exchange mr-1"></i> {{__('Approve & Buy')}} {{ settings('coin_name') }}
                            </button>
                        </div>
                        <div id="wc-status"></div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: Phase Info ──────────────────────────────────────────────────── --}}
    <div class="col-xl-6">
        <div class="card cp-user-custom-card ico-phase-info-list">
            <div class="card-body">
                @if($no_phase)
                    <div class="cp-user-card-header-area"><h4>{{__("Today's Rate")}}</h4></div>
                    <ul class="ico-phase-ul">
                        <li>1 {{ settings('coin_name') }} = {{number_format($coin_price, 6)}} USD</li>
                    </ul>

                @elseif($activePhase['futurePhase'] == true)
                    <div class="cp-user-card-header-area future-ico-phase">
                        <h4 class="mb-3">{{__("New ICO Phase Starting Soon")}}</h4>
                        <p>{{__('Start at')}}: {{date('d M Y', strtotime($activePhase['futureDate']))}}</p>
                    </div>
                    <ul class="ico-phase-ul">
                        <li>1 {{ settings('coin_name') }} = {{number_format($coin_price, 6)}} USD</li>
                    </ul>

                @else
                    @php
                        $phase      = $activePhase['pahse_info'];
                        $total_sell = \App\Model\BuyCoinHistory::where('status', STATUS_SUCCESS)->where('phase_id', $phase->id)->sum('coin');
                        $target     = $phase->amount;
                        $progress   = $target > 0 ? min(100, (int) ceil(bcmul(100, $total_sell) / $target)) : 0;
                    @endphp
                    <div class="cp-user-card-header-area">
                        <h4>{{__("ICO Phase Running")}}: {{ $phase->phase_name }}</h4>
                    </div>
                    <ul class="ico-phase-ul">
                        <li><p>{{__('Rate')}}:</p><p>1 {{ settings('coin_name') }} = {{number_format($phase->rate,6)}} USD</p></li>
                        <li><p>{{__('Bonus')}}:</p><p>{{ number_format($phase->bonus,2) }}%</p></li>
                        <li><p>{{__('Start')}}:</p><p>{{ date('d M Y', strtotime($phase->start_date)) }}</p></li>
                        <li><p>{{__('End')}}:</p><p>{{ date('d M Y', strtotime($phase->end_date)) }}</p></li>
                    </ul>
                    <hr>
                    <h5>{{ settings('coin_name') }} {{__('Sales Progress')}}</h5>
                    <ul class="ico-phase-ul ico-phase-amount">
                        <li class="total_sale"><span>{{__('RAISED')}}</span><span>{{number_format($total_sell,2)}} {{ settings('coin_name') }}</span></li>
                        <li class="total_target"><span>{{__('TARGET')}}</span><span>{{number_format($target,2)}} {{ settings('coin_name') }}</span></li>
                    </ul>
                    <div class="progress mb-3">
                        <div class="progress-bar" role="progressbar" style="width:{{$progress}}%"
                             aria-valuenow="{{$progress}}" aria-valuemin="0" aria-valuemax="100">{{$progress}}%</div>
                    </div>
                    <p class="card-text card-text-2 mb-2">
                        <span>{{__("SALES END IN")}}</span>
                        <span>{{ date('d M Y', strtotime($phase->end_date)) }}</span>
                    </p>
                    <div id="clockdiv" class="countdown-row">
                        <div class="countdown-section"><span class="days"></span><div class="smalltext">{{__('Days')}}</div></div>
                        <div class="countdown-section"><span class="hours"></span><div class="smalltext">{{__('Hours')}}</div></div>
                        <div class="countdown-section"><span class="minutes"></span><div class="smalltext">{{__('Minutes')}}</div></div>
                        <div class="countdown-section"><span class="seconds"></span><div class="smalltext">{{__('Seconds')}}</div></div>
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@section('script')
<script>
// ── Price preview ────────────────────────────────────────────────────────────
const COIN_PRICE = {{ (float) $coin_price }};
@if(!$no_phase && !$activePhase['futurePhase'] && isset($activePhase['pahse_info']))
const PHASE_BONUS_PCT = {{ (float) $activePhase['pahse_info']->bonus }};
@else
const PHASE_BONUS_PCT = 0;
@endif

function updatePreview() {
    const amt   = parseFloat(document.getElementById('coin_amount').value) || 0;
    const bonus = amt * PHASE_BONUS_PCT / 100;
    const net   = amt - bonus;
    document.getElementById('pr_total').innerText = (net * COIN_PRICE).toFixed(4) + ' USD';
    const bonusEl = document.getElementById('pr_bonus');
    if (bonusEl) bonusEl.innerText = bonus.toFixed(4);
}

// ── Payment method selection ─────────────────────────────────────────────────
function selectPayment(method) {
    document.querySelectorAll('.payment-card').forEach(el => el.classList.remove('selected'));
    document.getElementById('np-panel').classList.remove('show');
    document.getElementById('wc-panel').classList.remove('show');
    document.getElementById('payment_type_input').value = '';

    if (method === 'nowpayments') {
        const el = document.getElementById('pm_nowpayments');
        if (el) el.classList.add('selected');
        document.getElementById('np-panel').classList.add('show');
        document.getElementById('payment_type_input').value = '{{ NOWPAYMENTS }}';
    } else if (method === 'walletconnect') {
        const el = document.getElementById('pm_walletconnect');
        if (el) el.classList.add('selected');
        document.getElementById('wc-panel').classList.add('show');
        document.getElementById('payment_type_input').value = '{{ WALLETCONNECT }}';
    }
}

// Guard: require payment method selection before form submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('buy_coin_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!document.getElementById('payment_type_input').value) {
                e.preventDefault();
                alert('{{ __("Please select a payment method first.") }}');
            }
        });
    }
});

// ── Countdown (phase active) ─────────────────────────────────────────────────
@if(!$no_phase && !$activePhase['futurePhase'] && isset($activePhase['pahse_info']))
(function countdown() {
    const endDate = new Date('{{ $activePhase['pahse_info']->end_date }}').getTime();
    const tick = setInterval(function() {
        const diff = endDate - Date.now();
        if (diff <= 0) { clearInterval(tick); return; }
        const el = document.getElementById('clockdiv');
        if (!el) return;
        el.querySelector('.days').innerText    = String(Math.floor(diff / 86400000)).padStart(2,'0');
        el.querySelector('.hours').innerText   = String(Math.floor((diff % 86400000) / 3600000)).padStart(2,'0');
        el.querySelector('.minutes').innerText = String(Math.floor((diff % 3600000) / 60000)).padStart(2,'0');
        el.querySelector('.seconds').innerText = String(Math.floor((diff % 60000) / 1000)).padStart(2,'0');
    }, 1000);
})();
@endif

// ── WalletConnect ────────────────────────────────────────────────────────────
const WC_PROJECT_ID      = @json($wc_project_id);
const WC_CHAIN_ID        = {{ $wc_chain_id }};
const PRESALE_ADDRESS    = @json($presale_contract);
const USDT_ADDRESS       = @json($usdt_address);
const OBX_TOKEN_ADDRESS  = @json($obx_token_contract);
const OBX_TOKEN_SYMBOL   = @json($obx_token_symbol);
const OBX_TOKEN_DECIMALS = {{ (int)$obx_token_decimals }};
const OBX_TOKEN_LOGO     = @json($obx_token_logo_url);
// Block explorer base per chain
const EXPLORER_TX_BASE = WC_CHAIN_ID === 56  ? 'https://bscscan.com/tx/'
                       : WC_CHAIN_ID === 97  ? 'https://testnet.bscscan.com/tx/'
                       : WC_CHAIN_ID === 1   ? 'https://etherscan.io/tx/'
                       : WC_CHAIN_ID === 137 ? 'https://polygonscan.com/tx/'
                       : 'https://bscscan.com/tx/';

const ERC20_ABI = [
    {"inputs":[{"name":"spender","type":"address"},{"name":"amount","type":"uint256"}],"name":"approve","outputs":[{"name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},
    {"inputs":[{"name":"owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"}
];
const PRESALE_ABI = [
    {"inputs":[{"name":"contractPhaseIndex","type":"uint256"},{"name":"usdtAmount","type":"uint256"}],"name":"buyTokens","outputs":[],"stateMutability":"nonpayable","type":"function"},
    {"inputs":[],"name":"activePhaseIndex","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"}
];

let wcProvider = null, wcSigner = null, wcAddress = null;

function loadScript(src) {
    return new Promise((res, rej) => {
        if (document.querySelector(`script[src="${src}"]`)) { res(); return; }
        const s = document.createElement('script');
        s.src = src; s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
    });
}

async function wcConnect() {
    if (!WC_PROJECT_ID) {
        setStatus('<span class="text-danger">WalletConnect Project ID not configured. Contact admin.</span>');
        return;
    }
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
        const network = await web3Provider.getNetwork();
        if (network.chainId !== WC_CHAIN_ID) {
            setStatus(`<span class="text-danger">Wrong network (chain ${network.chainId}). Switch to chain ID ${WC_CHAIN_ID} in your wallet.</span>`);
            return;
        }

        wcSigner  = web3Provider.getSigner();
        wcAddress = await wcSigner.getAddress();

        document.getElementById('wc_connect_btn').style.display = 'none';
        document.getElementById('wc_connected').style.display   = 'block';
        document.getElementById('wc_address_display').innerText =
            '✅ Connected: ' + wcAddress.substring(0,6) + '…' + wcAddress.substring(38);
        setStatus('');

        // Automatically offer to add OBX token to the connected wallet
        if (OBX_TOKEN_ADDRESS) {
            await addOBXToWallet(true);
        }
    } catch (e) {
        setStatus('<span class="text-danger">Connection failed: ' + e.message + '</span>');
    }
}

/**
 * Prompt the wallet to import/watch the OBX token via EIP-747 (wallet_watchAsset).
 * Works with MetaMask, Trust Wallet (WalletConnect), and any EIP-1193 provider.
 *
 * @param {boolean} silent  When true, suppresses the status message on success
 *                          (called automatically on connect; user also has a button).
 */
async function addOBXToWallet(silent = false) {
    if (!OBX_TOKEN_ADDRESS) {
        setStatus('<span class="text-danger">OBX token contract not configured. Contact admin.</span>');
        return;
    }
    try {
        const provider = wcProvider
            ? new ethers.providers.Web3Provider(wcProvider)
            : window.ethereum
                ? new ethers.providers.Web3Provider(window.ethereum)
                : null;

        if (!provider) {
            setStatus('<span class="text-danger">No wallet connected. Click Connect Wallet first.</span>');
            return;
        }

        const wasAdded = await provider.provider.request({
            method: 'wallet_watchAsset',
            params: {
                type:    'ERC20',
                options: {
                    address:  OBX_TOKEN_ADDRESS,
                    symbol:   OBX_TOKEN_SYMBOL,
                    decimals: OBX_TOKEN_DECIMALS,
                    image:    OBX_TOKEN_LOGO || undefined,
                },
            },
        });

        if (!silent) {
            setStatus(wasAdded
                ? '<span class="text-success">✅ ' + OBX_TOKEN_SYMBOL + ' added to your wallet!</span>'
                : '<span class="text-warning">Token add was dismissed.</span>'
            );
        }
    } catch (e) {
        if (!silent) {
            setStatus('<span class="text-danger">Could not add token: ' + e.message + '</span>');
        }
    }
}

async function wcBuyTokens() {
    const coinAmt = parseFloat(document.getElementById('coin_amount').value);
    if (!coinAmt || coinAmt <= 0) { setStatus('<span class="text-danger">Enter a valid amount first.</span>'); return; }
    if (!wcSigner)                { setStatus('<span class="text-danger">Connect your wallet first.</span>'); return; }
    if (!PRESALE_ADDRESS)         { setStatus('<span class="text-danger">Presale contract not configured. Contact admin.</span>'); return; }

    try {
        setStatus('⏳ Calculating USDT cost…');
        const netCoins  = coinAmt - (coinAmt * PHASE_BONUS_PCT / 100);
        const usdtCost  = netCoins * COIN_PRICE;
        // USDT-BEP20 uses 18 decimals
        const usdtWei   = ethers.utils.parseUnits(Math.max(usdtCost, 0.000001).toFixed(6), 18);

        const usdtContract    = new ethers.Contract(USDT_ADDRESS, ERC20_ABI, wcSigner);
        const presaleContract = new ethers.Contract(PRESALE_ADDRESS, PRESALE_ABI, wcSigner);

        const balance = await usdtContract.balanceOf(wcAddress);
        if (balance.lt(usdtWei)) {
            setStatus(`<span class="text-danger">Insufficient USDT balance. Need ≈ ${usdtCost.toFixed(4)} USDT.</span>`);
            return;
        }

        let phaseIndex;
        try   { phaseIndex = await presaleContract.activePhaseIndex(); }
        catch (_) { phaseIndex = ethers.BigNumber.from(0); }

        // Step 1: Approve
        setStatus('⏳ Step 1/2: Approving USDT spend… (confirm in wallet)');
        const approveTx = await usdtContract.approve(PRESALE_ADDRESS, usdtWei);
        setStatus('⏳ Waiting for approval…');
        await approveTx.wait(1);

        // Step 2: Buy
        setStatus('⏳ Step 2/2: Buying tokens… (confirm in wallet)');
        const buyTx = await presaleContract.buyTokens(phaseIndex, usdtWei);
        setStatus('⏳ Waiting for confirmation…');
        await buyTx.wait(1);

        // Notify backend (fire & forget — presale webhook also picks up on-chain event)
        const phaseIdInput = document.querySelector('input[name="phase_id"]');
        fetch('{{ route("buyCoinProcess") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                coin:             coinAmt,
                payment_type:     {{ WALLETCONNECT }},
                phase_id:         phaseIdInput ? phaseIdInput.value : '',
                tx_hash:          buyTx.hash,
                wc_buyer_address: wcAddress
            })
        }).catch(() => {});

        setStatus(
            `<span class="text-success">✅ Purchase confirmed!<br>` +
            `Tx: <a href="${EXPLORER_TX_BASE}${buyTx.hash}" target="_blank" rel="noopener">${buyTx.hash.substring(0,20)}…</a><br>` +
            `Your OBX tokens will appear in your wallet shortly.</span>`
        );
        document.getElementById('wc_buy_btn').disabled = true;

    } catch (e) {
        setStatus('<span class="text-danger">Transaction failed: ' + (e.reason || e.message) + '</span>');
    }
}

function setStatus(html) {
    const el = document.getElementById('wc-status');
    if (el) el.innerHTML = html;
}
</script>
@endsection