@extends('user.master',['menu'=>'staking', 'sub_menu'=>'staking_history'])
@section('title', isset($title) ? $title : '')
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card cp-user-custom-card cp-user-wallet-card">
            <div class="card-body">
                <div class="cp-user-card-header-area">
                    <h4><i class="fa fa-history mr-2" style="color:var(--accent);"></i>{{__('My Staking History')}}</h4>
                </div>
                <div class="cp-user-wallet-table table-responsive mt-3">
                    <table id="staking_history_table" class="table">
                        <thead>
                        <tr>
                            <th>{{__('Pool')}}</th>
                            <th>{{__('APY')}}</th>
                            <th>{{__('Gross Staked')}}</th>
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
$(document).ready(function () {
    $('#staking_history_table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        responsive: false,
        ajax: '{{ route("user.staking.history") }}',
        order: [[10, 'desc']],
        autoWidth: false,
        language: { paginate: { next: 'Next &#8250;', previous: '&#8249; Previous' } },
        columns: [
            {"data": "pool_name",    "orderable": false},
            {"data": "apy",          "orderable": false},
            {"data": "gross_amount", "orderable": false},
            {"data": "burned_on_stake","orderable": false},
            {"data": "net_amount",   "orderable": false},
            {"data": "reward_earned","orderable": false},
            {"data": "returned_amount","orderable": false},
            {"data": "status_badge", "orderable": false},
            {"data": "tx_hash_stake", "orderable": false, "render": function(data) {
                if (!data) return '&mdash;';
                return '<a href="'+EXPLORER_TX_BASE+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,14)+'&#8230;</a>';
            }},
            {"data": "tx_hash_unstake", "orderable": false, "render": function(data) {
                if (!data) return '&mdash;';
                return '<a href="'+EXPLORER_TX_BASE+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,14)+'&#8230;</a>';
            }},
            {"data": "staked_at",  "orderable": true},
            {"data": "lock_until", "orderable": false},
        ],
    });
});
</script>
@endsection
