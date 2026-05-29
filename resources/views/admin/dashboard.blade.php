@extends('admin.master',['menu'=>'dashboard'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
    .admin-modern-dashboard {
        --md-bg-1: #0f172a;
        --md-bg-2: #111827;
        --md-border: rgba(148, 163, 184, 0.2);
        --md-text: #e2e8f0;
        --md-sub: #93a4c9;
        --md-shadow: 0 16px 34px rgba(2, 8, 23, 0.35);
    }

    .admin-modern-dashboard .admin-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        padding: 18px 20px;
        margin-bottom: 22px;
        border-radius: 14px;
        border: 1px solid rgba(96, 165, 250, 0.32);
        background: linear-gradient(135deg, #0b1f3f 0%, #14244f 45%, #1a2f66 100%);
        box-shadow: var(--md-shadow);
    }

    .admin-modern-dashboard .admin-hero h4 {
        margin: 0 0 4px;
        color: #f8fafc;
        font-size: 20px;
        font-weight: 700;
    }

    .admin-modern-dashboard .admin-hero p {
        margin: 0;
        color: #b8ccf5;
        font-size: 12.5px;
    }

    .admin-modern-dashboard .hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .admin-modern-dashboard .hero-chip {
        border: 1px solid rgba(191, 219, 254, 0.35);
        background: rgba(191, 219, 254, 0.14);
        color: #dbeafe;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .admin-modern-dashboard .dashboard-status .row {
        margin-top: 0;
    }

    .admin-modern-dashboard .dashboard-status .row + .row {
        margin-top: 18px !important;
    }

    .admin-modern-dashboard .modern-stat-card {
        border-radius: 14px;
        border: 1px solid var(--md-border);
        background: linear-gradient(165deg, #111d36 0%, #182642 100%);
        box-shadow: var(--md-shadow);
        position: relative;
        overflow: hidden;
    }

    .admin-modern-dashboard .modern-stat-card::after {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 3px;
    }

    .admin-modern-dashboard .modern-stat-primary::after { background: linear-gradient(90deg, #38bdf8, #0ea5e9); }
    .admin-modern-dashboard .modern-stat-success::after { background: linear-gradient(90deg, #22c55e, #16a34a); }
    .admin-modern-dashboard .modern-stat-danger::after { background: linear-gradient(90deg, #f97316, #ef4444); }
    .admin-modern-dashboard .modern-stat-warning::after { background: linear-gradient(90deg, #f59e0b, #f97316); }
    .admin-modern-dashboard .modern-stat-info::after { background: linear-gradient(90deg, #14b8a6, #06b6d4); }
    .admin-modern-dashboard .modern-stat-violet::after { background: linear-gradient(90deg, #8b5cf6, #6366f1); }

    .admin-modern-dashboard .modern-stat-card .card-body {
        padding: 18px 18px;
    }

    .admin-modern-dashboard .modern-stat-card .status-card-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .admin-modern-dashboard .modern-stat-card .content p {
        margin: 0 0 5px;
        font-size: 11px;
        color: var(--md-sub);
        text-transform: uppercase;
        letter-spacing: 0.65px;
        font-weight: 600;
    }

    .admin-modern-dashboard .modern-stat-card .content h3 {
        margin: 0;
        color: var(--md-text);
        font-size: 22px;
        line-height: 1.2;
        font-weight: 700;
    }

    .admin-modern-dashboard .modern-stat-card .icon {
        width: 46px;
        height: 46px;
        flex-shrink: 0;
        border-radius: 11px;
        border: 1px solid rgba(191, 219, 254, 0.24);
        background: rgba(191, 219, 254, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .admin-modern-dashboard .modern-stat-card .icon img {
        width: 23px;
        height: 23px;
        object-fit: contain;
        filter: brightness(0) invert(1);
        opacity: 0.95;
    }

    .admin-modern-dashboard .user-chart {
        margin-top: 20px;
    }

    .admin-modern-dashboard .modern-panel-card {
        border-radius: 14px;
        border: 1px solid var(--md-border);
        background: linear-gradient(160deg, var(--md-bg-1) 0%, var(--md-bg-2) 100%);
        box-shadow: var(--md-shadow);
        height: 100%;
    }

    .admin-modern-dashboard .modern-panel-card .card-body {
        padding: 18px 18px;
    }

    .admin-modern-dashboard .modern-panel-card .card-top {
        margin-bottom: 8px;
    }

    .admin-modern-dashboard .modern-panel-card .card-top h4 {
        color: #f1f5f9;
        font-size: 14px;
        margin: 0;
        font-weight: 700;
    }

    .admin-modern-dashboard .modern-panel-card h3 {
        color: #e2e8f0;
        font-size: 26px;
        margin: 0;
        font-weight: 700;
    }

    .admin-modern-dashboard .modern-panel-card .subtitle {
        color: #93a4c9;
        font-size: 12px;
    }

    .admin-modern-dashboard #active-user-chart,
    .admin-modern-dashboard #deleted-user-chart {
        min-height: 280px;
    }

    .admin-modern-dashboard .table.custom-table thead th {
        border: 0;
        color: #a8b7d8;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        padding-top: 0;
    }

    .admin-modern-dashboard .table.custom-table tbody td {
        border-top: 1px solid rgba(148, 163, 184, 0.15);
        color: #d4def4;
    }

    .admin-modern-dashboard .table.custom-table tbody tr:hover {
        background: rgba(59, 130, 246, 0.08);
    }

    @media (max-width: 767px) {
        .admin-modern-dashboard .admin-hero {
            padding: 16px;
        }

        .admin-modern-dashboard .admin-hero h4 {
            font-size: 17px;
        }

        .admin-modern-dashboard .modern-stat-card .content h3 {
            font-size: 19px;
        }

        .admin-modern-dashboard .modern-panel-card h3 {
            font-size: 22px;
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
                    <li class="active-item">{{__('Dashboard')}}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- /breadcrumb -->

    <div class="admin-modern-dashboard">
        <div class="admin-hero">
            <div>
                <h4>{{__('Admin Command Center')}}</h4>
                <p>{{__('Track live user funding, dashboard activity, and operational signals from one view.')}}</p>
            </div>
            <div class="hero-chips">
                <span class="hero-chip">{{__('Users:')}} {{number_format($total_user)}}</span>
                <span class="hero-chip">{{__('Active:')}} {{number_format($active_percentage,2)}}%</span>
                <span class="hero-chip">{{__('Presale USDT Paid (All Users):')}} {{number_format($total_usdt_paid,2)}}</span>
                <span class="hero-chip">{{__('Airdrop Participants:')}} {{number_format($total_airdrop_participants)}}</span>
                <span class="hero-chip">{{__('Live Airdrops:')}} {{number_format($live_airdrop_campaigns)}}</span>
                <span class="hero-chip">{{__('Total Airdrops:')}} {{number_format($total_airdrop_campaigns)}}</span>
                <span class="hero-chip">
                    @if((int) $presale_start_block > 0)
                        {{__('Presale Start Block:')}} {{number_format($presale_start_block)}}
                    @else
                        {{__('Presale Start Block:')}} {{__('Not Set')}}
                    @endif
                </span>
            </div>
        </div>

    <!-- Status -->
    <div class="dashboard-status">
        <div class="row">
            <div class="col-xl-4 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-primary">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total Sold Coin')}}</p>
                                <h3>{{number_format($total_sold_coin,4)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/money.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-success">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total Blocked Coin')}}</p>
                                <h3>{{number_format($total_blocked_coin,4)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/funds.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12">
                <div class="card modern-stat-card modern-stat-danger">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total User')}}</p>
                                <h3>{{$total_user}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/team.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-xl-4 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-warning">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total Membership')}}</p>
                                <h3>{{ $total_member }}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/team.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-info">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total Distributed Bonus')}}</p>
                                <h3>{{number_format($bonus_distribution,6)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/funds.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12">
                <div class="card modern-stat-card modern-stat-violet">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total Revenue')}}</p>
                                <h3>{{number_format($total_income,6)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/money.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-xl-3 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-primary">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Total Presale USDT Paid (All Users)')}}</p>
                                <h3>{{number_format($total_usdt_paid,2)}} USDT</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/money.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-success">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Today Presale USDT Paid (All Users)')}}</p>
                                <h3>{{number_format($today_usdt_paid,2)}} USDT</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/funds.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-md-0 mb-4">
                <div class="card modern-stat-card modern-stat-warning">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Last 24h Presale USDT Paid (All Users)')}}</p>
                                <h3>{{number_format($last_24h_usdt_paid,2)}} USDT</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/money.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card modern-stat-card modern-stat-violet">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Successful Buy Orders')}}</p>
                                <h3>{{number_format($successful_buy_count)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/team.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-xl-3 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-primary">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Airdrop Claimed OBX')}}</p>
                                <h3>{{number_format((float) $total_airdrop_claimed_obx,4)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/money.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-xl-0 mb-4">
                <div class="card modern-stat-card modern-stat-success">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Airdrop Participants')}}</p>
                                <h3>{{number_format($total_airdrop_participants)}}</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/team.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-md-0 mb-4">
                <div class="card modern-stat-card modern-stat-info">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Confirmed Unlock Users')}}</p>
                                <h3>{{number_format($total_airdrop_confirmed_unlock_users)}}</h3>
                                <small class="d-block mt-1 text-muted">{{__('Pending:')}} {{number_format($total_airdrop_pending_unlocks)}}</small>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/funds.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card modern-stat-card modern-stat-violet">
                    <div class="card-body">
                        <div class="status-card-inner">
                            <div class="content">
                                <p>{{__('Airdrop USDT Paid')}}</p>
                                <h3>{{number_format((float) $total_airdrop_usdt_paid,2)}} USDT</h3>
                            </div>
                            <div class="icon">
                                <img src="{{asset('assets/user/images/status-icons/money.svg')}}" class="img-fluid" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Status -->
    <div class="user-chart">
        <div class="row">
            <div class="col-lg-6 mb-lg-0 mb-4">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Active User')}}</h4>
                        </div>
                        <div id="active-user-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Inactive User')}}</h4>
                        </div>
                        <div id="deleted-user-chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="user-chart">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-lg-0 mb-4">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Today Successful Buys')}}</h4>
                        </div>
                        <h3 class="mb-1">{{number_format($today_buy_activity_count)}}</h3>
                        <p class="subtitle mb-0">{{__('USDT Paid:')}} {{number_format($today_buy_activity_usdt,2)}} USDT</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-lg-0 mb-4">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Today Deposits')}}</h4>
                        </div>
                        <h3 class="mb-1">{{number_format($today_deposit_activity_count)}}</h3>
                        <p class="subtitle mb-0">{{__('Total Amount:')}} {{number_format($today_deposit_activity_amount,6)}}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Today Withdrawals')}}</h4>
                        </div>
                        <h3 class="mb-1">{{number_format($today_withdraw_activity_count)}}</h3>
                        <p class="subtitle mb-0">{{__('Total Amount:')}} {{number_format($today_withdraw_activity_amount,6)}}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- user chart -->
    <div class="user-chart">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-lg-0 mb-4">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Deposit')}}</h4>
                        </div>
                        <p class="subtitle">{{__('Current Year')}}</p>
                        <canvas id="depositChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-lg-0 mb-4">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Withdrawal')}}</h4>
                        </div>
                        <p class="subtitle">{{__('Current Year')}}</p>
                        <canvas id="withdrawalChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('USDT Paid')}}</h4>
                        </div>
                        <p class="subtitle">{{__('Current Year')}}</p>
                        <canvas id="usdtPaidChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /user chart -->

    <div class="user-management user-chart">
        <div class="row">
            <div class="col-12">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top d-flex justify-content-between align-items-center flex-wrap">
                            <h4>{{__('Recent User Activity')}}</h4>
                            <p class="subtitle mb-0">{{__('Last 24h Activities:')}} {{number_format($activity_last_24h_count)}}</p>
                        </div>
                        <div class="table-area">
                            <div class="table-responsive">
                                <table class="table table-borderless custom-table text-left" width="100%">
                                    <thead>
                                    <tr>
                                        <th>{{__('User')}}</th>
                                        <th>{{__('Activity')}}</th>
                                        <th>{{__('Source')}}</th>
                                        <th>{{__('IP Address')}}</th>
                                        <th>{{__('Time')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($recent_user_activities as $activity)
                                        @php($displayName = trim(($activity->user->first_name ?? '').' '.($activity->user->last_name ?? '')))
                                        <tr>
                                            <td>
                                                @if($displayName !== '')
                                                    {{$displayName}}
                                                @elseif(!empty($activity->user->email))
                                                    {{$activity->user->email}}
                                                @else
                                                    {{__('User')}} #{{$activity->user_id}}
                                                @endif
                                            </td>
                                            <td>{{$user_activity_labels[$activity->action] ?? $activity->action}}</td>
                                            <td>{{$activity->source ?: __('N/A')}}</td>
                                            <td>{{$activity->ip_address ?: __('N/A')}}</td>
                                            <td>
                                                @if($activity->created_at)
                                                    {{$activity->created_at->diffForHumans()}}
                                                @else
                                                    {{__('N/A')}}
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">{{__('No recent user activities found')}}</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /user chart -->

    <div class="user-management user-chart">
        <div class="row">
            <div class="col-12">
                <div class="card modern-panel-card">
                    <div class="card-body">
                        <div class="card-top">
                            <h4>{{__('Pending Withdrawal')}}</h4>
                        </div>
                        <div class="table-area">
                            <div class="table-responsive">
                                <table id="pending_withdrwall" class=" table table-borderless custom-table display text-left"
                                       width="100%">
                                    <thead>
                                    <tr>
                                        <th class="all">{{__('Type')}}</th>
                                        <th class="all">{{__('Sender')}}</th>
                                        <th class="all">{{__('Address')}}</th>
                                        <th class="all">{{__('Receiver')}}</th>
                                        <th class="all">{{__('Amount')}}</th>
                                        <th class="all">{{__('Fees')}}</th>
                                        <th class="all">{{__('Transaction Id')}}</th>
                                        <th class="all">{{__('Update Date')}}</th>
                                        <th class="all">{{__('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /user chart -->

    </div>

@endsection

@section('script')
    <script src="{{asset('assets/chart/chart.min.js')}}"></script>
    <script>
        var ctx = document.getElementById('depositChart').getContext("2d")
        var depositChart = new Chart(ctx, {
            type: 'line',
            yaxisname: "Monthly Deposit",

            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul","Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Monthly Deposit",
                    borderColor: "#1cf676",
                    pointBorderColor: "#1cf676",
                    pointBackgroundColor: "#1cf676",
                    pointHoverBackgroundColor: "#1cf676",
                    pointHoverBorderColor: "#D1D1D1",
                    pointBorderWidth: 4,
                    pointHoverRadius: 2,
                    pointHoverBorderWidth: 1,
                    pointRadius: 3,
                    fill: false,
                    borderWidth: 3,
                    data: {!! json_encode($monthly_deposit) !!}
                }]
            },
            options: {
                legend: {
                    position: "bottom",
                    display: true,
                    labels: {
                        fontColor: '#928F8F'
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            beginAtZero: true,
                            // maxTicksLimit: 5,
                            padding: 20
                        },
                        gridLines: {
                            drawTicks: false,
                            display: false
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            zeroLineColor: "transparent",
                            drawTicks: false,
                            display: false
                        },
                        ticks: {
                            padding: 20,
                            fontColor: "#928F8F",
                            fontStyle: "bold"
                        }
                    }]
                }
            }
        });
    </script>
    <script>
        var ctx = document.getElementById('withdrawalChart').getContext("2d");
        var withdrawalChart = new Chart(ctx, {
            type: 'line',
            yaxisname: "Monthly Withdrawal",

            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul","Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Monthly Withdrawal",
                    borderColor: "#f691be",
                    pointBorderColor: "#f691be",
                    pointBackgroundColor: "#f691be",
                    pointHoverBackgroundColor: "#f691be",
                    pointHoverBorderColor: "#D1D1D1",
                    pointBorderWidth: 4,
                    pointHoverRadius: 2,
                    pointHoverBorderWidth: 1,
                    pointRadius: 3,
                    fill: false,
                    borderWidth: 3,
                    data: {!! json_encode($monthly_withdrawal) !!}
                }]
            },
            options: {
                legend: {
                    position: "bottom",
                    display: true,
                    labels: {
                        fontColor: '#928F8F'
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            beginAtZero: true,
                            // maxTicksLimit: 5,
                            // padding: 20,
                            // max: 1000
                        },
                        gridLines: {
                            drawTicks: false,
                            display: false
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            zeroLineColor: "transparent",
                            drawTicks: true,
                            display: false
                        },
                        ticks: {
                            // padding: 20,
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            // max: 10000,
                            autoSkip: false
                        }
                    }]
                }
            }
        });
    </script>

    <script>
        var usdtCtx = document.getElementById('usdtPaidChart').getContext("2d");
        var usdtPaidChart = new Chart(usdtCtx, {
            type: 'line',
            yaxisname: "Monthly USDT Paid",

            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul","Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Monthly USDT Paid",
                    borderColor: "#55a4ff",
                    pointBorderColor: "#55a4ff",
                    pointBackgroundColor: "#55a4ff",
                    pointHoverBackgroundColor: "#55a4ff",
                    pointHoverBorderColor: "#D1D1D1",
                    pointBorderWidth: 4,
                    pointHoverRadius: 2,
                    pointHoverBorderWidth: 1,
                    pointRadius: 3,
                    fill: false,
                    borderWidth: 3,
                    data: {!! json_encode($monthly_usdt_paid) !!}
                }]
            },
            options: {
                legend: {
                    position: "bottom",
                    display: true,
                    labels: {
                        fontColor: '#928F8F'
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            beginAtZero: true,
                        },
                        gridLines: {
                            drawTicks: false,
                            display: false
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            zeroLineColor: "transparent",
                            drawTicks: false,
                            display: false
                        },
                        ticks: {
                            padding: 20,
                            fontColor: "#928F8F",
                            fontStyle: "bold"
                        }
                    }]
                }
            }
        });
    </script>

    <script>
        var options = {
            series: [{{number_format($active_percentage,2)}}],
            colors: ["#5D58E7"],
            chart: {
                height: 400,
                type: 'radialBar',
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '50',
                    },
                    dataLabels: {
                        value: {
                            color: "#B4B8D7",
                            fontSize: "20px",
                            offsetY: -5,
                            show: true
                        }
                    }
                },
            },
            labels: [''],
            fill: {
                type: "gradient",
                gradient: {
                    shade: "dark",
                    type: "vertical",
                    gradientToColors: ["#309EF9"],
                    stops: [0, 100]
                }
            },
        };

        var chart = new ApexCharts(document.querySelector("#active-user-chart"), options);
        chart.render();
    </script>

    <script>
        var options = {
            series: [{{number_format($inactive_percentage,2)}}],
            colors: ["#F24F4D"],
            chart: {
                height: 400,
                type: 'radialBar',
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '50',
                    },
                    dataLabels: {
                        value: {
                            color: "#B4B8D7",
                            fontSize: "20px",
                            offsetY: -5,
                            show: true
                        }
                    }
                },
            },
            labels: [''],
            fill: {
                type: "gradient",
                gradient: {
                    shade: "dark",
                    type: "vertical",
                    gradientToColors: ["#F89A6B"],
                    stops: [0, 100]
                }
            },
        };

        var chart = new ApexCharts(document.querySelector("#deleted-user-chart"), options);
        chart.render();
    </script>

    <script>
        $('#pending_withdrwall').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            responsive: false,
            ajax: '{{route('adminPendingWithdrawal')}}',
            order: [7, 'desc'],
            autoWidth: false,
            language: {
                paginate: {
                    next: 'Next &#8250;',
                    previous: '&#8249; Previous'
                }
            },
            columns: [
                {"data": "address_type"},
                {"data": "sender"},
                {"data": "address"},
                {"data": "receiver"},
                {"data": "amount"},
                {"data": "fees"},
                {"data": "transaction_hash", "render": function(data) {
                    if (!data) return '&mdash;';
                    if (typeof data === 'string' && data.startsWith('0x')) {
                        return '<a href="{{ explorer_tx_base() }}'+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,16)+'&#8230;</a>';
                    }
                    return data;
                }},
                {"data": "updated_at"},
                {"data": "actions"}
            ]
        });
    </script>
@endsection
