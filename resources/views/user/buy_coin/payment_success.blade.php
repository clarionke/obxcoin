@extends('user.master',['menu'=>'buy_coin'])
@section('title', isset($title) ? $title : '')
@section('style')
    <style>
        .user-profile-img{
            height: auto !important;
            width: auto !important;
            border-radius: 0% !important;
        }
        .np-glass-wrap {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(18,28,64,.76), rgba(11,18,40,.78));
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }
        .np-glass-wrap:before {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            top: -80px;
            right: -70px;
            background: radial-gradient(circle, rgba(53,170,255,.26), rgba(53,170,255,0));
            pointer-events: none;
        }
        .np-glass-inner { position: relative; z-index: 1; }
        .np-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .np-status-pill.pending { color: #ffd568; background: rgba(255, 192, 61, .16); border: 1px solid rgba(255, 192, 61, .35); }
        .np-status-pill.success { color: #6ef0b2; background: rgba(58, 215, 151, .18); border: 1px solid rgba(58, 215, 151, .35); }
        .np-status-pill.rejected { color: #ff8a8a; background: rgba(255, 95, 95, .18); border: 1px solid rgba(255, 95, 95, .35); }
        .np-row {
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            padding: 12px 14px;
            background: rgba(255,255,255,.03);
            margin-bottom: 10px;
        }
        .np-label { font-size: 12px; color: #9cb0d5; text-transform: uppercase; letter-spacing: .05em; }
        .np-value { font-size: 14px; font-weight: 700; color: #f2f6ff; }
        .np-copy-btn {
            border: 1px solid rgba(255,255,255,.25);
            color: #eaf2ff;
            background: rgba(255,255,255,.07);
            border-radius: 10px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .np-copy-btn:hover { background: rgba(255,255,255,.13); }
        .np-instruction {
            border: 1px dashed rgba(115, 200, 255, .35);
            border-radius: 12px;
            padding: 12px;
            background: rgba(26, 38, 72, .45);
            color: #c9d7f3;
            font-size: 13px;
            margin-top: 12px;
        }
        .np-note { font-size: 12px; color: #9eb4db; margin-top: 8px; }
    </style>
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card cp-user-custom-card">
                <div class="card-body">
                    @if($coinAddress->type == NOWPAYMENTS)
                    {{-- NOWPayments payment details --}}
                    <div class="np-glass-wrap p-3 p-lg-4">
                        <div class="np-glass-inner">
                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                <div class="cp-user-card-header-area mb-2 mb-md-0">
                                    <h4 class="mb-0">{{__('Complete Payment')}}</h4>
                                </div>
                                <div id="npStatusBadge" class="np-status-pill pending">
                                    <i class="fa fa-clock-o"></i> {{__('Waiting Payment')}}
                                </div>
                            </div>

                            <div class="row align-items-center">
                                <div class="col-lg-4 text-center mb-4 mb-lg-0">
                                    <div class="user-profile-img d-inline-block p-2" style="background:rgba(255,255,255,.92);border-radius:12px!important;">
                                        @if($coinAddress->nowpayments_pay_address)
                                            {!! QrCode::size(240)->generate($coinAddress->nowpayments_pay_address) !!}
                                        @endif
                                    </div>
                                    <div class="np-note">{{__('Scan QR code with your wallet app')}}</div>
                                </div>
                                <div class="col-lg-8">
                                    <div class="np-row d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="np-label">{{__('Pay Amount')}}</div>
                                            <div class="np-value" id="npPayAmount">{{$coinAddress->nowpayments_pay_amount}} {{strtoupper($coinAddress->nowpayments_pay_currency)}}</div>
                                        </div>
                                    </div>

                                    <div class="np-row d-flex align-items-center justify-content-between">
                                        <div style="min-width:0;">
                                            <div class="np-label">{{__('Pay To Address')}}</div>
                                            <div class="np-value" id="npPayAddress" style="word-break:break-all;">{{$coinAddress->nowpayments_pay_address}}</div>
                                        </div>
                                        <button type="button" class="np-copy-btn ml-2" data-copy-target="npPayAddress">{{__('Copy')}}</button>
                                    </div>

                                    <div class="np-row d-flex align-items-center justify-content-between">
                                        <div style="min-width:0;">
                                            <div class="np-label">{{__('Payment ID')}}</div>
                                            <div class="np-value" id="npPaymentId" style="word-break:break-all;">{{$coinAddress->nowpayments_payment_id}}</div>
                                        </div>
                                        <button type="button" class="np-copy-btn ml-2" data-copy-target="npPaymentId">{{__('Copy')}}</button>
                                    </div>

                                    <div class="np-row">
                                        <div class="np-label">{{__('OBX to Credit')}}</div>
                                        <div class="np-value">{{number_format($coinAddress->requested_amount, 2)}} OBX</div>
                                    </div>

                                    <div class="np-instruction">
                                        <strong>{{__('Quick Instructions')}}</strong><br>
                                        1. {{__('Send the exact amount shown above to the payment address.')}}<br>
                                        2. {{__('Keep this page open. Status updates automatically every few seconds.')}}<br>
                                        3. {{__('After confirmation, OBX is auto-delivered on-chain and credited to your default OBX/EVM wallet.')}}
                                    </div>

                                    <div id="npStatusNote" class="np-note">{{__('Checking payment status...')}}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @elseif($coinAddress->type == WALLETCONNECT)
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
<script>
(function(){
    @if($coinAddress->type == NOWPAYMENTS)
    var statusUrl = @json(route('buyCoinPaymentStatus', $coinAddress->nowpayments_payment_id ?: $coinAddress->id));
    var pollHandle = null;
    var stopPolling = false;

    function setBadge(statusCode, deliveryStatus) {
        var badge = document.getElementById('npStatusBadge');
        if (!badge) return;

        var cls = 'pending';
        var text = '{{__('Waiting Payment')}}';
        var icon = 'clock-o';

        if (Number(statusCode) === {{ STATUS_SUCCESS }}) {
            cls = 'success';
            text = '{{__('Payment Confirmed / Credited')}}';
            icon = 'check-circle';
            stopPolling = true;
        } else if (Number(statusCode) === {{ STATUS_REJECTED }}) {
            cls = 'rejected';
            text = '{{__('Payment Rejected/Expired')}}';
            icon = 'times-circle';
            stopPolling = true;
        } else if (String(deliveryStatus || '') === 'failed') {
            cls = 'rejected';
            text = '{{__('Delivery Failed (Retrying)')}}';
            icon = 'exclamation-circle';
        }

        badge.className = 'np-status-pill ' + cls;
        badge.innerHTML = '<i class="fa fa-' + icon + '"></i> ' + text;
    }

    function setNote(text) {
        var note = document.getElementById('npStatusNote');
        if (note) note.textContent = text;
    }

    function pollStatus() {
        if (stopPolling) return;
        fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d || d.success !== true) return;

                setBadge(d.status_code, d.obx_delivery_status);

                if (Number(d.status_code) === {{ STATUS_SUCCESS }}) {
                    setNote('{{__('Success: OBX credited to your default OBX/EVM wallet.')}}');
                    return;
                }
                if (Number(d.status_code) === {{ STATUS_REJECTED }}) {
                    setNote('{{__('Payment was rejected, expired, or refunded by gateway.')}}');
                    return;
                }
                if (String(d.obx_delivery_status || '') === 'failed') {
                    var err = String(d.obx_delivery_error || '').trim();
                    if (err !== '') {
                        setNote('{{__('Payment seen, but on-chain OBX delivery failed. Auto-retry will continue.')}} ' + err);
                    } else {
                        setNote('{{__('Payment seen, but on-chain OBX delivery failed. Auto-retry will continue.')}}');
                    }
                    return;
                }
                if (String(d.remote_status || '') !== '') {
                    setNote('{{__('Gateway status')}}: ' + String(d.remote_status).toUpperCase() + '. {{__('Waiting for final confirmation...')}}');
                } else {
                    setNote('{{__('Waiting for payment broadcast and confirmation...')}}');
                }
            })
            .catch(function(){});
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('[data-copy-target]');
        if (!btn) return;
        var id = btn.getAttribute('data-copy-target');
        var el = document.getElementById(id);
        if (!el) return;

        var txt = (el.textContent || '').trim();
        if (!txt) return;

        navigator.clipboard.writeText(txt).then(function(){
            btn.textContent = '{{__('Copied')}}';
            setTimeout(function(){ btn.textContent = '{{__('Copy')}}'; }, 1200);
        }).catch(function(){});
    });

    pollStatus();
    pollHandle = setInterval(function(){
        if (stopPolling) {
            clearInterval(pollHandle);
            return;
        }
        pollStatus();
    }, 12000);
    @endif
})();
</script>
@endsection
