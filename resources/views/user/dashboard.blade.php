@extends('user.master',['menu'=>'dashboard'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
/* ---- Dashboard page styles ---- */
.welcome-card {
    background: linear-gradient(135deg, #1c2333 0%, #21262d 100%);
    border: 1px solid rgba(99,102,241,.25);
    border-radius: 14px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 22px;
}
.welcome-card .wc-info h5 {
    font-size: 17px;
    font-weight: 700;
    color: #e6edf3;
    margin: 0 0 4px;
}
.welcome-card .wc-info p {
    font-size: 12.5px;
    color: #7d8590;
    margin: 0;
}
.welcome-card .wc-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.welcome-card .wc-actions a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all .15s;
}
.wc-btn-primary { background: #6366f1; color: #fff !important; }
.wc-btn-primary:hover { background: #4f46e5; color: #fff !important; }
.wc-btn-outline { background: transparent; border: 1px solid rgba(99,102,241,.4); color: #a5b4fc !important; }
.wc-btn-outline:hover { background: rgba(99,102,241,.12); color: #a5b4fc !important; }

/* airdrop spotlight */
.airdrop-spotlight {
    background: linear-gradient(135deg, #1b2232 0%, #1a2136 100%);
    border: 1px solid rgba(59,130,246,.28);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 22px;
}
.airdrop-spotlight .as-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}
.airdrop-spotlight .as-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #dbe7ff;
    font-weight: 700;
    font-size: 14px;
}
.airdrop-spotlight .as-sub {
    color: #91a0bf;
    font-size: 12px;
    margin-top: 2px;
}
.airdrop-spotlight .as-toggle {
    border: 1px solid rgba(99,102,241,.45);
    background: rgba(99,102,241,.16);
    color: #c7d2fe;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 6px 11px;
    cursor: pointer;
}
.airdrop-spotlight .as-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.airdrop-spotlight .as-item {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 10px;
    padding: 10px 12px;
}
.airdrop-spotlight .as-item span {
    display: block;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #93a0bc;
    margin-bottom: 4px;
}
.airdrop-spotlight .as-item strong {
    display: block;
    font-size: 18px;
    color: #e6edf3;
    line-height: 1.2;
}
.airdrop-spotlight .as-msg {
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 12px;
    padding: 10px 12px;
    margin: 10px 0;
}
.airdrop-spotlight .as-msg-info {
    background: rgba(59,130,246,.10);
    border-color: rgba(59,130,246,.24);
    color: #bfdbfe;
}
.airdrop-spotlight .as-msg-success {
    background: rgba(34,197,94,.11);
    border-color: rgba(34,197,94,.26);
    color: #86efac;
}
.airdrop-spotlight .as-msg-warning {
    background: rgba(245,158,11,.12);
    border-color: rgba(245,158,11,.3);
    color: #fcd34d;
}
.airdrop-spotlight .as-msg-neutral {
    background: rgba(148,163,184,.11);
    border-color: rgba(148,163,184,.28);
    color: #cbd5e1;
}
.airdrop-spotlight .as-track {
    height: 7px;
    background: rgba(255,255,255,.08);
    border-radius: 99px;
    overflow: hidden;
    margin-top: 4px;
}
.airdrop-spotlight .as-track > div {
    height: 7px;
    border-radius: 99px;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    transition: width .35s ease;
}
.airdrop-spotlight .as-foot {
    margin-top: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    color: #93a0bc;
    font-size: 11px;
    flex-wrap: wrap;
}
.airdrop-spotlight .as-details {
    margin-top: 10px;
    border-top: 1px dashed rgba(255,255,255,.14);
    padding-top: 10px;
}
.airdrop-spotlight .as-details p {
    color: #9fb0d3;
    font-size: 12px;
    margin: 0 0 8px;
}
.airdrop-spotlight .as-open-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    padding: 7px 12px;
    border-radius: 8px;
    border: 1px solid rgba(59,130,246,.4);
    color: #bfdbfe;
    font-size: 12px;
    font-weight: 600;
}
.airdrop-spotlight .as-open-btn:hover {
    color: #dbeafe;
    text-decoration: none;
    background: rgba(59,130,246,.13);
}
@media(max-width:767px) {
    .airdrop-spotlight .as-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media(max-width:520px) {
    .airdrop-spotlight .as-grid {
        grid-template-columns: 1fr;
    }
}

/* stat cards */
.dash-stat-card {
    background: #161b22;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: transform .15s, box-shadow .15s;
}
.dash-stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.4); }
.dash-stat-card .dsc-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.dsc-icon-blue  { background: rgba(99,102,241,.18); color: #a5b4fc; }
.dsc-icon-green { background: rgba(34,197,94,.18);  color: #4ade80; }
.dsc-icon-amber { background: rgba(245,158,11,.18);  color: #fbbf24; }
.dash-stat-card .dsc-body { flex: 1; min-width: 0; }
.dash-stat-card .dsc-label {
    font-size: 11.5px; font-weight: 500; color: #7d8590;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px;
}
.dash-stat-card .dsc-value {
    font-size: 20px; font-weight: 700; color: #e6edf3; line-height: 1.2;
}
.dash-stat-card .dsc-sub {
    font-size: 11px; color: #7d8590; margin-top: 2px;
}

/* chart cards */
.chart-card {
    background: #161b22;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    padding: 18px 20px;
}
.chart-card .chart-card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 4px;
}
.chart-card .chart-card-header h5 {
    font-size: 14px; font-weight: 600; color: #e6edf3; margin: 0;
}
.chart-card .chart-subtitle {
    font-size: 11.5px; color: #7d8590; margin-bottom: 14px;
}

/* transaction table card */
.tx-card {
    background: #161b22;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    overflow: hidden;
}
.tx-card-header {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.tx-card-header h5 { font-size: 14px; font-weight: 600; color: #e6edf3; margin: 0; }
.tx-tabs .nav-link {
    font-size: 12.5px; font-weight: 500; color: #7d8590;
    padding: 5px 14px; border-radius: 6px; border: none;
    transition: all .15s;
}
.tx-tabs .nav-link.active, .tx-tabs .nav-link:hover {
    background: rgba(99,102,241,.15); color: #a5b4fc;
}
.tx-body { padding: 16px 20px; }

/* OBX Market widget */
.obx-market-widget {
    background: linear-gradient(135deg,#1a1f35 0%,#1c2333 100%);
    border: 1px solid rgba(99,102,241,.25);
    border-radius: 14px;
    padding: 16px 20px 14px;
    margin-bottom: 22px;
    position: relative;
    overflow: hidden;
}
.obx-market-widget::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg,#6366f1,#a855f7,#06b6d4);
}
.obx-market-widget .mw-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px; margin-bottom: 14px;
}
.obx-market-widget .mw-title {
    display: flex; align-items: center; gap: 10px;
}
.obx-market-widget .mw-title .mw-logo {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg,#6366f1,#a855f7);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 13px; color: #fff; flex-shrink: 0;
}
.obx-market-widget .mw-title h6 {
    font-size: 14px; font-weight: 700; color: #e6edf3; margin: 0 0 1px;
}
.obx-market-widget .mw-title small {
    font-size: 11px; color: #7d8590;
}
.mw-live-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.25);
    border-radius: 20px; padding: 3px 10px;
    font-size: 11px; font-weight: 600; color: #4ade80;
}
.mw-live-badge .pulse {
    width: 7px; height: 7px; border-radius: 50%; background: #4ade80;
    animation: pulse-dot 1.5s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.4; transform:scale(.75); }
}
.obx-market-widget .mw-stats {
    display: grid;
    grid-template-columns: repeat(5,1fr);
    gap: 10px;
}
.mw-stat {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 10px;
    padding: 12px 14px;
    min-width: 0;
}
.mw-stat .mws-label {
    font-size: 10.5px; font-weight: 600; color: #7d8590;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px;
}
.mw-stat .mws-value {
    font-size: 17px; font-weight: 700; color: #e6edf3;
    line-height: 1.2; word-break: break-word;
}
.mw-stat .mws-sub {
    font-size: 11px; color: #7d8590; margin-top: 3px;
}
.mw-stat.stat-price .mws-value { color: #a5b4fc; font-size: 20px; }
.chg-up { color: #4ade80 !important; }
.chg-dn { color: #f87171 !important; }
.chg-neutral { color: #7d8590 !important; }
@media(max-width:991px) { .obx-market-widget .mw-stats { grid-template-columns: repeat(3,1fr); } }
@media(max-width:575px) { .obx-market-widget .mw-stats { grid-template-columns: repeat(2,1fr); } }

/* multisig panel */
.msig-card {
    background: linear-gradient(135deg, #1a2136 0%, #1d2335 100%);
    border: 1px solid rgba(99,102,241,.22);
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 20px;
}
.msig-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.msig-title { margin:0; font-size:14px; font-weight:700; color:#e6edf3; }
.msig-sub { margin:4px 0 0; color:#8b93a0; font-size:12px; }
.msig-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
.msig-stat { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); border-radius:9px; padding:10px 12px; }
.msig-stat-label { font-size:10px; color:#8b93a0; text-transform:uppercase; letter-spacing:.5px; }
.msig-stat-val { font-size:18px; color:#e6edf3; font-weight:700; line-height:1.25; }
.msig-list { border-top:1px solid rgba(255,255,255,.08); padding-top:10px; }
.msig-item { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 0; border-bottom:1px dashed rgba(255,255,255,.06); }
.msig-item:last-child { border-bottom:none; }
.msig-item-main { min-width:0; }
.msig-item-main strong { color:#dbe2ff; font-size:12.5px; }
.msig-item-main p { margin:2px 0 0; color:#8b93a0; font-size:11.5px; }
.msig-btn {
    display:inline-flex; align-items:center; gap:6px;
    background:rgba(99,102,241,.16); border:1px solid rgba(99,102,241,.35);
    color:#b8c0ff; border-radius:7px; font-size:11.5px; font-weight:600;
    padding:6px 10px; text-decoration:none;
}
.msig-btn:hover { color:#d9ddff; text-decoration:none; background:rgba(99,102,241,.22); }
@media(max-width:767px){ .msig-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:520px){ .msig-grid { grid-template-columns:1fr; } }
</style>
@endsection
@php
if (!function_exists('fmtBigNum')) {
    function fmtBigNum(float $n): string {
        if ($n >= 1_000_000_000) return '$' . number_format($n / 1_000_000_000, 2) . 'B';
        if ($n >= 1_000_000)     return '$' . number_format($n / 1_000_000, 2)     . 'M';
        if ($n >= 1_000)         return '$' . number_format($n / 1_000, 1)         . 'K';
        return '$' . number_format($n, 2);
    }
}
@endphp
@php
    $profileUser = Auth::user();
    $countryCodeRaw = trim((string) ($profileUser->country_code ?: $profileUser->country));
    $countryCode = '';
    if (strlen($countryCodeRaw) === 2 && ctype_alpha($countryCodeRaw)) {
        $countryCode = strtoupper($countryCodeRaw);
    }

    $tz = config('app.timezone', 'UTC');
    if (!empty($countryCode)) {
        try {
            $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $countryCode);
            if (!empty($zones[0])) {
                $tz = $zones[0];
            }
        } catch (\Exception $e) {
            // Keep application timezone fallback.
        }
    }

    $localNow = now($tz);
    $hour = (int) $localNow->format('G');
    $timeGreeting = $hour < 12 ? __('Good morning') : __('Good evening');

    $profileName = trim((string) (($profileUser->first_name ?? '') . ' ' . ($profileUser->last_name ?? '')));
    if ($profileName === '') {
        $profileName = $profileUser->name ?? $profileUser->email ?? __('User');
    }
@endphp
@section('content')

{{-- Welcome card --}}
<div class="welcome-card">
    <div class="wc-info">
        <h5>{{ $timeGreeting }}, {{ $profileName }}. {{ __('Welcome back') }}, 👋</h5>
        <p>{{__('Here\'s an overview of your OBXCoin account activity.')}}</p>
    </div>
    <div class="wc-actions">
        <a href="{{route('buyCoin')}}" class="wc-btn-primary">
            <i class="fa fa-shopping-cart"></i> {{__('Buy OBXCoin')}}
        </a>
        <a href="{{ isset($obx_wallet) ? route('walletDetails', $obx_wallet->id).'?q=withdraw' : route('myPocket') }}" class="wc-btn-outline">
            <i class="fa fa-exchange"></i> {{__('Send OBXCoin')}}
        </a>
        <a href="{{route('myPocket')}}" class="wc-btn-outline">
            <i class="fa fa-credit-card"></i> {{__('XPocket')}}
        </a>
    </div>
</div>

@php
    $adProgress = $airdropProgress ?? [];
    $adCampaign = $adProgress['campaign'] ?? null;
    $adTodayStreak = (int) ($adProgress['todayStreak'] ?? 0);
    $adRemaining = (int) ($adProgress['remainingStreak'] ?? 0);
    $adProgressPct = (int) ($adProgress['progressPercent'] ?? 0);
    $adStreakDays = (int) ($adProgress['streakDays'] ?? 0);
    $adClaimedToday = (bool) ($adProgress['claimedToday'] ?? false);
    $adCongrats = (string) ($adProgress['congratsMessage'] ?? __('No active airdrop campaign right now. Stay tuned!'));
    $adCongratsToneRaw = (string) ($adProgress['congratsTone'] ?? 'info');
    $adCongratsTone = in_array($adCongratsToneRaw, ['success', 'warning', 'info', 'neutral'], true) ? $adCongratsToneRaw : 'info';
    $adBonusAmount = (string) ($adProgress['streakBonusAmount'] ?? '0');
@endphp

<div class="airdrop-spotlight">
    <div class="as-head">
        <div>
            <div class="as-title">
                <i class="fa fa-gift"></i>
                {{ __('Airdrop Streak Center') }}
            </div>
            <div class="as-sub">
                @if($adCampaign)
                    {{ $adCampaign->name }}
                @else
                    {{ __('No active campaign selected') }}
                @endif
            </div>
        </div>
        <button type="button" class="as-toggle" data-target="airdropSpotlightDetails" data-open-text="{{ __('Show Details') }}" data-close-text="{{ __('Hide Details') }}">
            {{ __('Show Details') }}
        </button>
    </div>

    <div class="as-grid">
        <div class="as-item">
            <span>{{ __('Today Streak') }}</span>
            <strong>{{ $adTodayStreak }}</strong>
        </div>
        <div class="as-item">
            <span>{{ __('Remaining Streak') }}</span>
            <strong>{{ $adRemaining }}</strong>
        </div>
        <div class="as-item">
            <span>{{ __('Claim Status') }}</span>
            <strong>{{ $adClaimedToday ? __('Claimed Today') : __('Not Claimed') }}</strong>
        </div>
    </div>

    <div class="as-msg as-msg-{{ $adCongratsTone }}">{{ $adCongrats }}</div>

    <div class="as-track"><div style="width:{{ $adProgressPct }}%"></div></div>
    <div class="as-foot">
        <span>{{ __('Milestone Cycle') }}: {{ $adStreakDays > 0 ? $adStreakDays : '--' }} {{ __('days') }}</span>
        <span>{{ __('Progress') }}: {{ $adProgressPct }}%</span>
    </div>

    <div class="as-details" id="airdropSpotlightDetails" hidden>
        @if($adCampaign)
            <p>{{ __('Bonus on milestone') }}: +{{ number_format((float) $adBonusAmount, 2) }} OBX</p>
            <p>{{ __('Campaign window') }}: {{ $adCampaign->start_date->format('M d, Y H:i') }} → {{ $adCampaign->end_date->format('M d, Y H:i') }}</p>
        @else
            <p>{{ __('Create or activate a campaign to start tracking streak progress here.') }}</p>
        @endif
        <a href="{{ route('user.airdrop') }}" class="as-open-btn">
            <i class="fa fa-rocket"></i> {{ __('Open Airdrop Page') }}
        </a>
    </div>
</div>

{{-- OBX Market Stats Widget --}}
<div class="obx-market-widget" id="obx-market-widget">
    <div class="mw-header">
        <div class="mw-title">
            <div class="mw-logo">OBX</div>
            <div>
                <h6>{{ settings('coin_name') ?? 'OBXCoin' }} <span style="font-size:12px;color:#7d8590;font-weight:400;">/ USD</span></h6>
                <small>{{__('Live market data · updates every 30s')}}</small>
            </div>
        </div>
        <div class="mw-live-badge">
            <span class="pulse"></span> LIVE
        </div>
    </div>
    <div class="mw-stats">
        <div class="mw-stat stat-price">
            <div class="mws-label">{{__('Price')}}</div>
            <div class="mws-value" id="mw_price">${{ number_format((float)settings('coin_price'), 6) }}</div>
            <div class="mws-sub">USD</div>
        </div>
        <div class="mw-stat">
            <div class="mws-label">{{__('24h Change')}}</div>
            <div class="mws-value" id="mw_change">—</div>
            <div class="mws-sub" id="mw_change_sub">vs yesterday</div>
        </div>
        <div class="mw-stat">
            <div class="mws-label">{{__('Market Cap')}}</div>
            <div class="mws-value" id="mw_mcap">{{ settings('obx_market_cap') ? fmtBigNum((float)settings('obx_market_cap')) : '—' }}</div>
            <div class="mws-sub">USD</div>
        </div>
        <div class="mw-stat">
            <div class="mws-label">{{__('24h Volume')}}</div>
            <div class="mws-value" id="mw_vol">{{ settings('obx_volume_24h') ? fmtBigNum((float)settings('obx_volume_24h')) : '—' }}</div>
            <div class="mws-sub">USD</div>
        </div>
        <div class="mw-stat">
            <div class="mws-label">{{__('Circulating Supply')}}</div>
            <div class="mws-value" id="mw_supply">{{ settings('obx_circulating_supply') ? fmtBigNum((float)settings('obx_circulating_supply')) : '—' }}</div>
            <div class="mws-sub">OBX</div>
        </div>
    </div>
</div>

{{-- Stat cards --}}
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-blue">
                <i class="fa fa-coins" style="font-size:18px;"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('Available OBXCoin')}}</div>
                <div class="dsc-value"><i class="fa fa-wallet" style="margin-right:8px;font-size:15px;opacity:.9;"></i>{{number_format($balance['available_coin'],2)}}</div>
                <div class="dsc-sub">{{__('USD')}}: {{number_format($balance['available_used'],2)}}</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-green">
                <i class="fa fa-arrow-up"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('Total Withdraws')}}</div>
                <div class="dsc-value">{{number_format($completed_withdraw,2)}}</div>
                <div class="dsc-sub">{{__('All-time completed withdrawals')}}</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-amber">
                <i class="fa fa-shopping-cart"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('Total Purchased')}}</div>
                <div class="dsc-value">{{number_format($total_buy_coin,2)}}</div>
                <div class="dsc-sub">{{__('All-time buy total')}}</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-12">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-blue">
                <i class="fa fa-usd"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('My Presale USDT Paid')}}</div>
                <div class="dsc-value">{{number_format((float) $total_presale_usdt_paid,2)}} USDT</div>
                <div class="dsc-sub">{{__('Only your successful presale payments')}}</div>
            </div>
        </div>
    </div>
</div>

@if($co_wallet_feature_active)
<div class="msig-card">
    <div class="msig-head">
        <div>
            <h5 class="msig-title"><i class="fa fa-shield" style="margin-right:6px;color:#a5b4fc;"></i>{{__('Team XPocket Security')}}</h5>
            <p class="msig-sub">{{__('Team XPocket requires multiple co-user approvals before a withdrawal is executed.')}}</p>
        </div>
        <a href="{{route('myPocket', ['tab' => 'co-pocket'])}}" class="msig-btn">
            <i class="fa fa-wallet"></i> {{__('Open Team XPocket')}}
        </a>
    </div>

    <div class="msig-grid">
        <div class="msig-stat">
            <div class="msig-stat-label">{{__('XPockets Joined')}}</div>
            <div class="msig-stat-val">{{$msig_wallet_count}}</div>
        </div>
        <div class="msig-stat">
            <div class="msig-stat-label">{{__('Pending Your Approval')}}</div>
            <div class="msig-stat-val">{{$msig_pending_approvals_count}}</div>
        </div>
        <div class="msig-stat">
            <div class="msig-stat-label">{{__('Approval Threshold')}}</div>
            <div class="msig-stat-val">{{$msig_approval_percentage}}%</div>
        </div>
    </div>

    <div class="msig-list">
        @forelse($msig_pending_approvals as $pending)
            <div class="msig-item">
                <div class="msig-item-main">
                    <strong>{{__('Request')}} #{{$pending->id}} · {{$pending->amount}} {{$pending->wallet->coin_type ?? __('Coin')}}</strong>
                    <p>{{__('XPocket')}}: {{$pending->wallet->name ?? __('N/A')}} · {{__('To')}}: {{$pending->address}}</p>
                </div>
                <a href="{{route('coWalletApprovals', $pending->id)}}" class="msig-btn">
                    <i class="fa fa-check-circle"></i> {{__('Review & Approve')}}
                </a>
            </div>
        @empty
            <div style="font-size:12px;color:#8b93a0;">{{__('No pending Team XPocket approvals for you right now.')}}</div>
        @endforelse
    </div>
</div>
@endif

{{-- Charts row --}}
<div class="row mb-4">
    <div class="col-xl-6 mb-4 mb-xl-0">
        <div class="chart-card h-100">
            <div class="chart-card-header">
                <h5><i class="fa fa-arrow-down" style="color:#4ade80;margin-right:6px;font-size:12px;"></i>{{__('Deposits')}}</h5>
                <span class="chart-subtitle" style="margin:0;">{{__('Current Year')}}</span>
            </div>
            <div class="chart-subtitle">{{__('Monthly deposit activity')}}</div>
            <canvas id="depositChart"></canvas>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="chart-card h-100">
            <div class="chart-card-header">
                <h5><i class="fa fa-arrow-up" style="color:#f87171;margin-right:6px;font-size:12px;"></i>{{__('Withdrawals')}}</h5>
                <span class="chart-subtitle" style="margin:0;">{{__('Current Year')}}</span>
            </div>
            <div class="chart-subtitle">{{__('Monthly withdrawal activity')}}</div>
            <canvas id="withdrawalChart"></canvas>
        </div>
    </div>
</div>

{{-- Buy Coin chart --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="chart-card-header">
                <h5><i class="fa fa-bar-chart" style="color:#a5b4fc;margin-right:6px;font-size:12px;"></i>{{__('OBXCoin Purchase Report')}}</h5>
                <span class="chart-subtitle" style="margin:0;">{{__('Current Year')}}</span>
            </div>
            <div class="chart-subtitle">{{__('Monthly buy coin volume')}}</div>
            <canvas id="myBarChart"></canvas>
        </div>
    </div>
</div>

{{-- Transaction history --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="tx-card">
            <div class="tx-card-header">
                <h5 id="list_title">{{__('Transaction History')}}</h5>
                <ul class="nav tx-tabs" id="myTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="deposit-tab" data-toggle="tab"
                           onclick="$('#list_title').html('Deposit History')"
                           href="#deposit" role="tab" aria-controls="deposit" aria-selected="true">
                            <i class="fa fa-arrow-down" style="font-size:10px;"></i> {{__('Deposits')}}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="withdraw-tab" data-toggle="tab"
                           onclick="$('#list_title').html('Withdrawal History')"
                           href="#withdraw" role="tab" aria-controls="withdraw" aria-selected="false">
                            <i class="fa fa-arrow-up" style="font-size:10px;"></i> {{__('Withdrawals')}}
                        </a>
                    </li>
                </ul>
            </div>
            <div class="tx-body">
                <div class="tab-content">
                    <div id="deposit" class="tab-pane fade in active show">
                        <div class="cp-user-transaction-history-table table-responsive">
                            <table class="table" id="table">
                                <thead>
                                    <tr>
                                        <th>{{__('Address')}}</th>
                                        <th>{{__('Amount')}}</th>
                                        <th>{{__('Transaction Hash')}}</th>
                                        <th>{{__('Status')}}</th>
                                        <th>{{__('Created At')}}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="withdraw" class="tab-pane fade in">
                        <div class="cp-user-transaction-history-table table-responsive">
                            <table class="table" id="withdraw_table">
                                <thead>
                                    <tr>
                                        <th>{{__('Address')}}</th>
                                        <th>{{__('Amount')}}</th>
                                        <th>{{__('Transaction Hash')}}</th>
                                        <th>{{__('Status')}}</th>
                                        <th>{{__('Created At')}}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

        @section('script')
            <script src="{{asset('assets/chart/chart.min.js')}}"></script>
            <script src="{{asset('assets/chart/anychart-base.min.js')}}"></script>
            <!-- Resources -->
            <script src="{{asset('assets/chart/amchart.core.js')}}"></script>
            <script src="{{asset('assets/chart/amchart.charts.js')}}"></script>
            <script src="{{asset('assets/chart/amchart.animated.js')}}"></script>
            <script>
                anychart.onDocumentReady(function () {
                    var chart = anychart.pie([
                        {x: "Complete", value: {!! $completed_withdraw !!}},
                        {x: "Pending", value: {!! $pending_withdraw !!}},
                    ]);
                    chart.innerRadius("60%");
                    var label = anychart.standalones.label();
                    label.text({!! json_encode($pending_withdraw) !!});
                    label.width("100%");
                    label.height("100%");
                    label.adjustFontSize(true);
                    label.fontColor("#7d8590");
                    label.hAlign("center");
                    label.vAlign("middle");
                    chart.center().content(label);
                    chart.container('circle');
                    chart.draw();
                });
            </script>
            <script>
                am4core.ready(function () {
                    am4core.useTheme(am4themes_animated);
                    var chart = am4core.create("container", am4charts.XYChart);
                    chart.data = {!! json_encode($sixmonth_diposites) !!};
                    var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
                    categoryAxis.dataFields.category = "country";
                    categoryAxis.renderer.grid.template.location = 0;
                    categoryAxis.renderer.minGridDistance = 30;
                    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
                    valueAxis.title.text = "Deposit and withdraw";
                    valueAxis.title.fontWeight = 800;
                    var series = chart.series.push(new am4charts.ColumnSeries());
                    series.dataFields.valueY = "year2004";
                    series.dataFields.categoryX = "country";
                    series.clustered = false;
                    series.tooltipText = "Deposit {categoryX}: [bold]{valueY}[/]";
                    var series2 = chart.series.push(new am4charts.ColumnSeries());
                    series2.dataFields.valueY = "year2005";
                    series2.dataFields.categoryX = "country";
                    series2.clustered = false;
                    series2.columns.template.width = am4core.percent(50);
                    series2.tooltipText = "Withdraw {categoryX}: [bold]{valueY}[/]";
                    chart.cursor = new am4charts.XYCursor();
                    chart.cursor.lineX.disabled = true;
                    chart.cursor.lineY.disabled = true;
                }); // end am4core.ready()
            </script>

            <script>
                $(document).ready(function () {
                    $('#withdraw_table').DataTable({
                        processing: true,
                        serverSide: true,
                        pageLength: 10,
                        bLengthChange: true,
                        responsive: false,
                        ajax: '{{ route('transactionHistories', [], false) }}?type=withdraw',
                        order: [4, 'desc'],
                        autoWidth: false,
                        language: {
                            paginate: {
                                next: 'Next &#8250;',
                                previous: '&#8249; Previous'
                            }
                        },
                        columns: [
                            {"data": "address", "orderable": false},
                            {"data": "amount", "orderable": false},
                            {"data": "hashKey", "orderable": false, "render": function(data) {
                                if (!data) return '&mdash;';
                                if (typeof data === 'string' && data.startsWith('0x')) {
                                    return '<a href="{{ explorer_tx_base() }}'+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,16)+'&#8230;</a>';
                                }
                                return data;
                            }},
                            {"data": "status", "orderable": false},
                            {"data": "created_at", "orderable": false}
                        ],
                    });
                });
            </script>

            <script>
                $(document).ready(function () {
                    $('#table').DataTable({
                        processing: true,
                        serverSide: true,
                        pageLength: 10,
                        retrieve: true,
                        bLengthChange: true,
                        responsive: false,
                        ajax: '{{ route('transactionHistories', [], false) }}?type=deposit',
                        order: [4, 'desc'],
                        autoWidth: false,
                        language: {
                            paginate: {
                                next: 'Next &#8250;',
                                previous: '&#8249; Previous'
                            }
                        },
                        columns: [
                            {"data": "address", "orderable": false},
                            {"data": "amount", "orderable": false},
                            {"data": "hashKey", "orderable": false, "render": function(data) {
                                if (!data) return '&mdash;';
                                if (typeof data === 'string' && data.startsWith('0x')) {
                                    return '<a href="{{ explorer_tx_base() }}'+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,16)+'&#8230;</a>';
                                }
                                return data;
                            }},
                            {"data": "status", "orderable": false},
                            {"data": "created_at", "orderable": false}
                        ],
                    });
                });
            </script>
            <script>
                var ctx = document.getElementById('depositChart').getContext("2d");
                var depositChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Monthly Deposit",
                            borderColor: "#4ade80",
                            pointBorderColor: "#4ade80",
                            pointBackgroundColor: "#4ade80",
                            pointHoverBackgroundColor: "#4ade80",
                            pointHoverBorderColor: "#21262d",
                            pointBorderWidth: 3,
                            pointHoverRadius: 4,
                            pointHoverBorderWidth: 2,
                            pointRadius: 3,
                            fill: true,
                            backgroundColor: "rgba(74,222,128,.07)",
                            borderWidth: 2.5,
                            data: {!! json_encode($monthly_deposit) !!}
                        }]
                    },
                    options: {
                        legend: { position: "bottom", display: true, labels: { fontColor: '#7d8590' } },
                        scales: {
                            yAxes: [{
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", beginAtZero: true, padding: 20 },
                                gridLines: { drawTicks: false, color: "rgba(255,255,255,.05)", zeroLineColor: "rgba(255,255,255,.1)" }
                            }],
                            xAxes: [{
                                gridLines: { display: false, drawTicks: false },
                                ticks: { padding: 20, fontColor: "#7d8590", fontStyle: "bold" }
                            }]
                        }
                    }
                });
            </script>
            <script>
                var ctx2 = document.getElementById('withdrawalChart').getContext("2d");
                var withdrawalChart = new Chart(ctx2, {
                    type: 'line',
                    data: {
                        labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Monthly Withdrawal",
                            borderColor: "#f87171",
                            pointBorderColor: "#f87171",
                            pointBackgroundColor: "#f87171",
                            pointHoverBackgroundColor: "#f87171",
                            pointHoverBorderColor: "#21262d",
                            pointBorderWidth: 3,
                            pointHoverRadius: 4,
                            pointHoverBorderWidth: 2,
                            pointRadius: 3,
                            fill: true,
                            backgroundColor: "rgba(248,113,113,.07)",
                            borderWidth: 2.5,
                            data: {!! json_encode($monthly_withdrawal) !!}
                        }]
                    },
                    options: {
                        legend: { position: "bottom", display: true, labels: { fontColor: '#7d8590' } },
                        scales: {
                            yAxes: [{
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", beginAtZero: true },
                                gridLines: { drawTicks: false, color: "rgba(255,255,255,.05)", zeroLineColor: "rgba(255,255,255,.1)" }
                            }],
                            xAxes: [{
                                gridLines: { display: false, drawTicks: false },
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", autoSkip: false }
                            }]
                        }
                    }
                });
            </script>
            <script>
                var ctx3 = document.getElementById('myBarChart').getContext("2d");
                var myBarChart = new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Monthly Buy Coin",
                            backgroundColor: "rgba(99,102,241,.75)",
                            borderColor: "#6366f1",
                            borderWidth: 1,
                            borderRadius: 5,
                            data: {!! json_encode($monthly_buy_coin) !!}
                        }]
                    },
                    options: {
                        legend: { position: "bottom", display: true, labels: { fontColor: '#7d8590' } },
                        scales: {
                            yAxes: [{
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", beginAtZero: true, maxTicksLimit: 8, padding: 20 },
                                gridLines: { drawTicks: false, color: "rgba(255,255,255,.05)", zeroLineColor: "rgba(255,255,255,.1)" }
                            }],
                            xAxes: [{
                                gridLines: { color: "rgba(99,102,241,.1)", zeroLineColor: "#6366f1" },
                                ticks: { padding: 10, fontColor: "#7d8590", fontStyle: "bold" }
                            }]
                        }
                    }
                });
            </script>

            {{-- OBX live market data refresh --}}
            <script>
            (function(){
                var streakToggle = document.querySelector('.as-toggle');
                if (streakToggle) {
                    streakToggle.addEventListener('click', function() {
                        var detailsId = streakToggle.getAttribute('data-target');
                        var details = document.getElementById(detailsId);
                        if (!details) {
                            return;
                        }

                        var isHidden = details.hasAttribute('hidden');
                        if (isHidden) {
                            details.removeAttribute('hidden');
                            streakToggle.textContent = streakToggle.getAttribute('data-close-text') || 'Hide Details';
                        } else {
                            details.setAttribute('hidden', 'hidden');
                            streakToggle.textContent = streakToggle.getAttribute('data-open-text') || 'Show Details';
                        }
                    });
                }

                var OBX_PRICE_API = '{{ url("/api/obx-price") }}';

                function fmtNum(n) {
                    n = parseFloat(n) || 0;
                    if (n >= 1e9) return '$' + (n/1e9).toFixed(2) + 'B';
                    if (n >= 1e6) return '$' + (n/1e6).toFixed(2) + 'M';
                    if (n >= 1e3) return '$' + (n/1e3).toFixed(1) + 'K';
                    return '$' + n.toFixed(2);
                }

                function fmtSupply(n) {
                    n = parseFloat(n) || 0;
                    if (n >= 1e9) return (n/1e9).toFixed(2) + 'B';
                    if (n >= 1e6) return (n/1e6).toFixed(2) + 'M';
                    if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
                    return n.toFixed(0);
                }

                function refreshMarket() {
                    fetch(OBX_PRICE_API)
                        .then(function(r){ return r.json(); })
                        .then(function(d) {
                            if (!d || !d.price) return;

                            var price = parseFloat(d.price);
                            var chg   = parseFloat(d.change_24h || 0);

                            // Price
                            var priceEl = document.getElementById('mw_price');
                            if (priceEl) priceEl.textContent = '$' + price.toFixed(6);

                            // 24h change
                            var chgEl = document.getElementById('mw_change');
                            if (chgEl) {
                                var sign  = chg >= 0 ? '+' : '';
                                var cls   = chg > 0 ? 'chg-up' : chg < 0 ? 'chg-dn' : 'chg-neutral';
                                chgEl.className = 'mws-value ' + cls;
                                chgEl.textContent = sign + chg.toFixed(2) + '%';
                            }

                            // Market cap
                            var mcapEl = document.getElementById('mw_mcap');
                            if (mcapEl && d.market_cap) mcapEl.textContent = fmtNum(d.market_cap);

                            // Volume
                            var volEl = document.getElementById('mw_vol');
                            if (volEl && d.volume_24h) volEl.textContent = fmtNum(d.volume_24h);

                            // Supply
                            var supEl = document.getElementById('mw_supply');
                            if (supEl && d.circulating_supply) supEl.textContent = fmtSupply(d.circulating_supply);
                        })
                        .catch(function(){});
                }

                refreshMarket();
                setInterval(refreshMarket, 30000);
            })();
            </script>
@endsection
