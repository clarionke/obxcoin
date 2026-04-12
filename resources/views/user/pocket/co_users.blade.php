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
                                <th class="desktop">{{__('Wallet Imported At')}}</th>
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
                                        <td>{{ $co_user->created_at }}</td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')

@endsection
