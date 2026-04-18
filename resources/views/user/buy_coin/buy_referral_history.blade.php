@extends('user.master',['menu'=>'coin','sub_menu'=>'buy_coin_referral_history'])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 mb-xl-0 mb-4">
            <div class="card cp-user-custom-card">
                <div class="card-body">
                    <div class="cp-user-card-header-area">
                        <h4>{{__('Buy Coin Referral History')}}</h4>
                    </div>
                    <div class="cp-user-buy-coin-content-area">
                        <div class="cp-user-wallet-table table-responsive">
                            <table id="table" class="table">
                                <thead>
                                <tr>
                                    <th>{{__('Coin Amount')}}</th>
                                    <th>{{__('Coin Name')}}</th>
                                    <th>{{__('Bonus Tx Hash')}}</th>
                                    <th>{{__('Status')}}</th>
                                    <th>{{__('Created At')}}</th>
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

@endsection

@section('script')
    <script>
        $('#table').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 10,
            retrieve: true,
            bLengthChange: true,
            responsive: true,
            ajax: '{{route('buyCoinReferralHistory')}}',
            order: [4, 'desc'],
            autoWidth: false,
            language: {
                paginate: {
                    next: 'Next &#8250;',
                    previous: '&#8249; Previous'
                }
            },
            columns: [
                {"data": "amount","orderable": false},
                {"data": "wallet_id","orderable": false},
                {"data": "tx_hash","orderable": false, "render": function(data, type, row) {
                    if (!data) return '&mdash;';
                    var txUrl = row && row.tx_url ? row.tx_url : '';
                    if (typeof data === 'string' && /^0x/i.test(data) && txUrl) {
                        return '<a href="'+txUrl+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,16)+'&#8230;</a>';
                    }
                    return data;
                }},
                {"data": "status","orderable": false},
                {"data": "created_at","orderable": false},
            ],
        });
    </script>
@endsection
