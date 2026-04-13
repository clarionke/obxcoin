<style>
    .wallet-default-deposit-card {
        background: #161b22;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        padding: 18px;
        margin-top: 14px;
    }
    .wallet-default-qr {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        padding: 14px;
        text-align: center;
    }
    .wallet-default-qr svg {
        max-width: 100%;
        height: auto;
    }
    .wallet-default-deposit-card .form-control {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .14);
        color: #e6edf3;
    }
    .wallet-default-deposit-card .copy_to_clip {
        background: rgba(99, 102, 241, .18);
        border: 1px solid rgba(99, 102, 241, .34);
        color: #cdd3ff;
        font-size: 12px;
        font-weight: 600;
    }
    .wallet-default-btn {
        display: inline-flex;
        margin-top: 10px;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid rgba(99, 102, 241, .38);
        background: rgba(99, 102, 241, .16);
        color: #d2d8ff;
        text-decoration: none;
    }
    .wallet-default-btn:hover {
        color: #e8ebff;
        text-decoration: none;
        background: rgba(99, 102, 241, .24);
    }
    .token-info-card {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 12px;
        margin-top: 14px;
        overflow: hidden;
    }
    .token-info-card h5 {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        color: #dbe2ff;
        padding: 12px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
        background: rgba(99, 102, 241, .08);
    }
    .token-info-body {
        padding: 12px 14px;
    }
    .token-info-row {
        margin-bottom: 9px;
    }
    .token-info-row:last-child {
        margin-bottom: 0;
    }
    .token-info-row .k {
        color: #8f9aba;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: .35px;
        margin-bottom: 3px;
    }
    .token-info-row .v {
        color: #e3e9ff;
        font-size: 13px;
        word-break: break-all;
    }
</style>

<div class="wallet-default-deposit-card">
    <div class="row align-items-start">
        <div class="col-lg-4 mb-3 mb-lg-0">
            <div class="wallet-default-qr">
                @if(!empty($wallet_address) && !empty($wallet_address->address))
                    {!! QrCode::size(300)->generate($wallet_address->address); !!}
                @else
                    {!! QrCode::size(300)->generate('0'); !!}
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            <form action="#">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="button" class="copy_to_clip btn">{{__('Copy')}}</button>
                    </div>
                    <input readonly value="{{isset($wallet_address) ? $wallet_address->address : 0}}" type="text" class="form-control" id="addressVal">
                </div>
            </form>

            @if(empty($wallet_address) || empty($wallet_address->address))
                <a class="wallet-default-btn" href="{{route('generateNewAddress')}}?wallet_id={{$wallet->id}}">
                    {{__('Generate address')}}
                </a>
            @endif

            <div class="token-info-card">
                <h5>{{__("Token Info")}}</h5>
                <div class="token-info-body">
                    <div class="token-info-row">
                        <div class="k">{{__('Chain link')}}</div>
                        <div class="v">{{allsetting('chain_link')}}</div>
                    </div>
                    <div class="token-info-row">
                        <div class="k">{{__('Contract address')}}</div>
                        <div class="v">{{allsetting('contract_address')}}</div>
                    </div>
                    <div class="token-info-row">
                        <div class="k">{{__('Token Symbol')}}</div>
                        <div class="v">{{isset(allsetting()['coin_name']) ? allsetting()['coin_name'] : ''}}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
