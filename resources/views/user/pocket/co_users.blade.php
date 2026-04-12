@extends('user.master',['menu'=>'wallet','sub_menu'=>'my_wallet'])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card cp-user-custom-card cp-user-wallet-card">
                <div class="card-body">
                    <div class="cp-user-card-header-area">
                        <div class="cp-user-title">
                            <h4>{{__('Co Users Of Wallet ')}}({{$wallet->name}})</h4>
                        </div>
                    </div>

                    @if($wallet->user_id == \Illuminate\Support\Facades\Auth::id())
                        <div style="background:rgba(99,102,241,.10);border:1px solid rgba(99,102,241,.25);border-radius:10px;padding:14px 16px;margin-bottom:16px;">
                            <form method="POST" action="{{route('addCoWalletUser', $wallet->id)}}" class="row" style="margin:0;">
                                @csrf
                                <div class="col-md-8" style="padding-left:0;">
                                    <label style="font-size:12px;color:#B4B8D7;">{{__('Add co-user by email')}}</label>
                                    <input type="email" name="email" class="form-control" required placeholder="user@example.com">
                                </div>
                                <div class="col-md-4" style="display:flex;align-items:flex-end;padding-right:0;">
                                    <button type="submit" class="btn" style="width:100%;background:#6366f1;color:#fff;border-radius:8px;">{{__('Add Co-user')}}</button>
                                </div>
                                <div class="col-12" style="padding-left:0;padding-right:0;margin-top:10px;">
                                    <label style="font-size:12px;color:#B4B8D7;display:flex;align-items:center;gap:6px;">
                                        <input type="checkbox" name="can_approve" value="1"> {{__('Assign as signatory approver')}}
                                    </label>
                                </div>
                            </form>
                            <small style="display:block;margin-top:8px;color:#9aa4b2;">{{__('Only wallet creator can add co-users directly.')}}</small>
                        </div>
                    @endif

                    <div class="cp-user-wallet-table table-responsive">
                        <table class="table table-borderless cp-user-custom-table" width="100%">
                            <thead>
                            <tr>
                                <th class="all">{{__('Name')}}</th>
                                <th class="all">{{__('Email')}}</th>
                                <th class="all">{{__('Phone')}}</th>
                                <th class="all">{{__('Can Approve')}}</th>
                                <th class="desktop">{{__('Wallet Imported At')}}</th>
                                @if($wallet->user_id == \Illuminate\Support\Facades\Auth::id())
                                    <th class="all">{{__('Signatory Control')}}</th>
                                @endif
                            </tr>
                            </thead>
                            <tbody>
                            @if(isset($co_users[0]))
                                @foreach($co_users as $co_user)
                                    <tr>
                                        <td>{{ $co_user->user->first_name }} {{ $co_user->user->last_name }}
                                            @if($wallet->user_id == $co_user->user->id)
                                            <span class="badge badge-pill badge-warning">{{__('Creator')}}</span>
                                            @endif
                                        </td>
                                        <td>{{ $co_user->user->email }}</td>
                                        <td>{{ $co_user->user->phone }}</td>
                                        <td>
                                            @if((int)$co_user->can_approve === 1)
                                                <span class="badge badge-pill badge-success">{{__('Yes')}}</span>
                                            @else
                                                <span class="badge badge-pill badge-secondary">{{__('No')}}</span>
                                            @endif
                                        </td>
                                        <td>{{ $co_user->created_at }}</td>
                                        @if($wallet->user_id == \Illuminate\Support\Facades\Auth::id())
                                            <td>
                                                <form method="POST" action="{{route('setCoWalletUserApprover', [$wallet->id, $co_user->id])}}">
                                                    @csrf
                                                    <input type="hidden" name="can_approve" value="{{(int)$co_user->can_approve === 1 ? 0 : 1}}">
                                                    <button type="submit" class="btn btn-sm" style="background:#2f3a67;color:#fff;">
                                                        {{(int)$co_user->can_approve === 1 ? __('Remove Signatory') : __('Assign Signatory')}}
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>

                    @if(isset($signatory_requests[0]))
                        <div class="mt-4">
                            <h5 style="color:#B4B8D7;">{{__('Pending Signatory Change Requests')}}</h5>
                            <div class="cp-user-wallet-table table-responsive">
                                <table class="table table-borderless cp-user-custom-table" width="100%">
                                    <thead>
                                    <tr>
                                        <th>{{__('Target User')}}</th>
                                        <th>{{__('Requested Permission')}}</th>
                                        <th>{{__('Status')}}</th>
                                        <th>{{__('Action')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($signatory_requests as $requestItem)
                                        <tr>
                                            <td>{{__('User ID')}}: {{$requestItem->target_user_id}}</td>
                                            <td>
                                                @if((int)$requestItem->requested_can_approve === 1)
                                                    {{__('Grant Signatory')}}
                                                @else
                                                    {{__('Revoke Signatory')}}
                                                @endif
                                            </td>
                                            <td>{{__('Pending')}}</td>
                                            <td>
                                                @if(!empty($is_approver) && $is_approver)
                                                    <form method="POST" action="{{route('approveCoWalletSignatoryChange', $requestItem->id)}}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm" style="background:#0d7c4a;color:#fff;">{{__('Approve')}}</button>
                                                    </form>
                                                @else
                                                    <span class="badge badge-pill badge-warning">{{__('Signatory only')}}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
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
