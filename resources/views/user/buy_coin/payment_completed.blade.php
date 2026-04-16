@extends('user.master',['menu'=>'buy_coin'])
@section('title', isset($title) ? $title : '')

@section('style')
<style>
    .pay-success-wrap {
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 18px;
        background: linear-gradient(145deg, rgba(21, 34, 74, .82), rgba(11, 20, 45, .85));
        backdrop-filter: blur(10px);
        box-shadow: 0 20px 60px rgba(0,0,0,.35);
        position: relative;
        overflow: hidden;
    }
    .pay-success-wrap:before,
    .pay-success-wrap:after {
        content: "";
        position: absolute;
        border-radius: 999px;
        pointer-events: none;
    }
    .pay-success-wrap:before {
        width: 240px;
        height: 240px;
        right: -90px;
        top: -90px;
        background: radial-gradient(circle, rgba(75, 214, 160, .35), rgba(75, 214, 160, 0));
    }
    .pay-success-wrap:after {
        width: 200px;
        height: 200px;
        left: -80px;
        bottom: -80px;
        background: radial-gradient(circle, rgba(70, 159, 255, .3), rgba(70, 159, 255, 0));
    }
    .pay-success-inner { position: relative; z-index: 1; }
    .celebrate-icon {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 34px;
        color: #5de8b0;
        background: rgba(93, 232, 176, .16);
        border: 1px solid rgba(93, 232, 176, .35);
        animation: popPulse 1.6s ease-in-out infinite;
        box-shadow: 0 0 0 0 rgba(93, 232, 176, .4);
    }
    @keyframes popPulse {
        0% { transform: scale(.92); box-shadow: 0 0 0 0 rgba(93, 232, 176, .45); }
        50% { transform: scale(1); box-shadow: 0 0 0 16px rgba(93, 232, 176, 0); }
        100% { transform: scale(.92); box-shadow: 0 0 0 0 rgba(93, 232, 176, 0); }
    }
    .success-amount {
        font-size: 28px;
        font-weight: 800;
        color: #f3f8ff;
        letter-spacing: .02em;
    }
    .success-sub {
        color: #a8bcde;
        font-size: 14px;
    }
    .success-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        border: 1px solid rgba(93, 232, 176, .35);
        background: rgba(93, 232, 176, .12);
        color: #74efbf;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: .05em;
        text-transform: uppercase;
        padding: 8px 14px;
    }
    .success-row {
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 12px;
        padding: 12px 14px;
        margin-top: 12px;
        background: rgba(255,255,255,.03);
    }
    .success-label { color: #9db1d5; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
    .success-value { color: #f2f7ff; font-weight: 700; font-size: 14px; }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-10 offset-xl-1">
        <div class="card cp-user-custom-card pay-success-wrap">
            <div class="card-body p-4 p-lg-5 pay-success-inner text-center">
                <div class="celebrate-icon mb-3">
                    <i class="fa fa-trophy"></i>
                </div>

                <div class="success-chip mb-3">
                    <i class="fa fa-check-circle"></i> {{__('Payment Confirmed')}}
                </div>

                <h3 class="mb-2" style="color:#f4f8ff; font-weight:800;">{{__('OBX Credited Successfully')}}</h3>
                <p class="success-sub mb-3">{{__('Your purchase has been confirmed and credited to your default OBX/EVM wallet.')}}</p>

                <div class="success-amount">{{ number_format($creditedAmount, 2) }} OBX</div>
                <div class="success-sub">{{__('Amount Credited')}}</div>

                <div class="success-row text-left mt-4">
                    <div class="success-label">{{__('Payment ID')}}</div>
                    <div class="success-value" style="word-break:break-all;">{{ $coinAddress->nowpayments_payment_id ?: '—' }}</div>
                </div>

                <div class="success-row text-left">
                    <div class="success-label">{{__('On-chain Delivery Tx')}}</div>
                    <div class="success-value" style="word-break:break-all;">
                        @if(!empty($coinAddress->obx_delivery_tx_hash))
                            <a href="https://bscscan.com/tx/{{ $coinAddress->obx_delivery_tx_hash }}" target="_blank" rel="noopener noreferrer">{{ $coinAddress->obx_delivery_tx_hash }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    <a href="{{ route('myPocket') }}" class="btn btn-primary px-4 mr-2">{{__('Go To Wallet')}}</a>
                    <a href="{{ route('buyCoinHistory') }}" class="btn btn-outline-light px-4">{{__('View Buy History')}}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
