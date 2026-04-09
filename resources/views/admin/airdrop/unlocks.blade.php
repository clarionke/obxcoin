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
                <li class="breadcrumb-item active">{{ __('Unlocks') }}</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-body py-2">
                <b>{{ $campaign->name }}</b> &nbsp;|&nbsp;
                {{ __('Unlock Fee') }}: {{ $campaign->fee_revealed ? number_format($campaign->unlock_fee_usdt, 2).' USDT' : __('Not yet revealed') }}
            </div>
        </div>

        <div class="card">
            <div class="card-header"><b>{{ __('Unlock Records') }}</b> ({{ $unlocks->total() }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('USDT Paid') }}</th>
                                <th>{{ __('OBX Released') }}</th>
                                <th>{{ __('TX Hash') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Unlocked At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($unlocks as $u)
                            <tr>
                                <td>{{ $u->id }}</td>
                                <td>{{ $u->user->name ?? '—' }}</td>
                                <td>{{ $u->user->email ?? '—' }}</td>
                                <td>{{ number_format($u->usdt_paid, 2) }}</td>
                                <td>{{ number_format((float)$u->obx_released, 4) }}</td>
                                <td>
                                    @if($u->tx_hash)
                                        <code>{{ substr($u->tx_hash, 0, 18) }}…</code>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($u->status === 'confirmed')
                                        <span class="badge badge-success">{{ __('Confirmed') }}</span>
                                    @else
                                        <span class="badge badge-warning">{{ __('Pending') }}</span>
                                    @endif
                                </td>
                                <td>{{ $u->unlocked_at ? $u->unlocked_at->format('Y-m-d H:i') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No unlocks yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($unlocks->hasPages())
            <div class="card-footer">{{ $unlocks->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
