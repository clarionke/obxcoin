@extends('admin.master',['menu'=>'staking', 'sub_menu'=>'staking_positions'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
    .staking-stat-card { border-radius: 8px; padding: 20px 24px; color: #fff; }
    .staking-stat-card .stat-value { font-size: 1.7rem; font-weight: 700; }
    .staking-stat-card .stat-label { font-size: 0.85rem; opacity: .8; }
</style>
@endsection
@section('content')
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{__('Staking')}}</li>
                    <li class="active-item">{{__('All Positions')}}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="user-management padding-30">
        <!-- stat cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="staking-stat-card" style="background:linear-gradient(135deg,#6a3de8,#4318d1);">
                    <div class="stat-value">{{ number_format($stats['total_staked'],2) }}</div>
                    <div class="stat-label">{{__('Total Staked OBX')}}</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="staking-stat-card" style="background:linear-gradient(135deg,#e84a3d,#b01a10);">
                    <div class="stat-value">{{ number_format($stats['total_burned'],2) }}</div>
                    <div class="stat-label">{{__('Total Burned OBX')}}</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="staking-stat-card" style="background:linear-gradient(135deg,#27ae60,#1a7a41);">
                    <div class="stat-value">{{ number_format($stats['total_rewards'],2) }}</div>
                    <div class="stat-label">{{__('Total Rewards Paid OBX')}}</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="staking-stat-card" style="background:linear-gradient(135deg,#f39c12,#b8760c);">
                    <div class="stat-value">{{ $stats['active_count'] }}</div>
                    <div class="stat-label">{{__('Active Stakes')}}</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="header-bar">
                    <div class="table-title">
                        <h3>{{__('Staking Positions')}}</h3>
                    </div>
                </div>
                <div class="table-area">
                    <div class="table-responsive">
                        <table id="table" class="table table-borderless custom-table display text-center" width="100%">
                            <thead>
                            <tr>
                                <th class="text-left">{{__('User')}}</th>
                                <th>{{__('Pool')}}</th>
                                <th>{{__('APY')}}</th>
                                <th>{{__('Gross')}}</th>
                                <th>{{__('Burned (stake)')}}</th>
                                <th>{{__('Net Staked')}}</th>
                                <th>{{__('Reward')}}</th>
                                <th>{{__('Returned')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Stake Tx')}}</th>
                                <th>{{__('Unstake Tx')}}</th>
                                <th>{{__('Staked At')}}</th>
                                <th>{{__('Unlock At')}}</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
<script>
const EXPLORER_TX_BASE = '{{ explorer_tx_base() }}';
$('#table').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    retrieve: true,
    bLengthChange: true,
    responsive: false,
    ajax: '{{ route("admin.staking.index") }}',
    order: [[11, 'desc']],
    autoWidth: false,
    language: { paginate: { next: 'Next &#8250;', previous: '&#8249; Previous' } },
    columns: [
        {"data": "email",           "orderable": false, "className": "text-left"},
        {"data": "pool_name",       "orderable": false},
        {"data": "apy",             "orderable": false},
        {"data": "gross_amount",    "orderable": false},
        {"data": "burned_on_stake", "orderable": false},
        {"data": "net_amount",      "orderable": false},
        {"data": "reward_earned",   "orderable": false},
        {"data": "returned_amount", "orderable": false},
        {"data": "status_badge",    "orderable": false},
        {"data": "tx_hash_stake",   "orderable": false, "render": function(d) {
            if (!d) return '&mdash;';
            return '<a href="'+EXPLORER_TX_BASE+d+'" target="_blank" rel="noopener noreferrer" title="'+d+'">'+d.substring(0,14)+'&#8230;</a>';
        }},
        {"data": "tx_hash_unstake", "orderable": false, "render": function(d) {
            if (!d) return '&mdash;';
            return '<a href="'+EXPLORER_TX_BASE+d+'" target="_blank" rel="noopener noreferrer" title="'+d+'">'+d.substring(0,14)+'&#8230;</a>';
        }},
        {"data": "staked_at",  "orderable": true},
        {"data": "lock_until", "orderable": false},
    ],
});
</script>
@endsection
