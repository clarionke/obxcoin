@php
    $obxChainId = (int) (settings('chain_id') ?: settings('presale_chain_id') ?: 0);
    $obxChainLink = strtolower(trim((string) (settings('chain_link') ?: settings('bsc_rpc_url') ?: config('blockchain.bsc_rpc_url', ''))));
    if ($obxChainId <= 0) {
        if (str_contains($obxChainLink, 'prebsc') || str_contains($obxChainLink, 'testnet') || str_contains($obxChainLink, '97')) {
            $obxChainId = 97;
        } else {
            $obxChainId = 56;
        }
    }
    $obxNetworkName  = ((int)$obxChainId === 97) ? 'BSC Testnet' : 'BSC Mainnet';
    $bscscanBase     = ((int)$obxChainId === 97) ? 'https://testnet.bscscan.com' : 'https://bscscan.com';
    $contractAddr    = settings('contract_address') ?: '';
    $depositAddress  = isset($wallet_address) ? $wallet_address->address : '';
@endphp

{{-- ── Critical network warning ── --}}
<div class="alert alert-warning d-flex align-items-start mb-3" style="border-left:4px solid #f6c23e;border-radius:6px;">
    <i class="fa fa-exclamation-triangle mr-2 mt-1" style="color:#f6c23e;font-size:1.2rem;flex-shrink:0;"></i>
    <div>
        <strong>{{__('Important — send OBX on BSC only')}}</strong><br>
        {{__('Only send')}} <strong>OBX (BEP-20)</strong> {{__('to this address on')}} <strong>{{$obxNetworkName}}</strong>.
        {{__('Sending any other token or using a different network will result in permanent loss of funds.')}}
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-4 offset-lg-1">
        <div class="qr-img text-center">
            @if(!empty($depositAddress))
                {!! QrCode::size(300)->generate($depositAddress); !!}
                @if($contractAddr)
                    <p class="mt-2 mb-0" style="font-size:11px;opacity:.7;">
                        <a href="{{$bscscanBase}}/token/{{$contractAddr}}?a={{$depositAddress}}" target="_blank" rel="noopener noreferrer">
                            <i class="fa fa-external-link-alt"></i> {{__('View on')}} BSCScan
                        </a>
                    </p>
                @endif
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
                    <input readonly value="{{$depositAddress}}"
                           type="text" class="form-control" id="addressVal">
                </div>
            </form>
            <div class="aenerate-address">
                <div class="card mt-3 mb-2">
                    <h5 class="card-header">{{__('Coin Meta Info')}}</h5>
                    <div class="card-body">
                        <p><label>{{__('Token Symbol')}} :</label></p>
                        <p><label>{{isset(allsetting()['coin_name']) ? allsetting()['coin_name'] : 'OBX'}}</label></p>
                        <p><label>{{__('Network')}} :</label></p>
                        <p><label>{{$obxNetworkName}} (Chain ID: {{$obxChainId}})</label></p>
                        <p><label>{{__('Contract')}} :</label></p>
                        <p>
                            <label style="word-break:break-all;">
                                @if($contractAddr)
                                    <a href="{{$bscscanBase}}/token/{{$contractAddr}}" target="_blank" rel="noopener noreferrer" style="word-break:break-all;">
                                        {{$contractAddr}} <i class="fa fa-external-link-alt" style="font-size:11px;"></i>
                                    </a>
                                @else
                                    {{__('Not configured')}}
                                @endif
                            </label>
                        </p>
                        <p><label>{{__('Token Standard')}} :</label></p>
                        <p><label>BEP-20</label></p>
                        @if($depositAddress)
                            <p><label>{{__('Verify incoming transfers')}} :</label></p>
                            <p>
                                <label>
                                    <a href="{{$bscscanBase}}/token/{{$contractAddr}}?a={{$depositAddress}}" target="_blank" rel="noopener noreferrer">
                                        {{__('View wallet on BSCScan')}} <i class="fa fa-external-link-alt" style="font-size:11px;"></i>
                                    </a>
                                </label>
                            </p>
                        @endif
                    </div>
                </div>
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
