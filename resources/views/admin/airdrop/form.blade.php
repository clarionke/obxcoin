@extends('admin.master')
@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="mb-0">{{ $title }}</h4>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('adminDashboard') }}">{{ __('Home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.airdrop.index') }}">{{ __('Airdrop') }}</a></li>
                <li class="breadcrumb-item active">{{ $campaign ? __('Edit') : __('Create') }}</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><b>{{ $title }}</b></div>
            <div class="card-body">
                <form method="POST"
                      action="{{ $campaign ? route('admin.airdrop.update', $campaign->id) : route('admin.airdrop.store') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ __('Campaign Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                       value="{{ old('name', $campaign->name ?? '') }}"
                                       required maxlength="100" placeholder="{{ __('e.g. Launch Airdrop Wave 1') }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ __('Start Date / Time') }} <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="start_date" class="form-control"
                                       value="{{ old('start_date', $campaign ? $campaign->start_date->format('Y-m-d\TH:i') : '') }}"
                                       required>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ __('End Date / Time') }} <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="end_date" class="form-control"
                                       value="{{ old('end_date', $campaign ? $campaign->end_date->format('Y-m-d\TH:i') : '') }}"
                                       required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>{{ __('Daily Claim Amount (OBX per user)') }} <span class="text-danger">*</span></label>
                                <input type="number" name="daily_claim_amount" class="form-control"
                                       step="any" min="0.000000000000000001"
                                       value="{{ old('daily_claim_amount', $campaign ? (float)$campaign->daily_claim_amount : '') }}"
                                       required placeholder="{{ __('e.g. 100') }}">
                                <small class="text-muted">{{ __('OBX each user can claim per day') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>{{ __('Contract Address (optional)') }}</label>
                                <input type="text" name="contract_address" class="form-control"
                                       value="{{ old('contract_address', $campaign->contract_address ?? '') }}"
                                       maxlength="42" placeholder="0x...">
                                <small class="text-muted">{{ __('OBXAirdrop contract on-chain address') }}</small>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ __('Chain ID (optional)') }}</label>
                                <input type="number" name="chain_id" class="form-control"
                                       value="{{ old('chain_id', $campaign->chain_id ?? '') }}"
                                       placeholder="{{ __('e.g. 56') }}">
                                <small class="text-muted">56 = BSC, 1 = ETH</small>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ __('Active') }}</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="is_active"
                                           name="is_active" value="1"
                                           {{ old('is_active', $campaign->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">{{ __('Enabled') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-2">
                        <i class="fa fa-info-circle"></i>
                        {{ __('The unlock fee is NOT set here. After the campaign ends, go to the campaign list and click the unlock icon to reveal the fee to users.') }}
                    </div>

                    <button type="submit" class="btn btn-primary">
                        {{ $campaign ? __('Update Campaign') : __('Create Campaign') }}
                    </button>
                    <a href="{{ route('admin.airdrop.index') }}" class="btn btn-secondary ml-2">{{ __('Cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
