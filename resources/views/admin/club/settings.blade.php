@extends('admin.master',['menu'=>'club', 'sub_menu'=>'settings'])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    <!-- breadcrumb -->
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{__('Membership Management')}}</li>
                    <li class="active-item">{{ $title }}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- /breadcrumb -->

    <!-- User Management -->
    <div class="user-management">
        <div class="row">
            <div class="col-12">
                <div class="profile-info-form p-4">
                    <form action="{{route('adminLandingSettingSave')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mt-20">
                                <div class="form-group">
                                    <label for="firstname">{{__('Membership Plan Minimum Amount')}}</label>
                                    <input type="text" name="plan_minimum_amount" class="form-control"
                                           @if(isset($settings['plan_minimum_amount'])) value="{{$settings['plan_minimum_amount']}}" @else value="{{old('plan_minimum_amount')}}" @endif>
                                    <span class="text-danger"><strong>{{ $errors->first('plan_minimum_amount') }}</strong></span>
                                </div>
                            </div>
                            <div class="col-md-6 mt-20">
                                <div class="form-group">
                                    <label for="firstname">{{__('Membership Plan Maximum Amount')}}</label>
                                    <input type="text" name="plan_maximum_amount" class="form-control"
                                           @if(isset($settings['plan_maximum_amount'])) value="{{$settings['plan_maximum_amount']}}" @else value="{{old('plan_maximum_amount')}}" @endif>
                                    <span class="text-danger"><strong>{{ $errors->first('plan_maximum_amount') }}</strong></span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <button class="btn ">{{__('Update')}}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /User Management -->

@endsection

@section('script')
    <script>

    </script>
@endsection
