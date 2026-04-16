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
    #np-panel                 { display: none; margin-top: 16px; }
    #np-panel.show            { display: block; }
    .price-preview            { background: rgba(255,255,255,.04); border-radius: 8px; padding: 12px 16px; list-style:none; }
    .price-preview li         { padding: 4px 0; }
    #wc-status                { font-size: 13px; margin-top: 8px; }
    /* CMC live price bar */
    #cmc-price-bar { background:rgba(0,0,0,.3); border-radius:10px; border-left:3px solid #4f8ef7; padding:12px 16px; margin-bottom:16px; }
    #cmc-price-bar .pstat .val { font-size:15px; font-weight:700; display:block; }
    #cmc-price-bar .pstat .lbl { font-size:10px; color:#888; text-transform:uppercase; letter-spacing:.5px; }
    .chg-up { color:#00e676; } .chg-dn { color:#f44336; }
</style>
@endsection

@section('content')
<div class="row mx-0">

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
                    <input type="hidden" name="wc_buyer_address" id="wc_buyer_address" value="">
                    <input type="hidden" name="tx_hash" id="wc_tx_hash" value="">

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

                        @php
                            $has_payment_methods = ($nowpayments_enabled || $walletconnect_enabled);
                        @endphp

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
                            <span class="pm-desc d-block mt-1">{{__('Pay on-chain with USDT from your connected wallet')}}</span>
                        </div>
                        @endif

                        @if(!$has_payment_methods)
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
                    <div id="wc-panel" style="display:none; margin-top:16px;">
                        <p class="mb-2 text-muted">{{__('You will approve USDT and then confirm the on-chain purchase transaction in your wallet.')}}</p>
                        <button type="button" class="btn theme-btn" id="wc-buy-btn" onclick="startWalletConnectPurchase()">
                            <i class="fa fa-link mr-1"></i> {{__('Pay with WalletConnect')}}
                        </button>
                        <div id="wc-status" class="mt-2"></div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: Phase Info ──────────────────────────────────────────────────── --}}
    <div class="col-xl-6">
        <div class="card cp-user-custom-card ico-phase-info-list">
            <div class="card-body">
                {{-- CMC Live Market Price --}}
                <div id="cmc-price-bar">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small><i class="fa fa-circle text-success" style="font-size:8px;"></i> <strong>{{__('Live Market Price')}}</strong></small>
                        <small class="text-muted" id="cmc_last_updated">—</small>
                    </div>
                    <div class="row text-center">
                        <div class="col-6 col-sm-3 pstat mb-2">
                            <span class="val" id="cmc_price">{{ number_format((float)settings('coin_price'), 6) }}</span>
                            <span class="lbl">OBX/USD</span>
                        </div>
                        <div class="col-6 col-sm-3 pstat mb-2">
                            <span class="val" id="cmc_change">{{ settings('obx_price_change_24h') ? number_format((float)settings('obx_price_change_24h'),2).'%' : '—' }}</span>
                            <span class="lbl">24h Change</span>
                        </div>
                        <div class="col-6 col-sm-3 pstat mb-2">
                            <span class="val" id="cmc_mcap">—</span>
                            <span class="lbl">Mkt Cap</span>
                        </div>
                        <div class="col-6 col-sm-3 pstat mb-2">
                            <span class="val" id="cmc_vol">—</span>
                            <span class="lbl">24h Volume</span>
                        </div>
                    </div>
                </div>

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
const WC_ENABLED          = {{ $walletconnect_enabled ? 'true' : 'false' }};
const WC_PROJECT_ID       = @json($wc_project_id ?? '');
const WC_CHAIN_ID         = {{ (int)($wc_chain_id ?? 56) }};
const PRESALE_CONTRACT    = @json($presale_contract ?? '');
const USDT_ADDRESS        = @json($usdt_address ?? '');
const CONTRACT_PHASE_IDX  = {{ (!$no_phase && !$activePhase['futurePhase'] && isset($activePhase['pahse_info']) && $activePhase['pahse_info']->contract_phase_index !== null) ? (int)$activePhase['pahse_info']->contract_phase_index : 'null' }};
const PHASE_INFO_URL      = @json(url('/api/presale/phase-info/0'));
const BUY_RATE_URL        = @json(route('buyCoinRate'));

const ERC20_ABI = [
    'function approve(address spender, uint256 amount) returns (bool)'
];

const PRESALE_ABI = [
    'function buyTokens(uint256 contractPhaseIndex, uint256 usdtAmount)'
];

// ── Price preview ────────────────────────────────────────────────────────────
let COIN_PRICE = {{ (float) $coin_price }};
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
    const wcPanel = document.getElementById('wc-panel');
    if (wcPanel) wcPanel.style.display = 'none';
    document.getElementById('payment_type_input').value = '';

    if (method === 'nowpayments') {
        const el = document.getElementById('pm_nowpayments');
        if (el) el.classList.add('selected');
        document.getElementById('np-panel').classList.add('show');
        document.getElementById('payment_type_input').value = '{{ NOWPAYMENTS }}';
    } else if (method === 'walletconnect') {
        const el = document.getElementById('pm_walletconnect');
        if (el) el.classList.add('selected');
        if (wcPanel) wcPanel.style.display = 'block';
        document.getElementById('payment_type_input').value = '{{ WALLETCONNECT }}';
    }
}

function setWcStatus(message, isError = false) {
    const el = document.getElementById('wc-status');
    if (!el) return;
    el.innerHTML = isError ? '<span class="text-danger">' + message + '</span>' : message;
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector('script[src="' + src + '"]')) { resolve(); return; }
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
}

async function fetchQuoteFromServer(amount) {
    const formData = new URLSearchParams();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('amount', String(amount));

    const r = await fetch(BUY_RATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: formData.toString(),
    });

    if (!r.ok) throw new Error('{{ __('Unable to fetch price quote.') }}');
    const data = await r.json();
    const totalUsd = parseFloat(data.coin_price || 0);
    if (!totalUsd || totalUsd <= 0) {
        throw new Error('{{ __('Invalid quote amount returned by server.') }}');
    }
    return totalUsd;
}

async function resolveContractPhaseIndex() {
    if (CONTRACT_PHASE_IDX !== null && Number(CONTRACT_PHASE_IDX) >= 0) {
        return Number(CONTRACT_PHASE_IDX);
    }

    try {
        const r = await fetch(PHASE_INFO_URL, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!r.ok) return null;

        const data = await r.json();
        const idx = Number(data.active_phase);
        if (Number.isFinite(idx) && idx >= 0) {
            return idx;
        }
    } catch (e) {
        // fall through to null return
    }

    return null;
}

async function startWalletConnectPurchase() {
    const buyBtn = document.getElementById('wc-buy-btn');
    const form = document.getElementById('buy_coin_form');
    const amount = parseFloat(document.getElementById('coin_amount').value || 0);

    try {
        if (!WC_ENABLED) throw new Error('{{ __('WalletConnect is not enabled.') }}');
        if (!amount || amount <= 0) throw new Error('{{ __('Enter a valid coin amount first.') }}');
        if (!PRESALE_CONTRACT) throw new Error('{{ __('Presale contract is not configured.') }}');
        if (!USDT_ADDRESS) throw new Error('{{ __('USDT address is not configured for this chain.') }}');
        if (!WC_PROJECT_ID) throw new Error('{{ __('WalletConnect project ID is not configured.') }}');

        if (buyBtn) buyBtn.disabled = true;
        setWcStatus('⏳ {{ __('Preparing transaction...') }}');

        const phaseIdx = await resolveContractPhaseIndex();
        if (phaseIdx === null) {
            throw new Error('{{ __('Unable to resolve active on-chain phase right now. Please retry shortly.') }}');
        }

        const totalUsd = await fetchQuoteFromServer(amount);

        if (!window.ethers) {
            await loadScript('{{ asset("js/vendor/ethers-5.7.2.umd.min.js") }}');
        }
        if (!window.WalletConnectProvider) {
            await loadScript('{{ asset("js/vendor/walletconnect-web3-provider-1.8.0.min.js") }}');
        }

        const rpcMap = {};
        rpcMap[56] = 'https://bsc-dataseed.binance.org/';
        rpcMap[97] = 'https://bsc-testnet-rpc.publicnode.com';

        const wcProvider = new WalletConnectProvider.default({ projectId: WC_PROJECT_ID, rpc: rpcMap });
        await wcProvider.enable();

        const web3Provider = new ethers.providers.Web3Provider(wcProvider);
        const network = await web3Provider.getNetwork();
        if (Number(network.chainId) !== Number(WC_CHAIN_ID)) {
            throw new Error('{{ __('Wrong network. Please switch wallet network to chain ID') }} ' + WC_CHAIN_ID + '.');
        }

        const signer = web3Provider.getSigner();
        const buyerAddress = await signer.getAddress();
        const usdtAmount = ethers.utils.parseUnits(Number(totalUsd).toFixed(6), 6);

        const usdt = new ethers.Contract(USDT_ADDRESS, ERC20_ABI, signer);
        const presale = new ethers.Contract(PRESALE_CONTRACT, PRESALE_ABI, signer);

        setWcStatus('⏳ {{ __('Step 1/2: Approve USDT in wallet...') }}');
        const approveTx = await usdt.approve(PRESALE_CONTRACT, usdtAmount);
        await approveTx.wait(1);

        setWcStatus('⏳ {{ __('Step 2/2: Confirm purchase in wallet...') }}');
        const buyTx = await presale.buyTokens(phaseIdx, usdtAmount);
        await buyTx.wait(1);

        document.getElementById('payment_type_input').value = '{{ WALLETCONNECT }}';
        document.getElementById('wc_buyer_address').value = buyerAddress;
        document.getElementById('wc_tx_hash').value = buyTx.hash;

        setWcStatus('<span class="text-success">{{ __('On-chain purchase confirmed. Finalizing...') }}</span>');
        form.submit();
    } catch (e) {
        setWcStatus((e && e.message) ? e.message : '{{ __('WalletConnect transaction failed.') }}', true);
        if (buyBtn) buyBtn.disabled = false;
    }
}

// ── CMC Live Price ──────────────────────────────────────────────────────────
const OBX_PRICE_API = '{{ route("api.obx.price") }}';

function fmtCurrency(n) {
    return n >= 1e9 ? '$' + (n/1e9).toFixed(2) + 'B'
         : n >= 1e6 ? '$' + (n/1e6).toFixed(1) + 'M'
         : n >= 1e3 ? '$' + (n/1e3).toFixed(1) + 'K'
         : '$' + Number(n).toFixed(2);
}

function fetchLivePrice() {
    fetch(OBX_PRICE_API)
        .then(r => r.json())
        .then(d => {
            if (!d || !d.price) return;
            const p   = parseFloat(d.price);
            const chg = parseFloat(d.change_24h || 0);
            COIN_PRICE = p;

            const priceEl = document.getElementById('cmc_price');
            const chgEl   = document.getElementById('cmc_change');
            const mcapEl  = document.getElementById('cmc_mcap');
            const volEl   = document.getElementById('cmc_vol');
            const updEl   = document.getElementById('cmc_last_updated');
            const rateEl  = document.getElementById('pr_rate');

            if (priceEl) priceEl.innerText  = p.toFixed(6);
            if (chgEl)   chgEl.innerHTML    = '<span class="' + (chg >= 0 ? 'chg-up' : 'chg-dn') + '">' + (chg >= 0 ? '+' : '') + chg.toFixed(2) + '%</span>';
            if (mcapEl)  mcapEl.innerText   = fmtCurrency(parseFloat(d.market_cap  || 0));
            if (volEl)   volEl.innerText    = fmtCurrency(parseFloat(d.volume_24h  || 0));
            if (updEl)   updEl.innerText    = d.last_updated ? new Date(d.last_updated).toLocaleTimeString() : '';
            if (rateEl)  rateEl.innerText   = p.toFixed(6);

            updatePreview();
        })
        .catch(function() {});
}

fetchLivePrice();
setInterval(fetchLivePrice, 30000);

// Guard: require payment method selection before form submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('buy_coin_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const paymentType = document.getElementById('payment_type_input').value;
            if (!paymentType) {
                e.preventDefault();
                alert('{{ __("Please select a payment method first.") }}');
                return;
            }

            if (paymentType === '{{ WALLETCONNECT }}') {
                const buyer = document.getElementById('wc_buyer_address').value;
                const tx = document.getElementById('wc_tx_hash').value;
                if (!buyer || !tx) {
                    e.preventDefault();
                    alert('{{ __("Complete the WalletConnect transaction first.") }}');
                    return;
                }
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

// NOWPayments-only buy flow.
</script>
@endsection