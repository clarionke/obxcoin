@extends('user.master',['menu'=>'buy_coin'])
@section('title', isset($title) ? $title : '')
@section('style')
    <style>
        .user-profile-img{
            height: auto !important;
            width: auto !important;
            border-radius: 0% !important;
        }
    </style>
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card cp-user-custom-card">
                <div class="card-body">
                    @if($coinAddress->payment_type == NOWPAYMENTS)
                    {{-- NOWPayments payment details --}}
                    <div class="cp-user-card-header-area">
                        <h4>{{__('Payment created! Please send the exact amount below to complete your purchase.')}}</h4>
                    </div>
                    <div class="cp-user-buy-coin-content-area mt-5">
                        <div class="cp-user-coin-info">
                            <div class="row align-items-center">
                                <div class="col-lg-4">
                                    <div class="qr-img text-center">
                                        <div class="user-profile-area">
                                            <div class="user-profile-img">
                                                @if($coinAddress->nowpayments_pay_address)
                                                    {!! QrCode::size(250)->generate($coinAddress->nowpayments_pay_address) !!}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-1"></div>
                                <div class="col-lg-6">
                                    <div class="row no-gutters mb-2">
                                        <div class="col-5 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('Pay Amount')}}</h4></div>
                                        <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                                        <div class="col-6 px-1 cp-user-card-header-area">
                                            <h4 class="font-weight-bold font-16 text-primary">
                                                {{$coinAddress->nowpayments_pay_amount}} {{strtoupper($coinAddress->nowpayments_pay_currency)}}
                                            </h4>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mb-2">
                                        <div class="col-5 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('Pay To Address')}}</h4></div>
                                        <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                                        <div class="col-6 px-1 cp-user-card-header-area">
                                            <h4 class="font-weight-normal font-14" style="word-break:break-all;">{{$coinAddress->nowpayments_pay_address}}</h4>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mb-2">
                                        <div class="col-5 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('Payment ID')}}</h4></div>
                                        <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                                        <div class="col-6 px-1 cp-user-card-header-area">
                                            <h4 class="font-weight-normal font-16">{{$coinAddress->nowpayments_payment_id}}</h4>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mb-2">
                                        <div class="col-5 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('OBX Amount')}}</h4></div>
                                        <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                                        <div class="col-6 px-1 cp-user-card-header-area">
                                            <h4 class="font-weight-bold font-16">{{number_format($coinAddress->requested_amount, 2)}} OBX</h4>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning mt-3">
                                        <i class="fa fa-info-circle mr-1"></i>
                                        {{__('Your OBX tokens will be credited automatically once the payment is confirmed on the network.')}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @elseif($coinAddress->payment_type == WALLETCONNECT)
                    {{-- WalletConnect / on-chain payment --}}
                    <div class="cp-user-card-header-area">
                        <h4 class="text-success"><i class="fa fa-check-circle mr-2"></i>{{__('Transaction submitted successfully!')}}</h4>
                    </div>
                    <div class="cp-user-buy-coin-content-area mt-4">
                        <div class="row no-gutters mb-3">
                            <div class="col-4 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('Transaction Hash')}}</h4></div>
                            <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                            <div class="col-7 px-1 cp-user-card-header-area">
                                <h4 class="font-weight-normal font-14" style="word-break:break-all;">
                                    @if($coinAddress->tx_hash)
                                        <a href="https://bscscan.com/tx/{{$coinAddress->tx_hash}}" target="_blank" rel="noopener noreferrer">
                                            {{$coinAddress->tx_hash}}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </h4>
                            </div>
                        </div>
                        <div class="row no-gutters mb-3">
                            <div class="col-4 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('OBX Amount')}}</h4></div>
                            <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                            <div class="col-7 px-1 cp-user-card-header-area">
                                <h4 class="font-weight-bold font-16">{{number_format($coinAddress->requested_amount, 2)}} OBX</h4>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fa fa-info-circle mr-1"></i>
                            {{__('Your OBX tokens will be credited once the on-chain transaction is confirmed.')}}
                        </div>
                    </div>

                    @else
                    {{-- Fallback for legacy records --}}
                    <div class="cp-user-card-header-area">
                        <h4>{{__('Request submitted successfully.')}}</h4>
                    </div>
                    <div class="cp-user-buy-coin-content-area mt-5">
                        <div class="cp-user-coin-info">
                            <div class="row align-items-center">
                                <div class="col-lg-4">
                                    <div class="qr-img text-center">
                                        <div class="user-profile-area">
                                            <div class="user-profile-img">
                                                @if(isset($coinAddress->address)) {!! QrCode::size(300)->generate($coinAddress->address) !!} @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-1"></div>
                                <div class="col-lg-6">
                                    <div class="row no-gutters">
                                        <div class="col-6 cp-user-card-header-area"><h4 class="font-weight-normal font-16">{{__('Address')}} </h4></div>
                                        <div class="col-1 cp-user-card-header-area"><h4>:</h4></div>
                                        <div class="col-5 px-1 cp-user-card-header-area"><h4 class="font-weight-normal font-16"> {{$coinAddress->address ?? '—'}} </h4></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')

@endsection
