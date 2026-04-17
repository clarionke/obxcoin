@php
    $obxChainId = (int) (settings('chain_id') ?: settings('walletconnect_chain_id') ?: settings('presale_chain_id') ?: 0);
    $obxChainLink = strtolower(trim((string) (settings('chain_link') ?: settings('bsc_rpc_url') ?: config('blockchain.bsc_rpc_url', ''))));
    if ($obxChainId <= 0) {
        if (str_contains($obxChainLink, 'prebsc') || str_contains($obxChainLink, 'testnet') || str_contains($obxChainLink, '97')) {
            $obxChainId = 97;
        } else {
            $obxChainId = 56;
        }
    }
    $obxNetworkName = ((int)$obxChainId === 97) ? 'BSC Testnet' : 'BSC Mainnet';
@endphp

<div class="row mt-4">
    <div class="col-lg-4 offset-lg-1">
        <div class="qr-img text-center">
            @if(!empty($wallet_address) && !empty($wallet_address->address))  {!! QrCode::size(300)->generate($wallet_address->address); !!}
            @else
                {!! QrCode::size(300)->generate('0'); !!}
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="cp-user-copy tabcontent-right">
            <form action="#">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="button" class="copy_to_clip btn">{{__('Copy')}}</button>
                    </div>
                    <input readonly value="{{isset($wallet_address) ? $wallet_address->address : 0}}"
                           type="text" class="form-control" id="addressVal">
                </div>
            </form>
            <div class="aenerate-address">
                @if(empty($wallet_address) || empty($wallet_address->address))
                    <a class="btn cp-user-buy-btn"  href="{{route('generateNewAddress')}}?wallet_id={{$wallet->id}}">
                        {{__('Generate address')}}
                    </a>
                @endif
            </div>
        </div>
        <div class="card mt-4">
            <h5 class="card-header">{{__("Token Info")}}</h5>
            <div class="card-body">
                <p> <label for="">{{__('Chain link')}} : </label></p>
                <p>
                    <label for="">{{allsetting('chain_link')}}</label>
                </p>
                <p><label for="">{{__('Contract address')}} :</label></p>
                <p>
                    <label for="">
                        {{allsetting('contract_address')}}
                    </label>
                </p>
                <p><label for="">{{__('Token Symbol')}} :</label></p>
                <p>
                    <label for="">
                        {{isset(allsetting()['coin_name']) ? allsetting()['coin_name'] : ''}}
                    </label>
                </p>
            </div>
        </div>

        <div class="card mt-4">
            <h5 class="card-header">{{__('Past OBX Wallet Addresses')}}</h5>
            <div class="card-body p-0">
                <div class="table-responsive mb-0">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>{{__('Address')}}</th>
                            <th>{{__('Token')}}</th>
                            <th>{{__('Network')}}</th>
                            <th>{{__('Contract')}}</th>
                            <th>{{__('Created')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if(isset($address_histories) && count($address_histories) > 0)
                            @foreach($address_histories as $address_history)
                                <tr>
                                    <td style="word-break:break-all;">{{$address_history->address}}</td>
                                    <td>{{isset(allsetting()['coin_name']) ? allsetting()['coin_name'] : 'OBX'}}</td>
                                    <td>{{$obxNetworkName}}</td>
                                    <td style="word-break:break-all;">{{allsetting('contract_address')}}</td>
                                    <td>{{$address_history->created_at}}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center">{{__('No past address found')}}</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($address_histories) && count($address_histories) > 0)
                <div class="card-footer">
                    {{ $address_histories->appends(request()->input())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
