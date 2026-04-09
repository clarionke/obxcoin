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
                <li class="breadcrumb-item active">{{ __('Claims') }}</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-body py-2">
                <b>{{ $campaign->name }}</b> &nbsp;|&nbsp;
                {{ $campaign->start_date->format('Y-m-d') }} → {{ $campaign->end_date->format('Y-m-d') }}
                &nbsp;|&nbsp; {{ __('Daily') }}: {{ number_format((float)$campaign->daily_claim_amount, 4) }} OBX
            </div>
        </div>

        <div class="card">
            <div class="card-header"><b>{{ __('Claim Records') }}</b> ({{ $claims->total() }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('Claim Date') }}</th>
                                <th>{{ __('Amount (OBX)') }}</th>
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
                                <td>{{ $claim->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No claims yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($claims->hasPages())
            <div class="card-footer">{{ $claims->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
