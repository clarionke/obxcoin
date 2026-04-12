@extends('admin.master',['menu'=>'wallet'])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    <!-- breadcrumb -->
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{__('Wallet Management')}}</li>
                    <li class="active-item">{{__('Co Wallet')}}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- /breadcrumb -->

    <!-- User Management -->
    <div class="user-management">
        <div class="row">
            <div class="col-12">
                <div class="card-body">
                    <div class="header-bar">
                        <div class="table-title">
                            {{__('Co Users Of Wallet ')}}({{$wallet->name}})
                        </div>
                    </div>
                    <div class="table-area">
                        <div class="table-responsive">
                            <table id="table" class="table table-borderless custom-table display text-center" width="100%">
                                <thead>
                                <tr>
                                    <th class="all">{{__('Name')}}</th>
                                    <th class="all">{{__('Email')}}</th>
                                    <th class="all">{{__('Phone')}}</th>
                                    <th class="all">{{__('Can Approve')}}</th>
                                    <th class="desktop">{{__('Wallet Imported At')}}</th>
                                    <th class="all">{{__('Request Signatory Change')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(isset($co_users[0]))
                                    @foreach($co_users as $co_user)
                                        <tr>
                                            <td>{{ $co_user->user->first_name }} {{ $co_user->user->last_name }}
                                                @if($wallet->user_id == $co_user->user->id)
                                                    <span
                                                        class="badge badge-pill badge-warning">{{__('Creator')}}</span>
                                                @endif
                                            </td>
                                            <td>{{ $co_user->user->email }}</td>
                                            <td>{{ $co_user->user->phone }}</td>
                                            <td>
                                                @if((int)$co_user->can_approve === 1)
                                                    <span class="badge badge-success">{{__('Yes')}}</span>
                                                @else
                                                    <span class="badge badge-secondary">{{__('No')}}</span>
                                                @endif
                                            </td>
                                            <td>{{ $co_user->created_at }}</td>
                                            <td>
                                                <form method="POST" action="{{route('adminRequestCoWalletSignatoryChange', $wallet->id)}}">
                                                    @csrf
                                                    <input type="hidden" name="wallet_co_user_id" value="{{$co_user->id}}">
                                                    <input type="hidden" name="requested_can_approve" value="{{(int)$co_user->can_approve === 1 ? 0 : 1}}">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        {{(int)$co_user->can_approve === 1 ? __('Request Revoke') : __('Request Grant')}}
                                                    </button>
                                                </form>
                                            </td>
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
    </div>
@endsection

@section('script')
<script>
    $(".custom-table").dataTable();
</script>
@endsection
