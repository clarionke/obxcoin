@extends('admin.master',['menu'=>'staking', 'sub_menu'=>'staking_transactions'])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{__('Staking')}}</li>
                    <li class="active-item">{{__('Transactions')}}</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="user-management padding-30">
        <div class="row">
            <div class="col-12">
                <div class="header-bar">
                    <div class="table-title">
                        <h3>{{__('All Staking Transactions')}}</h3>
                    </div>
                </div>
                <div class="table-area">
                    <div class="table-responsive">
                        <table id="table" class="table table-borderless custom-table display text-center" width="100%">
                            <thead>
                            <tr>
                                <th class="text-left">{{__('User')}}</th>
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
$('#table').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    retrieve: true,
    bLengthChange: true,
    responsive: false,
    ajax: '{{ route("admin.staking.transactions") }}',
    order: [[5, 'desc']],
    autoWidth: false,
    language: { paginate: { next: 'Next &#8250;', previous: '&#8249; Previous' } },
    columns: [
        {"data": "email",      "orderable": false, "className": "text-left"},
        {"data": "type_label", "orderable": false, "render": function(d, t, row) {
            var color = TYPE_COLORS[row.type] || 'secondary';
            return '<span class="badge badge-'+color+'">'+d+'</span>';
        }},
        {"data": "amount",     "orderable": false},
        {"data": "tx_hash",    "orderable": false, "render": function(d) {
            if (!d) return '&mdash;';
            return '<a href="'+EXPLORER_TX_BASE+d+'" target="_blank" rel="noopener noreferrer" title="'+d+'">'+d.substring(0,14)+'&#8230;</a>';
        }},
        {"data": "status",     "orderable": false},
        {"data": "created_at", "orderable": true},
    ],
});
</script>
@endsection
