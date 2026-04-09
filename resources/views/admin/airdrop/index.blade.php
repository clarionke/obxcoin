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
                <li class="breadcrumb-item active">{{ __('Airdrop') }}</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('dismiss'))
            <div class="alert alert-danger">{{ session('dismiss') }}</div>
        @endif

        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.airdrop.create') }}" class="btn btn-primary">
                    <i class="fa fa-plus"></i> {{ __('New Campaign') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><b>{{ __('Airdrop Campaigns') }}</b></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Start') }}</th>
                                <th>{{ __('End') }}</th>
                                <th>{{ __('Daily OBX') }}</th>
                                <th>{{ __('Unlock Fee') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($campaigns as $c)
                            <tr>
                                <td>{{ $c->id }}</td>
                                <td>{{ $c->name }}</td>
                                <td>{{ $c->start_date->format('Y-m-d H:i') }}</td>
                                <td>{{ $c->end_date->format('Y-m-d H:i') }}</td>
                                <td>{{ number_format((float)$c->daily_claim_amount, 4) }} OBX</td>
                                <td>
                                    @if($c->fee_revealed)
                                        <span class="badge badge-success">{{ number_format($c->unlock_fee_usdt, 2) }} USDT</span>
                                    @elseif($c->hasEnded())
                                        <span class="badge badge-warning">{{ __('Not Revealed') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('Hidden') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($c->isLive())
                                        <span class="badge badge-success">{{ __('Live') }}</span>
                                    @elseif($c->hasEnded())
                                        <span class="badge badge-secondary">{{ __('Ended') }}</span>
                                    @elseif($c->is_active)
                                        <span class="badge badge-primary">{{ __('Upcoming') }}</span>
                                    @else
                                        <span class="badge badge-danger">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        @if(!$c->hasStarted())
                                            <a href="{{ route('admin.airdrop.edit', $c->id) }}"
                                               class="btn btn-info btn-sm" title="{{ __('Edit') }}">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif

                                        @if($c->hasEnded() && !$c->fee_revealed)
                                            <button type="button" class="btn btn-warning btn-sm"
                                                    data-toggle="modal" data-target="#revealFeeModal{{ $c->id }}"
                                                    title="{{ __('Reveal Unlock Fee') }}">
                                                <i class="fa fa-unlock"></i>
                                            </button>
                                        @endif

                                        <a href="{{ route('admin.airdrop.toggleActive', $c->id) }}"
                                           class="btn btn-{{ $c->is_active ? 'danger' : 'success' }} btn-sm"
                                           title="{{ $c->is_active ? __('Deactivate') : __('Activate') }}"
                                           onclick="return confirm('{{ __('Toggle campaign status?') }}')">
                                            <i class="fa fa-{{ $c->is_active ? 'ban' : 'check' }}"></i>
                                        </a>

                                        <a href="{{ route('admin.airdrop.claims', $c->id) }}"
                                           class="btn btn-secondary btn-sm" title="{{ __('View Claims') }}">
                                            <i class="fa fa-list"></i>
                                        </a>

                                        <a href="{{ route('admin.airdrop.unlocks', $c->id) }}"
                                           class="btn btn-dark btn-sm" title="{{ __('View Unlocks') }}">
                                            <i class="fa fa-key"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            {{-- Reveal Fee Modal --}}
                            @if($c->hasEnded() && !$c->fee_revealed)
                            <div class="modal fade" id="revealFeeModal{{ $c->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <form action="{{ route('admin.airdrop.revealFee', $c->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ __('Reveal Unlock Fee — :name', ['name' => $c->name]) }}</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted">
                                                    {{ __('Set the USDT fee users must pay to unlock their locked airdrop OBX tokens. This fee is revealed once and cannot be changed.') }}
                                                </p>
                                                <div class="form-group">
                                                    <label>{{ __('Unlock Fee (USDT)') }}</label>
                                                    <input type="number" name="unlock_fee_usdt" class="form-control"
                                                           step="0.01" min="0.01" required placeholder="e.g. 5.00">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                                                <button type="submit" class="btn btn-warning">{{ __('Reveal Fee') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No airdrop campaigns yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</section>
@endsection
