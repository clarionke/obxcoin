@extends('admin.master',['menu'=>$menu, 'sub_menu'=>$sub_menu])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
    .airdrop-stats-wrap .stats-card {
        border: 1px solid rgba(51, 65, 85, 0.35);
        border-radius: 12px;
        background: linear-gradient(160deg, #0f172a 0%, #111827 100%);
        color: #e2e8f0;
        height: 100%;
    }

    .airdrop-stats-wrap .stats-card .card-body {
        padding: 16px 16px;
    }

    .airdrop-stats-wrap .stats-card .label {
        display: block;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #93a4c9;
        font-size: 10.5px;
        margin-bottom: 3px;
        font-weight: 600;
    }

    .airdrop-stats-wrap .stats-card .value {
        font-size: 21px;
        line-height: 1.2;
        font-weight: 700;
        color: #f1f5f9;
    }

    .airdrop-stats-wrap .stats-info {
        border-radius: 12px;
        border: 1px solid rgba(51, 65, 85, 0.35);
        background: linear-gradient(130deg, #12203f 0%, #172554 100%);
        color: #dbeafe;
        padding: 16px 18px;
    }

    .airdrop-stats-wrap .stats-info h4 {
        margin: 0 0 5px;
        color: #f8fafc;
        font-size: 17px;
        font-weight: 700;
    }

    .airdrop-stats-wrap .stats-info p {
        margin: 0;
        color: #bfdbfe;
        font-size: 12px;
    }

    .airdrop-stats-wrap .stats-table-card {
        border-radius: 12px;
        border: 1px solid rgba(51, 65, 85, 0.35);
        background: linear-gradient(160deg, #0f172a 0%, #111827 100%);
    }

    .airdrop-stats-wrap .stats-table-card .card-body {
        padding: 16px;
    }

    .airdrop-stats-wrap .stats-filter-card {
        border-radius: 12px;
        border: 1px solid rgba(51, 65, 85, 0.35);
        background: linear-gradient(160deg, #0f172a 0%, #111827 100%);
    }

    .airdrop-stats-wrap .stats-filter-card .card-body {
        padding: 16px;
    }

    .airdrop-stats-wrap .stats-filter-label {
        color: #9fb0d1;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .6px;
        margin-bottom: 6px;
        display: block;
        font-weight: 600;
    }

    .airdrop-stats-wrap .stats-range-note {
        color: #93a4c9;
        font-size: 11.5px;
        margin-top: 8px;
    }

    .airdrop-stats-wrap .table thead th {
        border: 0;
        color: #9fb0d1;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .6px;
    }

    .airdrop-stats-wrap .table tbody td {
        color: #d5def3;
        border-top: 1px solid rgba(148, 163, 184, .15);
        vertical-align: middle;
    }

    .airdrop-stats-wrap .table tbody tr:hover {
        background: rgba(59, 130, 246, 0.08);
    }
</style>
@endsection

@section('content')
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-9">
                <ul>
                    <li>{{ __('Airdrop Management') }}</li>
                    <li><a href="{{ route('admin.airdrop.index') }}">{{ __('Campaigns') }}</a></li>
                    <li class="active-item">{{ __('Stats') }}</li>
                </ul>
            </div>
            <div class="col-sm-3 text-right">
                <a class="add-btn theme-btn" href="{{ route('admin.airdrop.index') }}">
                    <i class="fa fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>

    <div class="airdrop-stats-wrap">
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-info">
                    <h4>{{ $campaign->name }} — {{ __('Airdrop Stats') }}</h4>
                    <p>
                        {{ $campaign->start_date->format('Y-m-d H:i') }} → {{ $campaign->end_date->format('Y-m-d H:i') }}
                        &nbsp;&bull;&nbsp; {{ __('Daily Claim') }}: {{ number_format((float)$campaign->daily_claim_amount, 4) }} OBX
                        &nbsp;&bull;&nbsp; {{ __('Streak') }}: {{ (int) ($campaign->streak_days ?? 0) }} {{ __('days') }}
                        &nbsp;&bull;&nbsp; {{ __('Range') }}: {{ $rangeLabel ?? __('Lifetime') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-filter-card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.airdrop.stats', $campaign->id) }}">
                            <div class="form-row align-items-end">
                                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                                    <label class="stats-filter-label" for="statsRange">{{ __('Date Range') }}</label>
                                    <select class="form-control" id="statsRange" name="range">
                                        <option value="all" {{ ($selectedRange ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('Lifetime') }}</option>
                                        <option value="today" {{ ($selectedRange ?? '') === 'today' ? 'selected' : '' }}>{{ __('Today') }}</option>
                                        <option value="7d" {{ ($selectedRange ?? '') === '7d' ? 'selected' : '' }}>{{ __('Last 7 Days') }}</option>
                                        <option value="30d" {{ ($selectedRange ?? '') === '30d' ? 'selected' : '' }}>{{ __('Last 30 Days') }}</option>
                                        <option value="custom" {{ ($selectedRange ?? '') === 'custom' ? 'selected' : '' }}>{{ __('Custom') }}</option>
                                    </select>
                                </div>

                                <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                                    <div id="customRangeFields" class="form-row {{ ($selectedRange ?? '') === 'custom' ? '' : 'd-none' }}">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <label class="stats-filter-label" for="startDate">{{ __('Start Date') }}</label>
                                            <input id="startDate" type="date" name="start_date" class="form-control" value="{{ $filterStartDate ?? '' }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="stats-filter-label" for="endDate">{{ __('End Date') }}</label>
                                            <input id="endDate" type="date" name="end_date" class="form-control" value="{{ $filterEndDate ?? '' }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 text-lg-right">
                                    <button type="submit" class="btn btn-info btn-sm mr-2">
                                        <i class="fa fa-filter"></i> {{ __('Apply') }}
                                    </button>
                                    <a href="{{ route('admin.airdrop.stats', $campaign->id) }}" class="btn btn-outline-secondary btn-sm">
                                        {{ __('Reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>

                        <div class="stats-range-note">
                            {{ __('Tip: use Custom to compare campaign claim and unlock behavior across any date window.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Participants') }}</span>
                        <div class="value">{{ number_format($total_participants) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Claim Entries') }}</span>
                        <div class="value">{{ number_format($total_claim_entries) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Bonus Claim Entries') }}</span>
                        <div class="value">{{ number_format($total_bonus_claim_entries) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Claimed OBX') }}</span>
                        <div class="value">{{ number_format((float) $total_claimed_obx, 4) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Bonus OBX') }}</span>
                        <div class="value">{{ number_format((float) $total_bonus_obx, 4) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Confirmed Unlocks') }}</span>
                        <div class="value">{{ number_format($total_confirmed_unlocks) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Pending Unlocks') }}</span>
                        <div class="value">{{ number_format($total_pending_unlocks) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('USDT Paid') }}</span>
                        <div class="value">{{ number_format((float) $total_usdt_paid, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('OBX Released') }}</span>
                        <div class="value">{{ number_format((float) $total_obx_released, 4) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12 mb-4">
                <div class="stats-card">
                    <div class="card-body">
                        <span class="label">{{ __('Unlock Requests') }}</span>
                        <div class="value">{{ number_format($total_unlock_requests) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="stats-table-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <h4 class="mb-0 text-white">{{ __('Per User Airdrop Stats') }}</h4>
                            <div>
                                <a href="{{ route('admin.airdrop.claims', $campaign->id) }}" class="btn btn-sm btn-outline-info mr-2">{{ __('Claim Records') }}</a>
                                <a href="{{ route('admin.airdrop.unlocks', $campaign->id) }}" class="btn btn-sm btn-outline-warning">{{ __('Unlock Records') }}</a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-borderless custom-table mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('User') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Claim Entries') }}</th>
                                    <th>{{ __('Bonus Entries') }}</th>
                                    <th>{{ __('Claimed OBX') }}</th>
                                    <th>{{ __('Bonus OBX') }}</th>
                                    <th>{{ __('USDT Paid') }}</th>
                                    <th>{{ __('OBX Released') }}</th>
                                    <th>{{ __('Unlock Status') }}</th>
                                    <th>{{ __('Last Claim Date') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($perUserStats as $row)
                                    @php
                                        $fullName = trim(($row->user->first_name ?? '').' '.($row->user->last_name ?? ''));
                                        $displayName = $fullName !== '' ? $fullName : __('User') . ' #' . $row->user_id;
                                    @endphp
                                    <tr>
                                        <td>{{ $row->user_id }}</td>
                                        <td>{{ $displayName }}</td>
                                        <td>{{ $row->user->email ?? '—' }}</td>
                                        <td>{{ number_format((int) $row->total_claim_count) }}</td>
                                        <td>{{ number_format((int) $row->bonus_claim_count) }}</td>
                                        <td>{{ number_format((float) $row->total_claimed_obx, 4) }}</td>
                                        <td>{{ number_format((float) $row->total_bonus_obx, 4) }}</td>
                                        <td>{{ number_format((float) $row->total_usdt_paid, 2) }}</td>
                                        <td>{{ number_format((float) $row->total_obx_released, 4) }}</td>
                                        <td>
                                            @if($row->unlock_status === 'confirmed')
                                                <span class="badge badge-success">{{ __('Confirmed') }}</span>
                                            @elseif($row->unlock_status === 'pending')
                                                <span class="badge badge-warning">{{ __('Pending') }}</span>
                                            @else
                                                <span class="badge badge-secondary">{{ __('Not Requested') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->last_claim_date ? \Carbon\Carbon::parse($row->last_claim_date)->format('Y-m-d') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-5">{{ __('No user claim stats found for this campaign yet.') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($perUserStats->hasPages())
                            <div class="mt-3">{{ $perUserStats->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var rangeSelect = document.getElementById('statsRange');
            var customRangeFields = document.getElementById('customRangeFields');

            if (!rangeSelect || !customRangeFields) {
                return;
            }

            function toggleCustomRange() {
                if (rangeSelect.value === 'custom') {
                    customRangeFields.classList.remove('d-none');
                } else {
                    customRangeFields.classList.add('d-none');
                }
            }

            rangeSelect.addEventListener('change', toggleCustomRange);
            toggleCustomRange();
        })();
    </script>
@endsection
