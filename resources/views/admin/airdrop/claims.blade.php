@extends('admin.master',['menu'=>$menu, 'sub_menu'=>$sub_menu])
@section('title', isset($title) ? $title : '')
@section('style')
@endsection
@section('content')
    {{-- breadcrumb --}}
    <div class="custom-breadcrumb">
        <div class="row">
            <div class="col-9">
                <ul>
                    <li>{{ __('Airdrop Management') }}</li>
                    <li><a href="{{ route('admin.airdrop.index') }}">{{ __('Campaigns') }}</a></li>
                    <li class="active-item">{{ __('Claims') }}</li>
                </ul>
            </div>
            <div class="col-sm-3 text-right">
                <a class="add-btn theme-btn" href="{{ route('admin.airdrop.index') }}">
                    <i class="fa fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>

    <div class="user-management">
        <div class="row">
            <div class="col-12">
                <div class="card-body">
                    <div class="header-bar p-4">
                        <div class="table-title">
                            <h3>{{ $campaign->name }} — {{ __('Claim Records') }}</h3>
                            <p class="text-muted mb-0">
                                {{ $campaign->start_date->format('Y-m-d') }} → {{ $campaign->end_date->format('Y-m-d') }}
                                &nbsp;&bull;&nbsp; {{ __('Daily') }}: {{ number_format((float)$campaign->daily_claim_amount, 4) }} OBX
                                &nbsp;&bull;&nbsp; {{ __('Streak every') }} {{ $campaign->streak_days ?? 5 }} {{ __('days') }}
                                (+{{ number_format((float)($campaign->streak_bonus_amount ?? 0), 4) }} OBX {{ __('bonus') }})
                            </p>
                        </div>
                    </div>

                    <div class="phase-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('User') }}</th>
                                        <th>{{ __('Email') }}</th>
                                        <th>{{ __('Claim Date') }}</th>
                                        <th>{{ __('Amount (OBX)') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Created') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($claims as $claim)
                                    <tr>
                                        <td>{{ $claim->id }}</td>
                                        <td>{{ $claim->user->name ?? '—' }}</td>
                                        <td>{{ $claim->user->email ?? '—' }}</td>
                                        <td>{{ $claim->claim_date->format('Y-m-d') }}</td>
                                        <td>{{ number_format((float)$claim->amount_obx, 4) }}</td>
                                        <td>
                                            @if($claim->is_bonus)
                                                <span class="badge badge-warning">
                                                    <i class="fa fa-fire"></i> {{ __('Streak Bonus') }}
                                                </span>
                                            @else
                                                <span class="badge badge-success">{{ __('Daily') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $claim->created_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">{{ __('No claims yet.') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($claims->hasPages())
                            <div class="p-3">{{ $claims->links() }}</div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

