@php
    $actChainId = (int) (settings('chain_id') ?: settings('presale_chain_id') ?: 56);
    $actBscscanBase = ($actChainId === 97) ? 'https://testnet.bscscan.com' : 'https://bscscan.com';
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="activity-area">
            <div class="activity-top-area">
                <div class="cp-user-card-header-area">
                    <div class="title">
                        <h4 id="list_title">{{__('All Deposit List')}}</h4>
                    </div>
                    <div class="deposite-tabs cp-user-deposit-card">
                        <div class="activity-right text-right">
                            <ul class="nav cp-user-profile-nav mb-0">
                                <li class="nav-item">
                                    <a class="nav-link  active "
                                       data-toggle="tab"
                                       onclick="$('#list_title').html('All Deposit List')"
                                       data-title=""
                                       href="#Deposit">{{__('Deposit')}}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link @if(isset($ac_tab) && $ac_tab == 'withdraw') active @endif"
                                       data-toggle="tab"
                                       onclick="$('#list_title').html('All Withdrawal List')"
                                       href="#Withdraw">{{__('Withdraw')}}</a>
                                </li>
                                @if(co_wallet_feature_active() && $wallet->type == CO_WALLET)
                                    <li class="nav-item">
                                        <a class="nav-link @if(isset($ac_tab) && $ac_tab == 'co-withdraw') active @endif"
                                           data-toggle="tab"
                                           onclick="$('#list_title').html('Pending Team XPocket Withdrawals')"
                                           href="#co-withdraw">{{__('Pending Team XPocket Withdraw')}}</a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="activity-list">
                <div class="tab-content">
                    <div id="Deposit"
                         class="tab-pane fade show active ">

                        <div class="cp-user-wallet-table table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>{{__('Sender')}}</th>
                                    <th>{{__('Destination')}}</th>
                                    <th>{{__('Amount')}}</th>
                                    <th>{{__('Transaction Hash')}}</th>
                                    <th>{{__('Status')}}</th>
                                    <th>{{__('Created At')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(isset($histories[0]))
                                    @foreach($histories as $history)
                                        <tr>
                                            <td style="word-break:break-all;">{{ $history->from_address ?: __('N/A') }}</td>
                                            <td style="word-break:break-all;">{{ $history->address }}</td>
                                            <td>{{ number_format((float)$history->amount, 2, '.', '') }}</td>
                                            <td>
                                                @if(!empty($history->transaction_id) && str_starts_with($history->transaction_id, '0x'))
                                                    <a href="{{$actBscscanBase}}/tx/{{$history->transaction_id}}" target="_blank" rel="noopener noreferrer" title="{{$history->transaction_id}}">
                                                        {{substr($history->transaction_id, 0, 10)}}…{{substr($history->transaction_id, -6)}}
                                                        <i class="fa fa-external-link-alt" style="font-size:11px;"></i>
                                                    </a>
                                                @else
                                                    {{$history->transaction_id ?: __('Pending')}}
                                                @endif
                                            </td>
                                            <td>{{deposit_status($history->status)}}</td>
                                            <td>{{$history->created_at}}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6"
                                            class="text-center">{{__('No data available')}}</td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="Withdraw"
                         class="tab-pane fade @if(isset($ac_tab) && $ac_tab == 'withdraw') show active @endif ">

                        <div class="cp-user-wallet-table table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>{{__('Sender')}}</th>
                                    <th>{{__('Destination')}}</th>
                                    <th>{{__('Amount')}}</th>
                                    <th>{{__('Transaction Hash')}}</th>
                                    <th>{{__('Status')}}</th>
                                    <th>{{__('Created At')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(isset($withdraws[0]))
                                    @foreach($withdraws as $withdraw)
                                        <tr>
                                            <td style="word-break:break-all;">{{ $default_wallet_sender_address ?: __('N/A') }}</td>
                                            <td style="word-break:break-all;">{{$withdraw->address}}</td>
                                            <td>{{ number_format((float)$withdraw->amount, 2, '.', '') }}</td>
                                            <td>
                                                @if(!empty($withdraw->transaction_hash) && str_starts_with($withdraw->transaction_hash, '0x'))
                                                    <a href="{{$actBscscanBase}}/tx/{{$withdraw->transaction_hash}}" target="_blank" rel="noopener noreferrer" title="{{$withdraw->transaction_hash}}">
                                                        {{substr($withdraw->transaction_hash, 0, 10)}}…{{substr($withdraw->transaction_hash, -6)}}
                                                        <i class="fa fa-external-link-alt" style="font-size:11px;"></i>
                                                    </a>
                                                @else
                                                    {{$withdraw->transaction_hash ?: __('Processing…')}}
                                                @endif
                                            </td>
                                            <td>{{deposit_status($withdraw->status)}}</td>
                                            <td>{{$withdraw->created_at}}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6"
                                            class="text-center">{{__('No data available')}}</td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if(co_wallet_feature_active() && $wallet->type == CO_WALLET)
                        <div id="co-withdraw"
                             class="tab-pane fade @if(isset($ac_tab) && $ac_tab == 'co-withdraw') show active @endif">

                            <div class="cp-user-wallet-table table-responsive">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th>{{__('Address')}}</th>
                                        <th>{{__('Amount')}}</th>
                                        <th>{{__('Status')}}</th>
                                        <th>{{__('Created At')}}</th>
                                        <th>{{__('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(isset($tempWithdraws[0]))
                                        @foreach($tempWithdraws as $withdraw)
                                            <tr>
                                                <td>{{$withdraw->address}}</td>
                                                <td>{{ number_format((float)$withdraw->amount, 2, '.', '') }}</td>
                                                <td>{{__('Need co users approval')}}</td>
                                                <td>{{$withdraw->created_at}}</td>
                                                <td>
                                                    <ul class="d-flex justify-content-center align-items-center">
                                                        <li>
                                                            <a title="{{__('Approvals')}}"
                                                               href="{{route('coWalletApprovals', $withdraw->id)}}">
                                                                <img
                                                                    src="{{asset('assets/user/images/wallet-table-icons/send.svg')}}"
                                                                    class="img-fluid" alt="">
                                                            </a>
                                                        </li>
                                                        @if($withdraw->user_id == \Illuminate\Support\Facades\Auth::id())
                                                            <li>
                                                                <form method="POST" action="{{route('rejectCoWalletWithdraw', $withdraw->id)}}" onsubmit="return confirm('{{__('Do you really want to reject?')}}');">
                                                                    @csrf
                                                                    <button type="submit" style="background:none;border:none;padding:0;">
                                                                        <img style="width: 25px; opacity: 0.7"
                                                                             src="{{asset('assets/user/images/close.png')}}"
                                                                             class="img-fluid"
                                                                             alt="">
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="5"
                                                class="text-center">{{__('No data available')}}</td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
