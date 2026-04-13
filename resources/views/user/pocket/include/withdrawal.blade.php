<style>
    .wallet-withdraw-card {
        background: #161b22;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        padding: 18px 20px;
    }
    .wallet-withdraw-head h5 {
        margin: 0;
        color: #e6edf3;
        font-size: 16px;
        font-weight: 700;
    }
    .wallet-withdraw-head p {
        margin: 5px 0 14px;
        color: #8b95a7;
        font-size: 12px;
    }
    .wallet-withdraw-card .form-group label {
        color: #d7dfef;
        font-weight: 600;
        font-size: 12.5px;
    }
    .wallet-withdraw-card .form-control {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .14);
        color: #e6edf3;
        border-radius: 8px;
    }
    .wallet-withdraw-card .form-control:focus {
        border-color: rgba(99, 102, 241, .7);
        box-shadow: 0 0 0 .12rem rgba(99, 102, 241, .18);
    }
    .withdraw-hint {
        background: rgba(245, 158, 11, .12);
        border: 1px solid rgba(245, 158, 11, .3);
        color: #fbbf24;
        border-radius: 8px;
        padding: 8px 10px;
        margin-top: 8px;
        font-size: 12px;
    }
    .withdraw-warning {
        background: rgba(239, 68, 68, .13);
        border: 1px solid rgba(239, 68, 68, .33);
        color: #fca5a5;
        border-radius: 8px;
        padding: 8px 10px;
        margin-top: 8px;
        font-size: 12px;
    }
    .withdraw-limits {
        margin-top: 8px;
        color: #fbbf24;
        font-size: 12px;
    }
    .withdraw-limits strong { color: #fcd34d; }
    .withdraw-submit-btn {
        border: none;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #fff;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        padding: 10px 18px;
        margin-top: 4px;
    }
    #g2fcheck .modal-content {
        background: #161b22;
        border: 1px solid rgba(255, 255, 255, .12);
    }
    #g2fcheck .modal-title,
    #g2fcheck .modal-body p,
    #g2fcheck .close {
        color: #e6edf3;
    }
</style>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="wallet-withdraw-card">
            <div class="wallet-withdraw-head">
                <h5>{{__('Withdraw Funds')}}</h5>
                <p>{{__('Transfer your tokens securely to an external address.')}}</p>
            </div>

            <form action="{{route('WithdrawBalance')}}" method="post" id="withdrawFormData">
                @csrf
                <input type="hidden" name="wallet_id" value="{{$wallet_id}}">

                <div class="form-group">
                    <label for="to">{{__('To')}}</label>
                    <input name="address" type="text" class="form-control" id="to" placeholder="{{__('Address')}}">
                    <div class="withdraw-hint">
                        {{__('Note : Please input here your ')}} {{find_coin_type($wallet->coin_type)}} {{__(' Coin address for withdrawal')}}
                    </div>
                    <div class="withdraw-warning">
                        {{__('Warning : Please input your ')}} {{find_coin_type($wallet->coin_type)}} {{__(' Coin address carefully. Because of wrong address if coin is lost, we will not responsible for that.')}}
                    </div>
                </div>

                <div class="form-group">
                    <label for="amount">{{__('Amount')}}</label>
                    <input name="amount" type="text" class="form-control" id="amount" placeholder="{{__('Amount')}}">
                    <div class="withdraw-limits">
                        <strong>{{__('Minimum withdrawal amount : ')}}</strong>
                        {{get_wallet_coin($wallet->coin_id)->minimum_withdrawal}} {{find_coin_type($wallet->coin_type)}}
                        {{__(' and ')}}
                        <strong>{{__('Maximum withdrawal amount : ')}}</strong>
                        {{get_wallet_coin($wallet->coin_id)->maximum_withdrawal}} {{find_coin_type($wallet->coin_type)}}
                    </div>
                    <p class="withdraw-limits mb-0" id="equ_btc"><span class="totalBTC"></span> <span class="coinType"></span></p>
                </div>

                <div class="form-group">
                    <label for="note">{{__('Note')}}</label>
                    <textarea class="form-control" name="message" id="note" placeholder="{{__('Type your message here(Optional)')}}"></textarea>
                </div>

                <button onclick="withDrawBalance()" type="button" class="withdraw-submit-btn">{{__('Submit')}}</button>

                <div class="modal fade" id="g2fcheck" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLabel">{{__('Google Authentication')}}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12">
                                        <p>{{__('Open your Google Authenticator app and enter the 6-digit code from the app into the input field to remove the google secret key')}}</p>
                                        <input placeholder="{{__('Code')}}" required type="text" class="form-control" name="code">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Close')}}</button>
                                <button type="submit" class="btn btn-primary">{{__('Verify')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
