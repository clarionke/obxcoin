@extends('admin.master',['menu'=>$menu, 'sub_menu'=>$sub_menu])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    {{-- breadcrumb --}}
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li>{{ __('Airdrop Management') }}</li>
                    <li><a href="{{ route('admin.airdrop.index') }}">{{ __('Campaigns') }}</a></li>
                    <li class="active-item">{{ $title }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="user-management">
        <div class="row">
            <div class="col-12">
                <div class="profile-info-form ico-phase">
                    <div class="card-body">

                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST"
                              action="{{ $campaign ? route('admin.airdrop.update', $campaign->id) : route('admin.airdrop.store') }}">
                            @csrf

                            {{-- Row 1: Name + Dates --}}
                            <div class="row">
                                <div class="col-md-6 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Campaign Name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control"
                                               value="{{ old('name', $campaign->name ?? '') }}"
                                               required maxlength="100"
                                               placeholder="{{ __('e.g. Launch Airdrop Wave 1') }}">
                                        <span class="text-danger"><strong>{{ $errors->first('name') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Start Date / Time') }} <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="start_date" class="form-control"
                                               value="{{ old('start_date', $campaign ? $campaign->start_date->format('Y-m-d\TH:i') : '') }}"
                                               required>
                                        <span class="text-danger"><strong>{{ $errors->first('start_date') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('End Date / Time') }} <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="end_date" class="form-control"
                                               value="{{ old('end_date', $campaign ? $campaign->end_date->format('Y-m-d\TH:i') : '') }}"
                                               required>
                                        <span class="text-danger"><strong>{{ $errors->first('end_date') }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 2: Daily amount + Streak --}}
                            <div class="row">
                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Daily Claim Amount (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="daily_claim_amount" class="form-control"
                                               step="any" min="0.000000000000000001"
                                               value="{{ old('daily_claim_amount', $campaign ? (float)$campaign->daily_claim_amount : '') }}"
                                               required placeholder="{{ __('e.g. 100') }}">
                                        <small class="text-muted">{{ __('OBX each user can claim per day') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('daily_claim_amount') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Milestone (days)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="streak_days" class="form-control"
                                               min="1" max="365"
                                               value="{{ old('streak_days', $campaign->streak_days ?? 5) }}"
                                               required placeholder="5">
                                        <small class="text-muted">{{ __('Award bonus every N consecutive days') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('streak_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus Amount (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="streak_bonus_amount" class="form-control"
                                               step="any" min="0"
                                               value="{{ old('streak_bonus_amount', $campaign ? (float)($campaign->streak_bonus_amount ?? 0) : 0) }}"
                                               required placeholder="{{ __('e.g. 500') }}">
                                        <small class="text-muted">{{ __('Bonus OBX awarded at each streak milestone') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('streak_bonus_amount') }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 3: Contract + Chain + Active --}}
                            <div class="row">
                                <div class="col-md-5 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Contract Address (optional)') }}</label>
                                        <input type="text" name="contract_address" class="form-control"
                                               value="{{ old('contract_address', $campaign->contract_address ?? '') }}"
                                               maxlength="42" placeholder="0x...">
                                        <small class="text-muted">{{ __('OBXAirdrop on-chain contract address') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Chain ID (optional)') }}</label>
                                        <input type="number" name="chain_id" class="form-control"
                                               value="{{ old('chain_id', $campaign->chain_id ?? '') }}"
                                               placeholder="56">
                                        <small class="text-muted">56 = BSC &nbsp;|&nbsp; 1 = ETH</small>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Status') }}</label>
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox" class="custom-control-input" id="is_active"
                                                   name="is_active" value="1"
                                                   {{ old('is_active', $campaign->is_active ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_active">{{ __('Active / Enabled') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fa fa-info-circle mr-1"></i>
                                {{ __('The unlock fee is set separately after the campaign ends. Use the "Reveal Fee" action from the campaign list.') }}
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="add-btn theme-btn">
                                    {{ $campaign ? __('Update Campaign') : __('Create Campaign') }}
                                </button>
                                <a href="{{ route('admin.airdrop.index') }}" class="btn btn-secondary ml-2">
                                    {{ __('Cancel') }}
                                </a>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

