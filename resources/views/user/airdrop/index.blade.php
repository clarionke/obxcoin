@extends('user.master',['menu'=>'airdrop'])
@section('title', isset($title) ? $title : __('My Airdrop'))
@section('style')
<style>
:root{--accent:#6366f1;--dark3:#1c2333;--dark4:#21262d;--border:rgba(255,255,255,.08);--text:#e6edf3;--muted:#7d8590;--success:#3fb950;--warning:#d29922;--danger:#f85149;--r:12px;}
.airdrop-hero{background:linear-gradient(135deg,#1c2333 0%,#1b1f2a 100%);border:1px solid rgba(99,102,241,.25);border-radius:var(--r);padding:28px 28px 22px;margin-bottom:22px;}
.airdrop-hero h2{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px;}
.airdrop-hero p{color:var(--muted);font-size:13px;margin-bottom:0;}
.obx-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);border-radius:8px;padding:6px 14px;font-size:13px;font-weight:600;color:#a5b4fc;}
.stat-card{background:var(--dark3);border:1px solid var(--border);border-radius:var(--r);padding:20px 22px;height:100%;}
.stat-card .sc-label{font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px;}
.stat-card .sc-value{font-size:22px;font-weight:700;color:var(--text);}
.stat-card .sc-sub{font-size:11px;color:var(--muted);margin-top:3px;}
.claim-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 32px;border-radius:10px;font-size:14px;font-weight:700;background:var(--accent);color:#fff;border:none;cursor:pointer;transition:all .15s;width:100%;margin-top:12px;}
.claim-btn:hover:not(:disabled){background:#4f46e5;}
.claim-btn:disabled{opacity:.45;cursor:not-allowed;}
.unlock-panel{background:var(--dark4);border:1px solid rgba(255,193,7,.25);border-radius:var(--r);padding:20px 22px;margin-top:18px;}
.unlock-panel h6{font-size:14px;font-weight:700;color:#fbbf24;margin-bottom:8px;}
.unlock-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 26px;border-radius:9px;font-size:13.5px;font-weight:700;background:#d97706;color:#fff;border:none;cursor:pointer;transition:all .15s;width:100%;justify-content:center;margin-top:10px;}
.unlock-btn:hover{background:#b45309;}
.flag-hidden{display:inline-flex;align-items:center;gap:6px;background:rgba(209,97,29,.1);border:1px solid rgba(209,97,29,.3);border-radius:7px;padding:6px 14px;font-size:12px;color:#fbbf24;}
.progress-bar-wrap{background:rgba(255,255,255,.06);border-radius:99px;height:6px;margin-top:8px;}
.progress-bar-fill{height:6px;border-radius:99px;background:var(--accent);transition:width .4s;}
.empty-state{text-align:center;padding:48px 24px;color:var(--muted);}
.empty-state i{font-size:40px;margin-bottom:14px;display:block;color:rgba(99,102,241,.3);}
</style>
@endsection

@section('content')
<div class="cp-user-content-area" id="main">
    {{-- Flash messages --}}
    @if(session('success'))
        <div style="background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);border-radius:8px;padding:12px 16px;margin-bottom:18px;color:#3fb950;font-size:13px;">
            <i class="fa fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('dismiss'))
        <div style="background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);border-radius:8px;padding:12px 16px;margin-bottom:18px;color:#f85149;font-size:13px;">
            <i class="fa fa-exclamation-circle"></i> {{ session('dismiss') }}
        </div>
    @endif
    @if(session('info'))
        <div style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);border-radius:8px;padding:12px 16px;margin-bottom:18px;color:#a5b4fc;font-size:13px;">
            <i class="fa fa-info-circle"></i> {{ session('info') }}
        </div>
    @endif

    @if($campaign)
    {{-- ── Active / Upcoming Campaign ─────────────────────────────────────── --}}
    <div class="airdrop-hero">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h2>{{ $campaign->name }}</h2>
                <p>{{ $campaign->start_date->format('M d, Y H:i') }} → {{ $campaign->end_date->format('M d, Y H:i') }}</p>
            </div>
            <span class="obx-badge">
                <i class="fa fa-gift"></i>
                {{ number_format((float)$campaign->daily_claim_amount, 2) }} OBX / day
            </span>
        </div>

        {{-- Campaign duration progress bar --}}
        @php
            $totalSecs  = $campaign->start_date->diffInSeconds($campaign->end_date);
            $elapsed    = $campaign->hasStarted() ? now()->diffInSeconds($campaign->start_date) : 0;
            $pct        = $totalSecs > 0 ? min(100, round($elapsed / $totalSecs * 100)) : 0;
        @endphp
        <div style="margin-top:12px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:4px;">
                <span>{{ __('Campaign Progress') }}</span>
                <span>{{ $pct }}%</span>
            </div>
            <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:{{ $pct }}%"></div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Total Locked OBX --}}
        <div class="col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="sc-label">{{ __('Your Locked OBX') }}</div>
                <div class="sc-value">{{ number_format((float)$totalLockedObx, 4) }}</div>
                <div class="sc-sub">{{ __('Accumulated from daily claims (locked until campaign ends)') }}</div>
            </div>
        </div>

        {{-- Campaign status --}}
        <div class="col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="sc-label">{{ __('Campaign Status') }}</div>
                <div class="sc-value" style="font-size:16px;margin-top:4px;">
                    @if($campaign->isLive())
                        <span style="color:var(--success);">● {{ __('Live') }}</span>
                    @elseif(!$campaign->hasStarted())
                        <span style="color:#fbbf24;">● {{ __('Starting Soon') }}</span>
                    @elseif($campaign->hasEnded())
                        <span style="color:var(--muted);">● {{ __('Ended') }}</span>
                    @endif
                </div>
                <div class="sc-sub">
                    @if($campaign->isLive())
                        {{ __('Ends') }} {{ $campaign->end_date->diffForHumans() }}
                    @elseif(!$campaign->hasStarted())
                        {{ __('Starts') }} {{ $campaign->start_date->diffForHumans() }}
                    @endif
                </div>
            </div>
        </div>

        {{-- Unlock fee status --}}
        <div class="col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="sc-label">{{ __('Unlock Fee') }}</div>
                @if($campaign->fee_revealed)
                    <div class="sc-value" style="color:#fbbf24;">{{ number_format($campaign->unlock_fee_usdt, 2) }} USDT</div>
                    <div class="sc-sub">{{ __('Pay this fee to unlock your OBX to your wallet') }}</div>
                @else
                    <div class="sc-value" style="font-size:15px;margin-top:6px;">
                        <span class="flag-hidden"><i class="fa fa-lock"></i> {{ __('Hidden Until Campaign Ends') }}</span>
                    </div>
                    <div class="sc-sub">{{ __('Fee will be revealed after the campaign closes') }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Daily Claim Button ───────────────────────────────────────────────── --}}
    @if($campaign->isLive())
        @if($unlockRecord && $unlockRecord->status === 'confirmed')
            <div style="background:rgba(63,185,80,.08);border:1px solid rgba(63,185,80,.2);border-radius:10px;padding:16px 20px;color:#3fb950;font-size:13.5px;margin-bottom:18px;">
                <i class="fa fa-check-circle"></i> {{ __('You have already unlocked your airdrop for this campaign. Your OBX has been sent to your wallet.') }}
            </div>
        @elseif($claimedToday)
            <div style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:16px 20px;color:#a5b4fc;font-size:13.5px;margin-bottom:18px;">
                <i class="fa fa-check"></i>
                {{ __("You've claimed today's airdrop!") }}
                {{ __('Come back tomorrow for your next claim.') }}
            </div>
        @else
            <form action="{{ route('user.airdrop.claim') }}" method="POST">
                @csrf
                <button type="submit" class="claim-btn">
                    <i class="fa fa-gift"></i>
                    {{ __('Claim') }} {{ number_format((float)$campaign->daily_claim_amount, 2) }} {{ __('OBX Today') }}
                </button>
            </form>
            <div style="font-size:11.5px;color:var(--muted);text-align:center;margin-top:6px;">
                {{ __('Tokens are locked until the campaign ends. Unlock by paying the USDT fee after the campaign.') }}
            </div>
        @endif
    @endif

    {{-- ── Unlock Panel ─────────────────────────────────────────────────────── --}}
    @if($campaign->canUnlock() && bccomp($totalLockedObx, '0', 18) > 0)
        @if($unlockRecord && $unlockRecord->status === 'confirmed')
            <div class="unlock-panel" style="border-color:rgba(63,185,80,.25);">
                <h6 style="color:#3fb950;"><i class="fa fa-unlock"></i> {{ __('Airdrop Unlocked') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                    {{ __(':obx OBX has been transferred to your wallet.', ['obx' => number_format((float)$unlockRecord->obx_released, 4)]) }}
                    @if($unlockRecord->tx_hash)
                        &nbsp;<code style="font-size:11px;">TX: {{ substr($unlockRecord->tx_hash, 0, 20) }}…</code>
                    @endif
                </p>
            </div>
        @elseif($unlockRecord && $unlockRecord->status === 'pending')
            <div class="unlock-panel">
                <h6><i class="fa fa-clock-o"></i> {{ __('Unlock Pending') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                    {{ __('Your unlock request is pending. Please complete the on-chain payment of') }}
                    <b style="color:#fbbf24;">{{ number_format($campaign->unlock_fee_usdt, 2) }} USDT</b>
                    {{ __('to the OBXAirdrop contract to receive your') }}
                    <b>{{ number_format((float)$unlockRecord->obx_released, 4) }} OBX</b>.
                </p>
                @if($campaign->contract_address)
                    <div style="margin-top:10px;background:rgba(0,0,0,.2);border-radius:7px;padding:10px 14px;font-size:12px;color:#a5b4fc;word-break:break-all;">
                        <b>{{ __('Contract') }}:</b> {{ $campaign->contract_address }}
                        @if($campaign->chain_id)
                            &nbsp;(Chain {{ $campaign->chain_id }})
                        @endif
                    </div>
                @endif
            </div>
        @else
            {{-- Ready to unlock --}}
            <div class="unlock-panel">
                <h6><i class="fa fa-unlock-alt"></i> {{ __('Campaign Ended — Unlock Your OBX') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:4px;">
                    {{ __('You have') }} <b style="color:var(--text);">{{ number_format((float)$totalLockedObx, 4) }} OBX</b>
                    {{ __('locked. Pay') }} <b style="color:#fbbf24;">{{ number_format($campaign->unlock_fee_usdt, 2) }} USDT</b>
                    {{ __('to unlock and transfer them to your wallet.') }}
                </p>
                <form action="{{ route('user.airdrop.unlock') }}" method="POST">
                    @csrf
                    <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
                    <button type="submit" class="unlock-btn"
                            onclick="return confirm('{{ __('Pay :fee USDT to unlock :obx OBX?', ['fee' => number_format($campaign->unlock_fee_usdt, 2), 'obx' => number_format((float)$totalLockedObx, 4)]) }}')">
                        <i class="fa fa-unlock"></i>
                        {{ __('Unlock :obx OBX for :fee USDT', ['obx' => number_format((float)$totalLockedObx, 4), 'fee' => number_format($campaign->unlock_fee_usdt, 2)]) }}
                    </button>
                </form>
            </div>
        @endif
    @elseif($campaign->hasEnded() && !$campaign->fee_revealed && bccomp($totalLockedObx, '0', 18) > 0)
        <div class="unlock-panel">
            <h6><i class="fa fa-lock"></i> {{ __('Campaign Ended — Fee Not Yet Revealed') }}</h6>
            <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                {{ __('You have') }} <b style="color:var(--text);">{{ number_format((float)$totalLockedObx, 4) }} OBX</b>
                {{ __('locked. The admin will reveal the unlock fee shortly. Check back soon.') }}
            </p>
        </div>
    @endif

    @else
    {{-- ── No Active Campaign ───────────────────────────────────────────────── --}}
    <div class="empty-state">
        <i class="fa fa-gift"></i>
        <p style="font-size:15px;color:var(--text);margin-bottom:6px;">{{ __('No Active Airdrop') }}</p>
        <p style="font-size:13px;">{{ __('There is no active airdrop campaign at the moment. Check back soon!') }}</p>
    </div>
    @endif

    {{-- ── Past ended campaigns with a balance to unlock ─────────────────── --}}
    @if($pastCampaigns->count() > 0)
    <div style="margin-top:28px;">
        <h6 style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:14px;">
            {{ __('Previous Campaigns with Locked Balance') }}
        </h6>
        @foreach($pastCampaigns as $pc)
            @php
                $pcBalance = \App\Model\AirdropClaim::where('user_id', auth()->id())
                    ->where('campaign_id', $pc->id)->sum('amount_obx') ?: '0';
                $pcUnlock  = \App\Model\AirdropUnlock::where('user_id', auth()->id())
                    ->where('campaign_id', $pc->id)->first();
            @endphp
            <div class="stat-card mb-3">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div>
                        <div style="font-weight:700;color:var(--text);margin-bottom:2px;">{{ $pc->name }}</div>
                        <div style="font-size:12px;color:var(--muted);">
                            {{ $pc->end_date->format('Y-m-d') }}
                            &nbsp;·&nbsp; {{ number_format((float)$pcBalance, 4) }} OBX locked
                        </div>
                    </div>
                    <div>
                        @if($pcUnlock && $pcUnlock->status === 'confirmed')
                            <span style="color:var(--success);font-size:12.5px;"><i class="fa fa-check-circle"></i> {{ __('Unlocked') }}</span>
                        @elseif($pc->canUnlock())
                            @if($pcUnlock)
                                <span style="color:#fbbf24;font-size:12.5px;"><i class="fa fa-clock-o"></i> {{ __('Pending') }}</span>
                            @else
                                <form action="{{ route('user.airdrop.unlock') }}" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="campaign_id" value="{{ $pc->id }}">
                                    <button class="unlock-btn" style="padding:8px 18px;font-size:12.5px;width:auto;margin:0;">
                                        <i class="fa fa-unlock"></i> {{ __('Unlock for :fee USDT', ['fee' => number_format($pc->unlock_fee_usdt, 2)]) }}
                                    </button>
                                </form>
                            @endif
                        @else
                            <span class="flag-hidden"><i class="fa fa-lock"></i> {{ __('Fee not revealed yet') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
