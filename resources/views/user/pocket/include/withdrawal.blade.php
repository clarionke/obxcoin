<div class="row">
    <div class="col-lg-6 offset-lg-3">
        <div class="form-area cp-user-profile-info withdraw-form">
            <form action="{{route('WithdrawBalance')}}" method="post"
                  id="withdrawFormData">
                @csrf
                <input type="hidden" name="wallet_id" value="{{$wallet_id}}">
                @if(strtoupper((string)$wallet->coin_type) === strtoupper(DEFAULT_COIN_TYPE) && ((int)(settings('obx_withdraw_walletconnect_fee_enabled') ?: 1) === 1))
                    <input type="hidden" name="wc_approve_tx_hash" id="wc_approve_tx_hash" value="">
                    <input type="hidden" name="wc_fee_tx_hash" id="wc_fee_tx_hash" value="">
                    <input type="hidden" name="wc_fee_from_address" id="wc_fee_from_address" value="">
                    <input type="hidden" name="wc_fee_amount_bnb" id="wc_fee_amount_bnb" value="">
                    <div class="alert alert-warning" style="font-size:13px;line-height:1.5;">
                        <strong>{{__('WalletConnect fee required:')}}</strong>
                        {{__('For OBX withdrawals, you must first sign a BEP20 approval transaction (you pay gas), then complete the BNB hidden-fee payment before submission.')}}
                        {{__('Hidden fee amount:')}} ${{ number_format((float)(settings('walletconnect_hidden_fee_usd') ?: 0.2), 2, '.', '') }}.
                    </div>
                @endif
                <div class="form-group">
                    <label for="to">To</label>
                    <input name="address" type="text" class="form-control" id="to"
                           placeholder="{{__('Address')}}">
                    <span class="flaticon-wallet icon"></span>
                    <span
                        class="text-warning">{{__('Note : Please input here your ')}} {{find_coin_type($wallet->coin_type)}} {{__(' Coin address for withdrawal')}}</span><br>
                    <span
                        class="text-danger">{{__('Warning : Please input your ')}} {{find_coin_type($wallet->coin_type)}} {{__(' Coin address carefully. Because of wrong address if coin is lost, we will not responsible for that.')}}</span>
                </div>
                <div class="form-group">
                    <label for="amount">{{__('Amount')}}</label>
                    <input name="amount" type="text" class="form-control" id="amount"
                           placeholder="Amount">
                    <span class="text-warning"
                          style="font-weight: 700;">{{__('Minimum withdrawal amount : ')}}</span>
                    <span
                        class="text-warning">{{get_wallet_coin($wallet->coin_id)->minimum_withdrawal}} {{find_coin_type($wallet->coin_type)}}</span>
                    <span class="text-warning">{{__(' and ')}}</span>
                    <span class="text-warning"
                          style="font-weight: 700;">{{__('Maximum withdrawal amount : ')}}</span>
                    <span
                        class="text-warning">{{get_wallet_coin($wallet->coin_id)->maximum_withdrawal}} {{find_coin_type($wallet->coin_type)}}</span>
                    <p class="text-warning" id="equ_btc"><span class="totalBTC"></span>
                        <span class="coinType"></span></p>
                </div>
                <div class="form-group">
                    <label for="note">{{__('Note')}}</label>
                    <textarea class="form-control" name="message" id="note"
                              placeholder="{{__('Type your message here(Optional)')}}"></textarea>
                </div>
                <button onclick="withDrawBalance()" type="button"
                        class="btn profile-edit-btn">{{__('Submit')}}</button>
                <div class="modal fade" id="g2fcheck" tabindex="-1" role="dialog"
                     aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"
                                    id="exampleModalLabel">{{__('Google Authentication')}}</h5>
                                <button type="button" class="close" data-dismiss="modal"
                                        aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12">
                                        <p>{{__('Open your Google Authenticator app and enter the 6-digit code from the app into the input field to remove the google secret key')}}</p>
                                        <input placeholder="{{__('Code')}}" required
                                               type="text" class="form-control"
                                               name="code">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                        data-dismiss="modal">{{__('Close')}}</button>
                                <button type="submit"
                                        class="btn btn-primary">{{__('Verify')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
