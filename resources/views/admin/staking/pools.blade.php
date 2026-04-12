@extends('admin.master',['menu'=>'staking', 'sub_menu'=>'staking_pools'])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{__('Staking')}}</li>
                    <li class="active-item">{{__('Pools')}}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="user-management padding-30">
        <div class="row">
            <!-- add/edit form -->
            <div class="col-lg-4 col-12 mb-4">
                <div class="profile-info-form p-4">
                    <h5 class="mb-3" id="form_heading">{{__('Add Pool')}}</h5>
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif
                    <form action="{{ route('admin.staking.savePool') }}" method="POST">
                        @csrf
                        <input type="hidden" name="id" id="field_id" value="">
                        <div class="form-group mt-2">
                            <label>{{__('Pool Name')}}</label>
                            <input type="text" name="name" id="field_name" class="form-control" placeholder="{{__('e.g. Silver 30-Day')}}"
                                   value="{{ old('name') }}">
                            <span class="text-danger"><strong>{{ $errors->first('name') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('On-chain Pool ID (0-based index)')}}</label>
                            <input type="number" name="pool_id_onchain" id="field_pool_id_onchain" class="form-control" min="0"
                                   value="{{ old('pool_id_onchain', 0) }}">
                            <span class="text-danger"><strong>{{ $errors->first('pool_id_onchain') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('Minimum Stake (OBX)')}}</label>
                            <input type="number" name="min_amount" id="field_min_amount" class="form-control" min="1" step="any"
                                   value="{{ old('min_amount') }}">
                            <span class="text-danger"><strong>{{ $errors->first('min_amount') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('Lock Duration (days)')}}</label>
                            <input type="number" name="duration_days" id="field_duration_days" class="form-control" min="1"
                                   value="{{ old('duration_days') }}">
                            <span class="text-danger"><strong>{{ $errors->first('duration_days') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('APY (basis points, 100 = 1%)')}}</label>
                            <input type="number" name="apy_bps" id="field_apy_bps" class="form-control" min="1" max="100000"
                                   value="{{ old('apy_bps') }}">
                            <span class="text-danger"><strong>{{ $errors->first('apy_bps') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('Burn on Stake (bps, default 100=1%)')}}</label>
                            <input type="number" name="burn_on_stake_bps" id="field_burn_on_stake_bps" class="form-control" min="0" max="1000"
                                   value="{{ old('burn_on_stake_bps', 100) }}">
                            <span class="text-danger"><strong>{{ $errors->first('burn_on_stake_bps') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('Burn on Unstake (bps, default 200=2%)')}}</label>
                            <input type="number" name="burn_on_unstake_bps" id="field_burn_on_unstake_bps" class="form-control" min="0" max="1000"
                                   value="{{ old('burn_on_unstake_bps', 200) }}">
                            <span class="text-danger"><strong>{{ $errors->first('burn_on_unstake_bps') }}</strong></span>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('Description')}}</label>
                            <textarea name="description" id="field_description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>
                        <div class="form-group mt-2">
                            <label>{{__('Status')}}</label>
                            <select name="status" id="field_status" class="form-control">
                                <option value="1">{{__('Active')}}</option>
                                <option value="0">{{__('Inactive')}}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3 w-100">{{__('Save Pool')}}</button>
                        <button type="button" class="btn btn-secondary mt-2 w-100" onclick="resetForm()">{{__('Reset / Add New')}}</button>
                    </form>
                </div>
            </div>

            <!-- pool list -->
            <div class="col-lg-8 col-12">
                <div class="header-bar mb-2">
                    <div class="table-title"><h3>{{__('Pool List')}}</h3></div>
                </div>
                <div class="table-area">
                    <div class="table-responsive">
                        <table class="table table-borderless custom-table display text-center" width="100%">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>{{__('Name')}}</th>
                                <th>{{__('On-chain ID')}}</th>
                                <th>{{__('Min OBX')}}</th>
                                <th>{{__('Duration')}}</th>
                                <th>{{__('APY')}}</th>
                                <th>{{__('Burn In')}}</th>
                                <th>{{__('Burn Out')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Action')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($pools as $pool)
                                <tr>
                                    <td>{{ $pool->id }}</td>
                                    <td>{{ $pool->name }}</td>
                                    <td>{{ $pool->pool_id_onchain }}</td>
                                    <td>{{ number_format($pool->min_amount,0) }}</td>
                                    <td>{{ $pool->duration_days }}d</td>
                                    <td>{{ $pool->apy_percent }}%</td>
                                    <td>{{ $pool->burn_stake_pct }}%</td>
                                    <td>{{ $pool->burn_unstake_pct }}%</td>
                                    <td>
                                        @if($pool->status == 1)
                                            <span class="badge badge-success">{{__('Active')}}</span>
                                        @else
                                            <span class="badge badge-secondary">{{__('Inactive')}}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editPool({{ $pool }})">{{__('Edit')}}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10">{{__('No pools created yet.')}}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
<script>
function resetForm() {
    document.getElementById('form_heading').textContent = '{{__("Add Pool")}}';
    ['id','name','pool_id_onchain','min_amount','duration_days','apy_bps','burn_on_stake_bps','burn_on_unstake_bps','description'].forEach(function(f) {
        var el = document.getElementById('field_'+f);
        if (el) el.value = (f==='burn_on_stake_bps' ? 100 : f==='burn_on_unstake_bps' ? 200 : '');
    });
    document.getElementById('field_status').value = '1';
}
function editPool(pool) {
    document.getElementById('form_heading').textContent = '{{__("Edit Pool")}}';
    document.getElementById('field_id').value              = pool.id;
    document.getElementById('field_name').value            = pool.name;
    document.getElementById('field_pool_id_onchain').value = pool.pool_id_onchain;
    document.getElementById('field_min_amount').value      = pool.min_amount;
    document.getElementById('field_duration_days').value   = pool.duration_days;
    document.getElementById('field_apy_bps').value         = pool.apy_bps;
    document.getElementById('field_burn_on_stake_bps').value  = pool.burn_on_stake_bps;
    document.getElementById('field_burn_on_unstake_bps').value= pool.burn_on_unstake_bps;
    document.getElementById('field_description').value     = pool.description || '';
    document.getElementById('field_status').value          = pool.status;
    window.scrollTo({top:0, behavior:'smooth'});
}
</script>
@endsection
