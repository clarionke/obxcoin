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
.airdrop-quick-panel{background:linear-gradient(135deg,#192338 0%,#1a2030 100%);border:1px solid rgba(99,102,241,.28);border-radius:var(--r);padding:16px 18px;margin-bottom:16px;}
.aq-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
.aq-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--text);}
.aq-sub{font-size:12px;color:#94a3b8;margin-top:2px;}
.aq-toggle{border:1px solid rgba(99,102,241,.45);background:rgba(99,102,241,.16);color:#c7d2fe;border-radius:7px;padding:6px 10px;font-size:11.5px;font-weight:600;cursor:pointer;}
.aq-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;}
.aq-item{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:9px;padding:9px 11px;}
.aq-item span{display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:#95a4c0;margin-bottom:3px;}
.aq-item strong{display:block;font-size:18px;line-height:1.2;color:var(--text);}
.aq-msg{margin-top:10px;border:1px solid transparent;border-radius:9px;padding:9px 11px;font-size:12px;}
.aq-msg-info{background:rgba(59,130,246,.12);border-color:rgba(59,130,246,.28);color:#bfdbfe;}
.aq-msg-success{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.3);color:#86efac;}
.aq-msg-warning{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.32);color:#fcd34d;}
.aq-msg-neutral{background:rgba(148,163,184,.12);border-color:rgba(148,163,184,.3);color:#cbd5e1;}
.aq-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;font-size:11px;color:#95a4c0;margin-top:5px;}
.aq-details{margin-top:10px;border-top:1px dashed rgba(255,255,255,.12);padding-top:10px;}
.aq-details p{font-size:12px;color:#a5b4cf;margin:0 0 8px;}
.aq-open-link{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(59,130,246,.4);background:rgba(59,130,246,.12);border-radius:8px;padding:6px 11px;color:#bfdbfe;font-size:12px;font-weight:600;text-decoration:none;}
.aq-open-link:hover{color:#dbeafe;text-decoration:none;background:rgba(59,130,246,.18);}
.tier-guide-card{background:linear-gradient(160deg,#152238 0%,#1a2030 100%);border:1px solid rgba(56,189,248,.28);border-radius:var(--r);padding:18px 20px;margin-bottom:18px;}
.tier-guide-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.tier-guide-head h6{font-size:14.5px;font-weight:700;color:var(--text);margin:0 0 4px;}
.tier-guide-head p{font-size:12px;color:#9fb0c8;margin:0;}
.tier-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid rgba(125,211,252,.32);background:rgba(125,211,252,.12);color:#bae6fd;font-size:11.5px;font-weight:700;}
.tier-next-msg{border:1px solid rgba(56,189,248,.28);background:rgba(56,189,248,.09);border-radius:10px;padding:10px 12px;font-size:12.2px;color:#d7f5ff;}
.tier-next-msg strong{color:#fff;}
.tier-table-wrap{margin-top:12px;overflow-x:auto;}
.tier-table{width:100%;border-collapse:separate;border-spacing:0;font-size:12px;color:#cbd5e1;min-width:760px;}
.tier-table th,.tier-table td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;white-space:nowrap;}
.tier-table th{font-size:10.8px;letter-spacing:.05em;text-transform:uppercase;color:#93a4be;background:rgba(255,255,255,.03);}
.tier-table tr.is-current td{background:rgba(14,116,144,.2);color:#ecfeff;}
.tier-table tr.is-next td{background:rgba(217,119,6,.13);}
.tier-status{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:4px 9px;font-size:10.8px;font-weight:700;}
.tier-status-current{background:rgba(16,185,129,.18);color:#a7f3d0;border:1px solid rgba(16,185,129,.35);}
.tier-status-next{background:rgba(245,158,11,.16);color:#fcd34d;border:1px solid rgba(245,158,11,.3);}
.tier-status-open{background:rgba(59,130,246,.16);color:#bfdbfe;border:1px solid rgba(59,130,246,.3);}
.tier-status-locked{background:rgba(148,163,184,.16);color:#cbd5e1;border:1px solid rgba(148,163,184,.3);}
@media(max-width:767px){.aq-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:520px){.aq-grid{grid-template-columns:1fr;}}
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
    @if(!empty($claimLevelNotice))
        <div style="background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);border-radius:8px;padding:12px 16px;margin-bottom:18px;color:#bfdbfe;font-size:13px;">
            <i class="fa fa-bell"></i> {{ $claimLevelNotice }}
        </div>
    @endif

    @php
        $adProgress = $airdropProgress ?? [];
        $adCampaign = $adProgress['campaign'] ?? null;
        $adTodayStreak = (int) ($adProgress['todayStreak'] ?? 0);
        $adRemaining = (int) ($adProgress['remainingStreak'] ?? 0);
        $adProgressPct = (int) ($adProgress['progressPercent'] ?? 0);
        $adStreakDays = (int) ($adProgress['streakDays'] ?? 0);
        $adClaimedToday = (bool) ($adProgress['claimedToday'] ?? false);
        $adDailyClaimAmount = (string) ($adProgress['dailyClaimAmount'] ?? ($campaign->daily_claim_amount ?? '0'));
        $adClaimTierLabel = (string) ($adProgress['claimTierLabel'] ?? '--');
        $adClaimTierName = (string) ($adProgress['claimTierName'] ?? ($userClaimTierName ?? '--'));
        $adClaimTierRequirement = (string) ($adProgress['claimTierRequirement'] ?? ($userClaimTierRequirement ?? '--'));
        $adClaimTierRows = (array) ($adProgress['claimTierRows'] ?? ($claimTierRows ?? []));
        $adHasNextTier = (bool) ($adProgress['hasNextTier'] ?? ($hasNextTier ?? false));
        $adNextTierName = (string) ($adProgress['nextTierName'] ?? ($nextTierName ?? ''));
        $adNextTierRequirement = (string) ($adProgress['nextTierRequirement'] ?? ($nextTierRequirement ?? ''));
        $adNextTierMinUsd = isset($adProgress['nextTierMinUsd']) ? (float) $adProgress['nextTierMinUsd'] : (float) ($nextTierMinUsd ?? 0);
        $adAmountToNextUsd = isset($adProgress['amountToNextUsd']) ? (float) $adProgress['amountToNextUsd'] : (float) ($amountToNextUsd ?? 0);
        $adCongrats = (string) ($adProgress['congratsMessage'] ?? __('No active airdrop campaign right now. Stay tuned!'));
        $adCongratsToneRaw = (string) ($adProgress['congratsTone'] ?? 'info');
        $adCongratsTone = in_array($adCongratsToneRaw, ['success', 'warning', 'info', 'neutral'], true) ? $adCongratsToneRaw : 'info';
        $adBonusAmount = (string) ($adProgress['streakBonusAmount'] ?? '0');
        $userPurchaseUsd = (float) ($userTotalPurchasedUsd ?? 0);
        $userTierFee = (float) ($userWithdrawFeeUsdt ?? 0);
        $userTierLabel = (string) ($userWithdrawFeeTierLabel ?? '--');
    @endphp

    <div class="airdrop-quick-panel">
        <div class="aq-head">
            <div>
                <div class="aq-title"><i class="fa fa-bolt"></i> {{ __('Your Airdrop Streak Status') }}</div>
                <div class="aq-sub">{{ $adCampaign ? $adCampaign->name : __('No active campaign selected') }}</div>
            </div>
            <button type="button" class="aq-toggle" data-target="airdropQuickDetails" data-open-text="{{ __('Show Details') }}" data-close-text="{{ __('Hide Details') }}">{{ __('Show Details') }}</button>
        </div>

        <div class="aq-grid">
            <div class="aq-item">
                <span>{{ __('Today Streak') }}</span>
                <strong>{{ $adTodayStreak }}</strong>
            </div>
            <div class="aq-item">
                <span>{{ __('Remaining Streak') }}</span>
                <strong>{{ $adRemaining }}</strong>
            </div>
            <div class="aq-item">
                <span>{{ __('Claim Status') }}</span>
                <strong>{{ $adClaimedToday ? __('Claimed Today') : __('Not Claimed') }}</strong>
            </div>
        </div>

        <div class="aq-msg aq-msg-{{ $adCongratsTone }}">{{ $adCongrats }}</div>
        <div class="progress-bar-wrap" style="margin-top:8px;"><div class="progress-bar-fill" style="width:{{ $adProgressPct }}%;background:linear-gradient(90deg,#3b82f6,var(--accent));"></div></div>
        <div class="aq-foot">
            <span>{{ __('Milestone Cycle') }}: {{ $adStreakDays > 0 ? $adStreakDays : '--' }} {{ __('days') }}</span>
            <span>{{ __('Progress') }}: {{ $adProgressPct }}%</span>
        </div>

        <div id="airdropQuickDetails" class="aq-details" hidden>
            @if($adCampaign)
                <p>{{ __('Bonus on milestone') }}: +{{ number_format((float) $adBonusAmount, 2) }} OBX</p>
                <p>{{ __('Campaign window') }}: {{ $adCampaign->start_date->format('M d, Y H:i') }} → {{ $adCampaign->end_date->format('M d, Y H:i') }}</p>
                <p>{{ __('Your claim tier') }}: {{ $adClaimTierName }} ({{ $adClaimTierRequirement }}) {{ __('| Daily claim') }}: {{ number_format((float)$adDailyClaimAmount, 2) }} OBX {{ __('| Streak target') }}: {{ $adStreakDays }} {{ __('days') }}</p>
                <p>{{ __('Your fee tier') }}: {{ $userTierLabel }} {{ __('| Total buy') }}: ${{ number_format($userPurchaseUsd, 2) }} {{ __('| Fee') }}: {{ number_format($userTierFee, 2) }} USDT</p>
            @else
                <p>{{ __('Airdrop status updates will appear here when a campaign is active.') }}</p>
            @endif
            <a href="#main" class="aq-open-link"><i class="fa fa-arrow-down"></i> {{ __('View Full Airdrop Details') }}</a>
        </div>
    </div>

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
                {{ number_format((float)$adDailyClaimAmount, 2) }} OBX / day
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

    @php
        $summaryColClass = $airdropWithdrawEnabled ? 'col-md-4 col-sm-6' : 'col-md-6 col-sm-6';
    @endphp
    <div class="row g-3 mb-3">
        {{-- Total Locked OBX --}}
        <div class="{{ $summaryColClass }}">
            <div class="stat-card">
                <div class="sc-label">{{ __('Your Locked OBX') }}</div>
                <div class="sc-value">{{ number_format((float)$totalLockedObx, 4) }}</div>
                <div class="sc-sub">{{ __('Accumulated from daily claims (locked until campaign ends)') }}</div>
            </div>
        </div>

        {{-- Campaign status --}}
        <div class="{{ $summaryColClass }}">
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

        {{-- Withdrawal access (visible only when global withdraw is active) --}}
        @if($airdropWithdrawEnabled)
        <div class="col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="sc-label">{{ __('Withdrawal Access') }}</div>
                <div class="sc-value" style="font-size:16px;margin-top:4px;color:var(--success);">
                    ● {{ __('Enabled') }}
                </div>
                @if($campaign->fee_revealed && $userTierFee > 0)
                    <div class="sc-sub">
                        {{ __('Fee: :fee USDT via :currency', [
                            'fee' => number_format($userTierFee, 2),
                            'currency' => strtoupper($airdropWithdrawPayCurrency)
                        ]) }}
                    </div>
                    <div class="sc-sub">{{ __('Tier :tier based on total buy $:amount', ['tier' => $userTierLabel, 'amount' => number_format($userPurchaseUsd, 2)]) }}</div>
                @elseif($campaign->fee_revealed)
                    <div class="sc-sub">{{ __('Campaign withdrawal fee is not configured yet') }}</div>
                @endif
            </div>
        </div>
        @endif

    </div>

    {{-- ── Claim Tier Guide ────────────────────────────────────────────────── --}}
    <div class="tier-guide-card">
        <div class="tier-guide-head">
            <div>
                <h6>{{ __('Claim Tier Guide') }}</h6>
                <p>{{ __('Total Paid to Buy OBX') }}: <strong style="color:#fff;">${{ number_format($userPurchaseUsd, 2) }}</strong></p>
            </div>
            <span class="tier-chip"><i class="fa fa-star"></i> {{ __('Current Tier') }}: {{ $adClaimTierName }}</span>
        </div>

        @if($adHasNextTier)
            <div class="tier-next-msg">
                @if($adAmountToNextUsd > 0)
                    {!! __('How to move to next tier: buy <strong>:amount USD</strong> more to unlock <strong>:tier</strong> (requires total buy of <strong>:target USD</strong>).', [
                        'amount' => number_format($adAmountToNextUsd, 2),
                        'tier' => e($adNextTierName),
                        'target' => number_format($adNextTierMinUsd, 2),
                    ]) !!}
                @else
                    {!! __('You have reached the requirement for <strong>:tier</strong>. Make a claim to refresh your current level view.', [
                        'tier' => e($adNextTierName),
                    ]) !!}
                @endif
            </div>
        @else
            <div class="tier-next-msg">
                {!! __('You are already on the highest claim tier: <strong>:tier</strong>. Keep claiming daily to maximize rewards.', [
                    'tier' => e($adClaimTierName),
                ]) !!}
            </div>
        @endif

        <div class="tier-table-wrap">
            <table class="tier-table">
                <thead>
                    <tr>
                        <th>{{ __('Tier Name') }}</th>
                        <th>{{ __('Requirement') }}</th>
                        <th>{{ __('Daily Claim') }}</th>
                        <th>{{ __('Streak Target') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adClaimTierRows as $tierRow)
                        @php
                            $isCurrentTierRow = !empty($tierRow['is_current']);
                            $isNextTierRow = !empty($tierRow['is_next']);
                            $isLockedTierRow = !empty($tierRow['is_locked']);
                        @endphp
                        <tr class="{{ $isCurrentTierRow ? 'is-current' : ($isNextTierRow ? 'is-next' : '') }}">
                            <td>{{ $tierRow['tier_name'] ?? '--' }}</td>
                            <td>{{ $tierRow['requirement_label'] ?? '--' }}</td>
                            <td>{{ number_format((float) ($tierRow['daily_claim_amount'] ?? 0), 2) }} OBX</td>
                            <td>{{ (int) ($tierRow['streak_days'] ?? 1) }} {{ __('days') }}</td>
                            <td>
                                @if($isCurrentTierRow)
                                    <span class="tier-status tier-status-current">{{ __('Current') }}</span>
                                @elseif($isNextTierRow)
                                    <span class="tier-status tier-status-next">{{ __('Next') }}</span>
                                @elseif($isLockedTierRow)
                                    <span class="tier-status tier-status-locked">{{ __('Locked') }}</span>
                                @else
                                    <span class="tier-status tier-status-open">{{ __('Unlocked') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="color:#94a3b8;">{{ __('Tier requirements are not available yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Streak Tracker ───────────────────────────────────────────────────── --}}
    @if($campaign->isLive())
    @php
        $streakDays  = max(1, (int)($adStreakDays > 0 ? $adStreakDays : ($campaign->streak_days ?? 5)));
        $mod         = $currentStreak % $streakDays;
        $filledDots  = $mod === 0 && $currentStreak > 0 ? $streakDays : $mod;
    @endphp
    <div style="background:var(--dark3);border:1px solid var(--border);border-radius:var(--r);padding:20px 22px;margin-bottom:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">🔥</span>
                <div>
                    <div style="font-weight:700;color:var(--text);font-size:15px;">
                        {{ __('Day Streak') }}: <span style="color:var(--accent);">{{ $currentStreak }}</span>
                    </div>
                    <div style="font-size:11.5px;color:var(--muted);">
                        {{ __('Claim daily to build your streak') }}
                    </div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11.5px;color:var(--muted);">{{ __('Next bonus in') }}</div>
                <div style="font-weight:700;color:#fbbf24;font-size:16px;">
                    {{ $nextBonusAt }} {{ trans_choice('day|days', $nextBonusAt) }}
                </div>
                @if(bccomp((string)$streakBonusAmount, '0', 18) > 0)
                <div style="font-size:11px;color:var(--muted);">+{{ number_format((float)$streakBonusAmount, 2) }} OBX {{ __('bonus') }}</div>
                @endif
            </div>
        </div>

        {{-- Day dots progress --}}
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            @for($d = 1; $d <= $streakDays; $d++)
                @if($d <= $filledDots)
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;" title="{{ __('Day :n', ['n'=>$d]) }}">{{ $d }}</div>
                @else
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--muted);" title="{{ __('Day :n', ['n'=>$d]) }}">{{ $d }}</div>
                @endif
            @endfor
            @if($streakDays <= 30)
            <div style="margin-left:4px;display:flex;align-items:center;gap:5px;">
                <span style="font-size:18px;">🎁</span>
                <span style="font-size:11.5px;color:#fbbf24;font-weight:600;">
                    +{{ number_format((float)$streakBonusAmount, 2) }} OBX
                </span>
            </div>
            @endif
        </div>

        {{-- Progress bar toward next bonus --}}
        @php $strPct = $streakDays > 0 ? round($filledDots / $streakDays * 100) : 0; @endphp
        <div class="progress-bar-wrap" style="margin-top:10px;">
            <div class="progress-bar-fill" style="width:{{ $strPct }}%;background:linear-gradient(90deg,var(--accent),#a855f7);"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--muted);margin-top:3px;">
            <span>{{ __('Day') }} {{ $filledDots }}</span>
            <span>{{ __('Day') }} {{ $streakDays }} 🎁</span>
        </div>
    </div>
    @endif

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
                    {{ __('Claim') }} {{ number_format((float)$adDailyClaimAmount, 2) }} {{ __('OBX Today') }}
                </button>
            </form>
            <div style="font-size:11.5px;color:var(--muted);text-align:center;margin-top:6px;">
                {{ __('Tokens are locked off-chain. Withdraw opens only when admin enables withdrawal.') }}
            </div>
        @endif
    @endif

    {{-- ── Withdrawal Panel ─────────────────────────────────────────────────── --}}
    @if($campaign->hasEnded() && bccomp($totalLockedObx, '0', 18) > 0)
        @if(!$airdropWithdrawEnabled)
            <div class="unlock-panel">
                <h6><i class="fa fa-lock"></i> {{ __('Withdrawals Not Open Yet') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                    {{ __('You have') }} <b style="color:var(--text);">{{ number_format((float)$totalLockedObx, 4) }} OBX</b>
                    {{ __('locked. Admin has not enabled withdrawals yet.') }}
                </p>
            </div>
        @elseif(!$campaign->fee_revealed)
            <div class="unlock-panel">
                <h6><i class="fa fa-eye-slash"></i> {{ __('Withdrawal Fee Hidden') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                    {{ __('Withdrawals are enabled, but the fee is hidden until admin reveals it.') }}
                </p>
            </div>
        @elseif($unlockRecord && $unlockRecord->status === 'confirmed')
            <div class="unlock-panel" style="border-color:rgba(63,185,80,.25);">
                <h6 style="color:#3fb950;"><i class="fa fa-check-circle"></i> {{ __('Withdrawal Completed') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                    {{ __(':obx OBX has been sent to your OBX Wallet.', ['obx' => number_format((float)$unlockRecord->obx_released, 4)]) }}
                </p>
            </div>
        @elseif($unlockRecord
            && $unlockRecord->status === 'pending'
            && strtolower((string) $unlockRecord->nowpayments_payment_status) === 'delivery_failed')
            <div class="unlock-panel" style="border-color:rgba(248,81,73,.32);background:rgba(248,81,73,.08);">
                <h6 style="color:#f85149;"><i class="fa fa-exclamation-triangle"></i> {{ __('Payment Received, OBX Delivery Failed') }}</h6>
                <p style="color:#fca5a5;font-size:13px;">
                    {{ __('Your payment was confirmed by gateway, but OBX delivery failed. Please contact support and include your Payment ID.') }}
                </p>
                <div style="margin-top:10px;background:rgba(0,0,0,.2);border-radius:7px;padding:10px 14px;font-size:12px;color:#fecaca;word-break:break-all;">
                    <div><b>{{ __('Payment ID') }}:</b> {{ $unlockRecord->nowpayments_payment_id ?: __('N/A') }}</div>
                    <div><b>{{ __('Gateway Status') }}:</b> {{ strtoupper($unlockRecord->nowpayments_payment_status ?: 'delivery_failed') }}</div>
                    <div><b>{{ __('Pay Amount') }}:</b> {{ $unlockRecord->nowpayments_pay_amount }} {{ strtoupper($unlockRecord->nowpayments_pay_currency ?: $airdropWithdrawPayCurrency) }}</div>
                </div>
            </div>
        @elseif($unlockRecord && $unlockRecord->status === 'pending' && $unlockRecord->nowpayments_payment_id)
            <div class="unlock-panel">
                <h6><i class="fa fa-clock-o"></i> {{ __('Payment Pending') }}</h6>
                <p style="color:var(--muted);font-size:13px;">
                    {{ __('Send the exact payment below. After NOWPayments confirms, OBX is sent to your OBX Wallet.') }}
                </p>
                <div style="margin-top:10px;background:rgba(0,0,0,.2);border-radius:7px;padding:10px 14px;font-size:12px;color:#c7d2fe;word-break:break-all;">
                    <div><b>{{ __('Pay Amount') }}:</b> {{ $unlockRecord->nowpayments_pay_amount }} {{ strtoupper($unlockRecord->nowpayments_pay_currency ?: $airdropWithdrawPayCurrency) }}</div>
                    <div><b>{{ __('Pay Address') }}:</b> {{ $unlockRecord->nowpayments_pay_address ?: __('Pending from gateway') }}</div>
                    <div><b>{{ __('Payment ID') }}:</b> {{ $unlockRecord->nowpayments_payment_id }}</div>
                    <div><b>{{ __('Gateway Status') }}:</b> {{ strtoupper($unlockRecord->nowpayments_payment_status ?: 'waiting') }}</div>
                </div>
            </div>
        @elseif($userTierFee <= 0)
            <div class="unlock-panel">
                <h6><i class="fa fa-exclamation-triangle"></i> {{ __('Withdrawal Fee Not Configured') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:0;">
                    {{ __('Withdrawal is enabled, but your fee tier is not configured by admin yet.') }}
                </p>
            </div>
        @else
            <div class="unlock-panel">
                <h6><i class="fa fa-unlock-alt"></i> {{ __('Campaign Ended — Withdraw Your OBX') }}</h6>
                <p style="color:var(--muted);font-size:13px;margin-bottom:4px;">
                    {{ __('You have') }} <b style="color:var(--text);">{{ number_format((float)$totalLockedObx, 4) }} OBX</b>
                    {{ __('locked. Pay') }} <b style="color:#fbbf24;">{{ number_format($userTierFee, 2) }} USDT</b>
                    {{ __('to send OBX to your OBX Wallet.') }}
                </p>
                <p style="color:var(--muted);font-size:12px;margin-bottom:0;">{{ __('Tier :tier based on total buy $:amount', ['tier' => $userTierLabel, 'amount' => number_format($userPurchaseUsd, 2)]) }}</p>
                <form action="{{ route('user.airdrop.unlock') }}" method="POST">
                    @csrf
                    <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
                    <button type="submit" class="unlock-btn">
                        <i class="fa fa-credit-card"></i>
                        {{ __('Pay :fee USDT and Withdraw :obx OBX', [
                            'fee' => number_format($userTierFee, 2),
                            'obx' => number_format((float)$totalLockedObx, 4)
                        ]) }}
                    </button>
                </form>
            </div>
        @endif
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
                $pcUserFee = (float) ($pc->resolveUnlockFeeByPurchaseUsd($userPurchaseUsd) ?? 0);
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
                        @elseif(!$airdropWithdrawEnabled)
                            <span class="flag-hidden"><i class="fa fa-lock"></i> {{ __('Locked by admin') }}</span>
                        @elseif(!$pc->fee_revealed)
                            <span class="flag-hidden"><i class="fa fa-eye-slash"></i> {{ __('Fee hidden') }}</span>
                        @elseif($pcUnlock && $pcUnlock->status === 'pending' && strtolower((string) $pcUnlock->nowpayments_payment_status) === 'delivery_failed')
                            <span style="color:var(--danger);font-size:12.5px;"><i class="fa fa-exclamation-triangle"></i> {{ __('Delivery Failed (Support)') }}</span>
                        @elseif($pcUnlock && $pcUnlock->status === 'pending')
                            <span style="color:#fbbf24;font-size:12.5px;"><i class="fa fa-clock-o"></i> {{ __('Payment Pending') }}</span>
                        @elseif($pcUserFee > 0)
                            <form action="{{ route('user.airdrop.unlock') }}" method="POST" style="display:inline;">
                                @csrf
                                <input type="hidden" name="campaign_id" value="{{ $pc->id }}">
                                <button class="unlock-btn" style="padding:8px 18px;font-size:12.5px;width:auto;margin:0;">
                                    <i class="fa fa-credit-card"></i>
                                    {{ __('Pay :fee USDT & Withdraw', ['fee' => number_format($pcUserFee, 2)]) }}
                                </button>
                            </form>
                        @else
                            <span class="flag-hidden"><i class="fa fa-exclamation-triangle"></i> {{ __('Fee not configured') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

</div>
@endsection

@section('script')
<script>
(function(){
    var toggle = document.querySelector('.aq-toggle');
    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function() {
        var detailsId = toggle.getAttribute('data-target');
        var details = document.getElementById(detailsId);
        if (!details) {
            return;
        }

        var isHidden = details.hasAttribute('hidden');
        if (isHidden) {
            details.removeAttribute('hidden');
            toggle.textContent = toggle.getAttribute('data-close-text') || 'Hide Details';
        } else {
            details.setAttribute('hidden', 'hidden');
            toggle.textContent = toggle.getAttribute('data-open-text') || 'Show Details';
        }
    });
})();
</script>
@endsection
