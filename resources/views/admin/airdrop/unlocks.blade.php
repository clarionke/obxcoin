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
                    <li class="active-item">{{ __('Unlocks') }}</li>
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
                            <h3>{{ $campaign->name }} — {{ __('Unlock Records') }}</h3>
                            <p class="text-muted mb-0">
                                {{ __('Unlock Fee') }}:
                                @if($campaign->fee_revealed)
                                    <span class="badge badge-success">{{ number_format($campaign->unlock_fee_usdt, 2) }} USDT</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Not yet revealed') }}</span>
                                @endif
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
                                        <td>{{ number_format((float)$u->obx_released, 4) }} OBX</td>
                                        <td>
                                            @if($u->tx_hash)
                                                @php $txUrl = explorer_tx_url($u->tx_hash); @endphp
                                                <a href="{{ $txUrl ?? '#' }}" target="_blank" rel="noopener noreferrer" title="{{ $u->tx_hash }}">
                                                    <code>{{ substr($u->tx_hash, 0, 18) }}…</code>
                                                    <i class="fa fa-external-link" style="font-size:9px;opacity:.7;"></i>
                                                </a>
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
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">{{ __('No unlocks yet.') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($unlocks->hasPages())
                            <div class="p-3">{{ $unlocks->links() }}</div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

