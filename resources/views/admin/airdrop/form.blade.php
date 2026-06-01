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

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="mb-3">{{ __('Airdrop Setup Settings') }}</h5>

                                <form method="POST" action="{{ route('admin.airdrop.settings') }}" class="mb-3">
                                    @csrf
                                    <div class="row align-items-end">
                                        <div class="col-md-4 mt-2">
                                            <div class="custom-control custom-switch mt-2">
                                                <input type="checkbox"
                                                       class="custom-control-input"
                                                       id="airdrop_withdraw_enabled"
                                                       name="airdrop_withdraw_enabled"
                                                       value="1"
                                                       {{ $airdropWithdrawEnabled ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="airdrop_withdraw_enabled">
                                                    {{ __('Withdraw Enabled') }}
                                                </label>
                                            </div>
                                            <small class="text-muted">{{ __('If disabled, users only see daily claim.') }}</small>
                                        </div>

                                        <div class="col-md-4 mt-2">
                                            <label>{{ __('NOWPayments Pay Currency') }}</label>
                                            <input type="text"
                                                   name="airdrop_withdraw_pay_currency"
                                                   class="form-control"
                                                   maxlength="30"
                                                   value="{{ old('airdrop_withdraw_pay_currency', $airdropWithdrawPayCurrency) }}"
                                                   placeholder="usdtbsc"
                                                   required>
                                            <small class="text-muted">{{ __('Example: usdtbsc, usdttrc20, btc') }}</small>
                                        </div>

                                        <div class="col-md-4 mt-2 text-md-right">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa fa-save"></i> {{ __('Save Setup') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

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

                            {{-- Row 3: Personalized claim tiers --}}
                            <div class="row">
                                <div class="col-12 mt-20">
                                    <h6 class="mb-2">{{ __('Personalized Daily Claim, Streak, and Bonus by Total Buy (USD)') }}</h6>
                                    <small class="text-muted">{{ __('These values are configurable per campaign and applied per user purchase tier.') }}</small>
                                </div>

                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('No Purchase (0 USD) - Daily OBX') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_daily_no_purchase_obx" class="form-control" step="any" min="0.000000000000000001"
                                               value="{{ old('claim_daily_no_purchase_obx', $campaign ? (is_numeric($campaign->claim_daily_no_purchase_obx) ? (float)$campaign->claim_daily_no_purchase_obx : 2) : 2) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_daily_no_purchase_obx') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Days') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_no_purchase_days" class="form-control" min="1" max="365"
                                               value="{{ old('claim_streak_no_purchase_days', $campaign->claim_streak_no_purchase_days ?? 15) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_no_purchase_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_bonus_no_purchase_obx" class="form-control" step="any" min="0"
                                               value="{{ old('claim_streak_bonus_no_purchase_obx', $campaign ? (is_numeric($campaign->claim_streak_bonus_no_purchase_obx) ? (float)$campaign->claim_streak_bonus_no_purchase_obx : (float)($campaign->streak_bonus_amount ?? 0)) : 0) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_bonus_no_purchase_obx') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('1 <= Buy <= 50 USD - Daily OBX') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_daily_lt_50_obx" class="form-control" step="any" min="0.000000000000000001"
                                               value="{{ old('claim_daily_lt_50_obx', $campaign ? (is_numeric($campaign->claim_daily_lt_50_obx) ? (float)$campaign->claim_daily_lt_50_obx : 3.5) : 3.5) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_daily_lt_50_obx') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Days') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_lt_50_days" class="form-control" min="1" max="365"
                                               value="{{ old('claim_streak_lt_50_days', $campaign->claim_streak_lt_50_days ?? 20) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_lt_50_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_bonus_lt_50_obx" class="form-control" step="any" min="0"
                                               value="{{ old('claim_streak_bonus_lt_50_obx', $campaign ? (is_numeric($campaign->claim_streak_bonus_lt_50_obx) ? (float)$campaign->claim_streak_bonus_lt_50_obx : (float)($campaign->streak_bonus_amount ?? 0)) : 0) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_bonus_lt_50_obx') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('51 <= Buy <= 100 USD - Daily OBX') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_daily_lt_100_obx" class="form-control" step="any" min="0.000000000000000001"
                                               value="{{ old('claim_daily_lt_100_obx', $campaign ? (is_numeric($campaign->claim_daily_lt_100_obx) ? (float)$campaign->claim_daily_lt_100_obx : 5) : 5) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_daily_lt_100_obx') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Days') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_lt_100_days" class="form-control" min="1" max="365"
                                               value="{{ old('claim_streak_lt_100_days', $campaign->claim_streak_lt_100_days ?? 30) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_lt_100_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_bonus_lt_100_obx" class="form-control" step="any" min="0"
                                               value="{{ old('claim_streak_bonus_lt_100_obx', $campaign ? (is_numeric($campaign->claim_streak_bonus_lt_100_obx) ? (float)$campaign->claim_streak_bonus_lt_100_obx : (float)($campaign->streak_bonus_amount ?? 0)) : 0) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_bonus_lt_100_obx') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('101 <= Buy <= 500 USD - Daily OBX') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_daily_lt_500_obx" class="form-control" step="any" min="0.000000000000000001"
                                               value="{{ old('claim_daily_lt_500_obx', $campaign ? (is_numeric($campaign->claim_daily_lt_500_obx) ? (float)$campaign->claim_daily_lt_500_obx : 10) : 10) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_daily_lt_500_obx') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Days') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_lt_500_days" class="form-control" min="1" max="365"
                                               value="{{ old('claim_streak_lt_500_days', $campaign->claim_streak_lt_500_days ?? 50) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_lt_500_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_bonus_lt_500_obx" class="form-control" step="any" min="0"
                                               value="{{ old('claim_streak_bonus_lt_500_obx', $campaign ? (is_numeric($campaign->claim_streak_bonus_lt_500_obx) ? (float)$campaign->claim_streak_bonus_lt_500_obx : (float)($campaign->streak_bonus_amount ?? 0)) : 0) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_bonus_lt_500_obx') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('501 <= Buy <= 1000 USD - Daily OBX') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_daily_lt_1000_obx" class="form-control" step="any" min="0.000000000000000001"
                                               value="{{ old('claim_daily_lt_1000_obx', $campaign ? (is_numeric($campaign->claim_daily_lt_1000_obx) ? (float)$campaign->claim_daily_lt_1000_obx : 20) : 20) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_daily_lt_1000_obx') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Days') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_lt_1000_days" class="form-control" min="1" max="365"
                                               value="{{ old('claim_streak_lt_1000_days', $campaign->claim_streak_lt_1000_days ?? 80) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_lt_1000_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_bonus_lt_1000_obx" class="form-control" step="any" min="0"
                                               value="{{ old('claim_streak_bonus_lt_1000_obx', $campaign ? (is_numeric($campaign->claim_streak_bonus_lt_1000_obx) ? (float)$campaign->claim_streak_bonus_lt_1000_obx : (float)($campaign->streak_bonus_amount ?? 0)) : 0) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_bonus_lt_1000_obx') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-4 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Buy >= 1001 USD - Daily OBX') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_daily_gte_1000_obx" class="form-control" step="any" min="0.000000000000000001"
                                               value="{{ old('claim_daily_gte_1000_obx', $campaign ? (is_numeric($campaign->claim_daily_gte_1000_obx) ? (float)$campaign->claim_daily_gte_1000_obx : 25) : 25) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_daily_gte_1000_obx') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Days') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_gte_1000_days" class="form-control" min="1" max="365"
                                               value="{{ old('claim_streak_gte_1000_days', $campaign->claim_streak_gte_1000_days ?? 100) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_gte_1000_days') }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-md-2 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Streak Bonus (OBX)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="claim_streak_bonus_gte_1000_obx" class="form-control" step="any" min="0"
                                               value="{{ old('claim_streak_bonus_gte_1000_obx', $campaign ? (is_numeric($campaign->claim_streak_bonus_gte_1000_obx) ? (float)$campaign->claim_streak_bonus_gte_1000_obx : (float)($campaign->streak_bonus_amount ?? 0)) : 0) }}" required>
                                        <span class="text-danger"><strong>{{ $errors->first('claim_streak_bonus_gte_1000_obx') }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 4: Withdrawal fee tiers --}}
                            <div class="row">
                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Fee for Total Buy < $100 (USDT)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="unlock_fee_lt_100_usdt" class="form-control"
                                               min="0.01" step="0.01"
                                               value="{{ old('unlock_fee_lt_100_usdt', $campaign ? number_format((float)($campaign->unlock_fee_lt_100_usdt ?? $campaign->unlock_fee_usdt), 2, '.', '') : '8.00') }}"
                                               required>
                                        <small class="text-muted">{{ __('Applied when user total purchases are below $100.') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('unlock_fee_lt_100_usdt') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Fee for $100 - $499.99 (USDT)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="unlock_fee_lt_500_usdt" class="form-control"
                                               min="0.01" step="0.01"
                                               value="{{ old('unlock_fee_lt_500_usdt', $campaign ? number_format((float)($campaign->unlock_fee_lt_500_usdt ?? $campaign->unlock_fee_usdt), 2, '.', '') : '6.00') }}"
                                               required>
                                        <small class="text-muted">{{ __('Applied when user total purchases are from $100 to $499.99.') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('unlock_fee_lt_500_usdt') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Fee for $500 - $999.99 (USDT)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="unlock_fee_lt_1000_usdt" class="form-control"
                                               min="0.01" step="0.01"
                                               value="{{ old('unlock_fee_lt_1000_usdt', $campaign ? number_format((float)($campaign->unlock_fee_lt_1000_usdt ?? $campaign->unlock_fee_usdt), 2, '.', '') : '4.00') }}"
                                               required>
                                        <small class="text-muted">{{ __('Applied when user total purchases are from $500 to $999.99.') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('unlock_fee_lt_1000_usdt') }}</strong></span>
                                    </div>
                                </div>

                                <div class="col-md-3 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Fee for $1000+ (USDT)') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="unlock_fee_gte_1000_usdt" class="form-control"
                                               min="0.01" step="0.01"
                                               value="{{ old('unlock_fee_gte_1000_usdt', $campaign ? number_format((float)($campaign->unlock_fee_gte_1000_usdt ?? $campaign->unlock_fee_usdt), 2, '.', '') : '2.00') }}"
                                               required>
                                        <small class="text-muted">{{ __('Applied when user total purchases are $1000 and above.') }}</small>
                                        <span class="text-danger"><strong>{{ $errors->first('unlock_fee_gte_1000_usdt') }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 4: Fee visibility controls --}}
                            <div class="row">
                                <div class="col-md-6 mt-20">
                                    <div class="form-group">
                                        <label>{{ __('Fee Visibility') }}</label>
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox" class="custom-control-input" id="fee_revealed"
                                                   name="fee_revealed" value="1"
                                                   {{ old('fee_revealed', $campaign->fee_revealed ?? false) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="fee_revealed">{{ __('Reveal fee to users') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mt-20">
                                    <div class="form-group">
                                        <label class="d-block">{{ __('Quick Action') }}</label>
                                        @php $feeIsVisible = old('fee_revealed', $campaign->fee_revealed ?? false); @endphp
                                        <button type="button"
                                                class="btn {{ $feeIsVisible ? 'btn-warning' : 'btn-success' }} mt-2 js-fee-visibility-toggle"
                                                data-target="fee_revealed"
                                                data-visible-label="{{ __('Hide Fee') }}"
                                                data-hidden-label="{{ __('Reveal Fee') }}">
                                            <i class="fa fa-{{ $feeIsVisible ? 'eye-slash' : 'eye' }}"></i>
                                            <span class="js-fee-visibility-label">{{ $feeIsVisible ? __('Hide Fee') : __('Reveal Fee') }}</span>
                                        </button>
                                        <small class="d-block text-muted mt-2">{{ __('This toggles the fee visibility switch above. Save campaign to apply.') }}</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 5: Contract + Chain + Active --}}
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
                                {{ __('Users see the withdraw button only when global withdraw is enabled and this campaign fee is revealed. The displayed fee is selected from these tiers by the user total buy amount.') }}
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

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('.js-fee-visibility-toggle');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-target');
        var checkbox = targetId ? document.getElementById(targetId) : null;
        if (!checkbox) {
            return;
        }

        checkbox.checked = !checkbox.checked;

        var visibleLabel = btn.getAttribute('data-visible-label') || 'Hide Fee';
        var hiddenLabel = btn.getAttribute('data-hidden-label') || 'Reveal Fee';
        var icon = btn.querySelector('i');
        var label = btn.querySelector('.js-fee-visibility-label');

        if (checkbox.checked) {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-warning');
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
            if (label) {
                label.textContent = visibleLabel;
            }
        } else {
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-success');
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
            if (label) {
                label.textContent = hiddenLabel;
            }
        }
    });
});
</script>
@endsection

