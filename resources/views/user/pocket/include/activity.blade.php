<style>
    .wallet-activity-card {
        background: #161b22;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        overflow: hidden;
    }
    .wallet-activity-head {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .wallet-activity-head h4 {
        margin: 0;
        font-size: 15px;
        color: #e6edf3;
        font-weight: 700;
    }
    .wallet-activity-tabs {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .09);
        border-radius: 8px;
        padding: 3px;
    }
    .wallet-activity-tabs .nav-link {
        border: none;
        border-radius: 6px;
        color: #98a4bf;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 11px;
    }
    .wallet-activity-tabs .nav-link.active,
    .wallet-activity-tabs .nav-link:hover {
        background: rgba(99, 102, 241, .16);
        color: #c7ceff;
    }
    .wallet-activity-body {
        padding: 12px;
    }
    .wallet-activity-table {
        background: #0f1520;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 10px;
        overflow: hidden;
    }
    .wallet-activity-table .table {
        margin-bottom: 0;
    }
    .wallet-activity-table .table tbody tr {
        transition: background .15s ease;
    }
    .wallet-activity-table .table tbody tr:hover {
        background: rgba(99, 102, 241, .08);
    }
    .wallet-activity-table .table thead th {
        border-bottom: 1px solid rgba(255, 255, 255, .1);
        color: #b3bdd9;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .45px;
    }
    .wallet-activity-table .table td {
        color: #d9e0f6;
        border-top: 1px solid rgba(255, 255, 255, .04);
        font-size: 12.5px;
    }
    .wallet-activity-table .table a {
        color: #a8b3ff;
        font-weight: 600;
        text-decoration: none;
    }
    .wallet-activity-table .table a:hover {
        color: #d7dcff;
        text-decoration: underline;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 999px;
        border: 1px solid rgba(16, 185, 129, .32);
        background: rgba(16, 185, 129, .14);
        color: #6ee7b7;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .wallet-activity-actions {
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 10px;
    }
    .wallet-activity-actions li {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .wallet-activity-actions a,
    .wallet-activity-actions button {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, .12);
        background: rgba(255, 255, 255, .03);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .wallet-activity-actions a:hover,
    .wallet-activity-actions button:hover {
        border-color: rgba(99, 102, 241, .4);
        background: rgba(99, 102, 241, .2);
    }
    .wallet-activity-actions img {
        width: 16px;
        opacity: .9;
    }

    @media (max-width: 767px) {
        .wallet-activity-head {
            align-items: flex-start;
        }
        .wallet-activity-tabs {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .wallet-activity-tabs .nav {
            flex-wrap: nowrap;
            min-width: max-content;
        }
        .wallet-activity-body {
            padding: 10px;
        }
        .wallet-activity-table {
            background: transparent;
            border: none;
        }
        .wallet-activity-table .table thead {
            display: none;
        }
        .wallet-activity-table .table,
        .wallet-activity-table .table tbody,
        .wallet-activity-table .table tr,
        .wallet-activity-table .table td {
            display: block;
            width: 100%;
        }
        .wallet-activity-table .table tbody tr {
            background: #0f1520;
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 10px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .wallet-activity-table .table tbody tr.no-data-row {
            padding: 14px 10px;
            text-align: center;
        }
        .wallet-activity-table .table tbody tr.no-data-row td {
            text-align: center;
            padding: 0;
        }
        .wallet-activity-table .table td {
            border: none;
            border-bottom: 1px dashed rgba(255, 255, 255, .08);
            padding: 8px 0;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            text-align: right;
            word-break: break-word;
        }
        .wallet-activity-table .table td:last-child {
            border-bottom: none;
        }
        .wallet-activity-table .table td:before {
            content: attr(data-label);
            color: #9eabca;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            text-align: left;
            min-width: 110px;
        }
        .wallet-activity-actions {
            justify-content: flex-end !important;
        }
    }
</style>

<div class="row">
    <div class="col-lg-12">
        <div class="wallet-activity-card">
            <div class="wallet-activity-head">
                <h4 id="list_title">{{__('All Deposit List')}}</h4>
                <div class="wallet-activity-tabs">
                    <ul class="nav mb-0">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" onclick="$('#list_title').html('All Deposit List')" data-title="" href="#Deposit">{{__('Deposit')}}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if(isset($ac_tab) && $ac_tab == 'withdraw') active @endif" data-toggle="tab" onclick="$('#list_title').html('All Withdrawal List')" href="#Withdraw">{{__('Withdraw')}}</a>
                        </li>
                        @if(co_wallet_feature_active() && $wallet->type == CO_WALLET)
                            <li class="nav-item">
                                <a class="nav-link @if(isset($ac_tab) && $ac_tab == 'co-withdraw') active @endif" data-toggle="tab" onclick="$('#list_title').html('Pending Team Wallet Withdrawals')" href="#co-withdraw">{{__('Pending Team Wallet Withdraw')}}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="wallet-activity-body">
                <div class="tab-content">
                    <div id="Deposit"
                         class="tab-pane fade show active ">

                        <div class="wallet-activity-table table-responsive">
                            <table class="table activity-table">
                                <thead>
                                <tr>
                                    <th>{{__('Address')}}</th>
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
                                            <td data-label="{{__('Address')}}">{{$history->address}}</td>
                                            <td data-label="{{__('Amount')}}">{{$history->amount}}</td>
                                            <td data-label="{{__('Transaction Hash')}}">
                                                @php
                                                    $depositTx = (string) ($history->transaction_id ?? '');
                                                    $depositTxUrl = !empty($depositTx) ? explorer_tx_url($depositTx) : null;
                                                    $depositTxMasked = strlen($depositTx) > 18 ? substr($depositTx, 0, 8) . '...' . substr($depositTx, -8) : $depositTx;
                                                @endphp
                                                @if($depositTxUrl)
                                                    <a href="{{ $depositTxUrl }}" target="_blank" rel="noopener noreferrer" title="{{$depositTx}}">
                                                        {{$depositTxMasked}}
                                                    </a>
                                                @else
                                                    {{$depositTxMasked}}
                                                @endif
                                            </td>
                                            <td data-label="{{__('Status')}}"><span class="status-pill">{{deposit_status($history->status)}}</span></td>
                                            <td data-label="{{__('Created At')}}">{{$history->created_at}}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr class="no-data-row">
                                        <td colspan="5"
                                            class="text-center">{{__('No data available')}}</td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="Withdraw"
                         class="tab-pane fade @if(isset($ac_tab) && $ac_tab == 'withdraw') show active @endif ">

                        <div class="wallet-activity-table table-responsive">
                            <table class="table activity-table">
                                <thead>
                                <tr>
                                    <th>{{__('Address')}}</th>
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
                                            <td data-label="{{__('Address')}}">{{$withdraw->address}}</td>
                                            <td data-label="{{__('Amount')}}">{{$withdraw->amount}}</td>
                                            <td data-label="{{__('Transaction Hash')}}">
                                                @php
                                                    $withdrawTx = (string) ($withdraw->transaction_hash ?? '');
                                                    $withdrawTxUrl = !empty($withdrawTx) ? explorer_tx_url($withdrawTx) : null;
                                                    $withdrawTxMasked = strlen($withdrawTx) > 18 ? substr($withdrawTx, 0, 8) . '...' . substr($withdrawTx, -8) : $withdrawTx;
                                                @endphp
                                                @if($withdrawTxUrl)
                                                    <a href="{{ $withdrawTxUrl }}" target="_blank" rel="noopener noreferrer" title="{{$withdrawTx}}">
                                                        {{$withdrawTxMasked}}
                                                    </a>
                                                @else
                                                    {{$withdrawTxMasked}}
                                                @endif
                                            </td>
                                            <td data-label="{{__('Status')}}"><span class="status-pill">{{deposit_status($withdraw->status)}}</span></td>
                                            <td data-label="{{__('Created At')}}">{{$withdraw->created_at}}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr class="no-data-row">
                                        <td colspan="5"
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

                            <div class="wallet-activity-table table-responsive">
                                <table class="table activity-table">
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
                                                <td data-label="{{__('Address')}}">{{$withdraw->address}}</td>
                                                <td data-label="{{__('Amount')}}">{{$withdraw->amount}}</td>
                                                <td data-label="{{__('Status')}}"><span class="status-pill">{{__('Need co users approval')}}</span></td>
                                                <td data-label="{{__('Created At')}}">{{$withdraw->created_at}}</td>
                                                <td data-label="{{__('Actions')}}">
                                                    <ul class="wallet-activity-actions d-flex justify-content-center align-items-center">
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
                                                                        <img style="width: 16px; opacity: 0.85"
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
                                        <tr class="no-data-row">
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
