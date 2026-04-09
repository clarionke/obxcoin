@extends('user.master',['menu'=>'staking', 'sub_menu'=>'staking_transactions'])
@section('title', isset($title) ? $title : '')
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card cp-user-custom-card cp-user-wallet-card">
            <div class="card-body">
                <div class="cp-user-card-header-area">
                    <h4><i class="fa fa-list-alt mr-2" style="color:var(--accent);"></i>{{__('Staking Transactions')}}</h4>
                </div>
                <div class="cp-user-wallet-table table-responsive mt-3">
                    <table id="staking_tx_table" class="table">
                        <thead>
                        <tr>
                            <th>{{__('Type')}}</th>
                            <th>{{__('Amount (OBX)')}}</th>
                            <th>{{__('Tx Hash')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Date')}}</th>
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
const TYPE_COLORS = {
    'stake_in':     'primary',
    'unstake_out':  'info',
    'burn_stake':   'danger',
    'burn_unstake': 'danger',
    'reward':       'success',
};
$(document).ready(function () {
    $('#staking_tx_table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        responsive: false,
        ajax: '{{ route("user.staking.transactions") }}',
        order: [[4, 'desc']],
        autoWidth: false,
        language: { paginate: { next: 'Next &#8250;', previous: '&#8249; Previous' } },
        columns: [
            {"data": "type", "orderable": false, "render": function(data) {
                var color = TYPE_COLORS[data] || 'secondary';
                return '<span class="badge badge-'+color+'">'+data.replace(/_/g,' ')+'</span>';
            }},
            {"data": "amount",  "orderable": false},
            {"data": "tx_hash", "orderable": false, "render": function(data) {
                if (!data) return '&mdash;';
                return '<a href="'+EXPLORER_TX_BASE+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,14)+'&#8230;</a>';
            }},
            {"data": "status",     "orderable": false},
            {"data": "created_at", "orderable": true},
        ],
    });
});
</script>
@endsection
