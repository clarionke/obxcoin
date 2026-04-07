<div class="header-bar">
    <div class="table-title">
        <h3>{{__('Some other settings')}}</h3>
    </div>
</div>
<div class="profile-info-form">
    <form action="{{route('adminCommonSettings')}}" method="post"
          enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('Admin send default coin minimum')}}</label>
                    <input class="form-control" type="text" name="admin_send_default_minimum"
                           autocomplete="off" value="{{settings('admin_send_default_minimum') ?? 1}}">
                </div>
            </div>
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('Admin send default coin maximum')}}</label>
                    <input class="form-control" type="text" name="admin_send_default_maximum"
                           autocomplete="off" value="{{settings('admin_send_default_maximum') ?? 1}}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-2 col-12 mt-20">
                <button type="submit" class="button-primary theme-btn">{{__('Update')}}</button>
            </div>
        </div>
    </form>
</div>
