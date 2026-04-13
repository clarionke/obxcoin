<style>
    .wallet-deposit-card {
        background: #161b22;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        padding: 18px;
    }
    .wallet-deposit-qr {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        padding: 14px;
        text-align: center;
    }
    .wallet-deposit-qr svg {
        max-width: 100%;
        height: auto;
    }
    .wallet-deposit-actions .input-group {
        margin-bottom: 12px;
    }
    .wallet-deposit-actions .form-control {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .14);
        color: #e6edf3;
    }
    .wallet-deposit-actions .copy_to_clip {
        background: rgba(99, 102, 241, .18);
        border: 1px solid rgba(99, 102, 241, .34);
        color: #cdd3ff;
        font-size: 12px;
        font-weight: 600;
    }
    .wallet-deposit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(99, 102, 241, .38);
        background: rgba(99, 102, 241, .16);
        color: #cfd6ff;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        margin-right: 6px;
        margin-bottom: 8px;
    }
    .wallet-deposit-btn:hover {
        color: #e6e9ff;
        text-decoration: none;
        background: rgba(99, 102, 241, .24);
    }
    .address-list {
        display: none;
        margin-top: 10px;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 10px;
        background: #0f1520;
        padding: 10px;
    }
    .address-list.show { display: block; }
    .address-list .table thead th {
        color: #afb9d8;
        font-size: 11px;
        text-transform: uppercase;
        border-bottom: 1px solid rgba(255, 255, 255, .1);
    }
    .address-list .table td {
        color: #d9e0f6;
        border-top: 1px solid rgba(255, 255, 255, .04);
    }
</style>

<div class="wallet-deposit-card">
    <div class="row align-items-start">
        <div class="col-lg-4 mb-3 mb-lg-0">
            <div class="wallet-deposit-qr">
                @if(!empty($address))
                    {!! QrCode::size(300)->generate($address); !!}
                @else
                    {!! QrCode::size(300)->generate(0); !!}
                @endif
            </div>
        </div>
        <div class="col-lg-8">
            <div class="wallet-deposit-actions">
                <form action="#">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <button type="button" class="copy_to_clip btn">{{__('Copy')}}</button>
                        </div>
                        <input readonly value="{{isset($address) ? $address : 0}}" type="text" class="form-control" id="address">
                    </div>
                </form>

                <a class="wallet-deposit-btn" href="{{route('generateNewAddress')}}?wallet_id={{$wallet_id}}">
                    {{__('Generate a new address')}}
                </a>

                <button class="wallet-deposit-btn" onclick="$('.address-list').toggleClass('show');" type="button">
                    {{__('Show past address')}}
                </button>

                <div class="address-list">
                    <div class="table-responsive">
                        <table class="table mb-2">
                            <thead>
                            <tr>
                                <th>{{__('Address')}}</th>
                                <th>{{__('Created At')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($address_histories as $address_history)
                                <tr>
                                    <td>{{$address_history->address}}</td>
                                    <td>{{$address_history->created_at}}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        @if(isset($address_histories[0]))
                            <div class="pull-right address-pagin">
                                {{ $address_histories->appends(request()->input())->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
