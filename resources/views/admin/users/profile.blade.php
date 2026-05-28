@extends('admin.master',['menu'=>'users','sub_menu'=>'user'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
.admin-airdrop-panel {
    border: 1px solid #d8e5ff;
    background: linear-gradient(135deg, #f5f9ff 0%, #eef4ff 100%);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 18px;
}
.admin-airdrop-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}
.admin-airdrop-title {
    font-size: 14px;
    font-weight: 700;
    color: #1e3a8a;
}
.admin-airdrop-sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.admin-airdrop-toggle {
    border: 1px solid #a5b4fc;
    background: #eef2ff;
    color: #3730a3;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 6px 11px;
    cursor: pointer;
}
.admin-airdrop-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.admin-airdrop-item {
    border: 1px solid #dbeafe;
    background: #ffffff;
    border-radius: 9px;
    padding: 9px 11px;
}
.admin-airdrop-item span {
    display: block;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: .45px;
    color: #64748b;
    margin-bottom: 3px;
}
.admin-airdrop-item strong {
    display: block;
    color: #0f172a;
    font-size: 18px;
    line-height: 1.2;
}
.admin-airdrop-msg {
    border: 1px solid transparent;
    border-radius: 9px;
    font-size: 12px;
    padding: 9px 11px;
    margin-top: 10px;
}
.admin-airdrop-msg-info {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
}
.admin-airdrop-msg-success {
    border-color: #86efac;
    background: #f0fdf4;
    color: #166534;
}
.admin-airdrop-msg-warning {
    border-color: #fcd34d;
    background: #fffbeb;
    color: #92400e;
}
.admin-airdrop-msg-neutral {
    border-color: #cbd5e1;
    background: #f8fafc;
    color: #475569;
}
.admin-airdrop-track {
    margin-top: 8px;
    height: 6px;
    border-radius: 99px;
    background: #dbeafe;
    overflow: hidden;
}
.admin-airdrop-track > div {
    height: 6px;
    border-radius: 99px;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    transition: width .3s ease;
}
.admin-airdrop-foot {
    margin-top: 5px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    color: #64748b;
    font-size: 11px;
}
.admin-airdrop-details {
    margin-top: 10px;
    border-top: 1px dashed #cbd5e1;
    padding-top: 10px;
}
.admin-airdrop-details p {
    margin: 0 0 8px;
    color: #475569;
    font-size: 12px;
}
@media(max-width:767px) {
    .admin-airdrop-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media(max-width:520px) {
    .admin-airdrop-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endsection
@section('content')
    <!-- breadcrumb -->
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{__('User management')}}</li>
                    <li class="active-item">{{__('User Profile')}}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- /breadcrumb -->
    <!-- User Management -->
    <div class="user-management profile">
        <div class="row">
            <div class="col-12">
                <div class="profile-info padding-40">
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

                    <div class="admin-airdrop-panel">
                        <div class="admin-airdrop-head">
                            <div>
                                <div class="admin-airdrop-title"><i class="fa fa-gift"></i> {{ __('Airdrop Snapshot') }}</div>
                                <div class="admin-airdrop-sub">{{ $adCampaign ? $adCampaign->name : __('No active campaign selected') }}</div>
                            </div>
                            <button type="button" class="admin-airdrop-toggle" data-target="adminAirdropDetails" data-open-text="{{ __('Show Details') }}" data-close-text="{{ __('Hide Details') }}">{{ __('Show Details') }}</button>
                        </div>

                        <div class="admin-airdrop-grid">
                            <div class="admin-airdrop-item">
                                <span>{{ __('Today Streak') }}</span>
                                <strong>{{ $adTodayStreak }}</strong>
                            </div>
                            <div class="admin-airdrop-item">
                                <span>{{ __('Remaining Streak') }}</span>
                                <strong>{{ $adRemaining }}</strong>
                            </div>
                            <div class="admin-airdrop-item">
                                <span>{{ __('Claim Status') }}</span>
                                <strong>{{ $adClaimedToday ? __('Claimed Today') : __('Not Claimed') }}</strong>
                            </div>
                        </div>

                        <div class="admin-airdrop-msg admin-airdrop-msg-{{ $adCongratsTone }}">{{ $adCongrats }}</div>
                        <div class="admin-airdrop-track"><div style="width:{{ $adProgressPct }}%"></div></div>
                        <div class="admin-airdrop-foot">
                            <span>{{ __('Milestone Cycle') }}: {{ $adStreakDays > 0 ? $adStreakDays : '--' }} {{ __('days') }}</span>
                            <span>{{ __('Progress') }}: {{ $adProgressPct }}%</span>
                        </div>

                        <div class="admin-airdrop-details" id="adminAirdropDetails" hidden>
                            @if($adCampaign)
                                <p>{{ __('Bonus on milestone') }}: +{{ number_format((float) $adBonusAmount, 2) }} OBX</p>
                                <p>{{ __('Campaign window') }}: {{ $adCampaign->start_date->format('M d, Y H:i') }} → {{ $adCampaign->end_date->format('M d, Y H:i') }}</p>
                            @else
                                <p>{{ __('Airdrop data will appear here when a campaign is active for users.') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 mb-xl-0 mb-4">
                            <div class="user-info text-center">
                                <div class="avater-img">
                                    <img src="{{show_image($user->id,'user')}}" alt="">
                                </div>
                                <h4>{{$user->first_name.' '.$user->last_name}}</h4>
                                <p>{{$user->email}}</p>
                                <p class="cp-user-btc">
                                    @if(!empty($clubInfos['club_id']))
                                        <span>
                                            <img src="{{ $clubInfos['plan_image'] }}" class="img-fluid" alt="">
                                        </span>
                                        {{ $clubInfos['plan_name'] }}
                                    @endif
                                </p>
                                <div class="cp-user-available-balance-profile">
                                    <p>{{__('Blocked Coin')}} <span>{{number_format(get_blocked_coin($user->id),2)}}</span> {{allsetting('coin_name')}}</p>
                                </div>
                            </div>
                            <ul class="profile-transaction">
                                <li class="profile-deposit">
                                    <p>{{__('Total Deposit')}}</p>
                                    <h4>{{total_deposit($user->id)}} {{ settings('coin_name') }}</h4>
                                </li>
                                <li class="profile-withdrow">
                                    <p>{{__('Total Withdrawal')}}</p>
                                    <h4>{{total_withdrawal($user->id) }} {{ settings('coin_name') }}</h4>
                                </li>
                            </ul>
                        </div>
                        <div class="col-xl-8">
                            <div class="profile-info-table">
                                <ul>
                                    <li>
                                        <span>{{__('Name')}}</span>
                                        <span class="dot">:</span>
                                        <span><strong>{{$user->first_name.' '.$user->last_name}}</strong></span>
                                    </li>
                                    <li>
                                        <span>{{__('Role')}}</span>
                                        <span class="dot">:</span>
                                        <span><strong>{{userRole($user->role)}}</strong></span>
                                    </li>
                                    <li>
                                        <span>{{__('Email')}}</span>
                                        <span class="dot">:</span>
                                        <span><strong>{{$user->email}}</strong></span>
                                    </li>
                                    <li>
                                        <span>{{__('Email Verification')}}</span>
                                        <span class="dot">:</span>
                                        <span class=""><strong>{{statusAction($user->is_verified)}}</strong></span>
                                    </li>
                                    <li>
                                        <span>{{__('Contact')}}</span>
                                        <span class="dot">:</span>
                                        <span><strong>{{$user->phone}}</strong></span>
                                    </li>
                                    <li>
                                        <span>{{__('Active Status')}}</span>
                                        <span class="dot">:</span>
                                        <span>{{statusAction($user->status)}}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /User Management -->
@endsection

@section('script')
<script>
(function(){
    var toggle = document.querySelector('.admin-airdrop-toggle');
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
