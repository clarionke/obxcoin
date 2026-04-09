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
                    <li class="active-item">{{ $title }}</li>
                </ul>
            </div>
            <div class="col-sm-3 text-right">
                <a class="add-btn theme-btn" href="{{ route('admin.airdrop.create') }}">
                    <i class="fa fa-plus"></i> {{ __('New Campaign') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mx-4 mt-3" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif
    @if(session('dismiss'))
        <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3" role="alert">
            {{ session('dismiss') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="user-management">
        <div class="row">
            <div class="col-12">
                <div class="card-body">
                    <div class="header-bar p-4">
                        <div class="table-title">
                            <h3>{{ __('Airdrop Campaigns') }}</h3>
                        </div>
                    </div>

                    <div class="phase-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Start') }}</th>
                                        <th>{{ __('End') }}</th>
                                        <th>{{ __('Daily OBX') }}</th>
                                        <th>{{ __('Streak Bonus') }}</th>
                                        <th>{{ __('Unlock Fee') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($campaigns as $c)
                                    <tr>
                                        <td>{{ $c->id }}</td>
                                        <td><b>{{ $c->name }}</b></td>
                                        <td>{{ $c->start_date->format('Y-m-d H:i') }}</td>
                                        <td>{{ $c->end_date->format('Y-m-d H:i') }}</td>
                                        <td>{{ number_format((float)$c->daily_claim_amount, 4) }} OBX</td>
                                        <td>
                                            <span class="badge badge-info">{{ $c->streak_days ?? 5 }}d</span>
                                            +{{ number_format((float)($c->streak_bonus_amount ?? 0), 2) }} OBX
                                        </td>
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
                                            <div class="btn-group">
                                                <button type="button" class="btn dropdown-toggle"
                                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fa fa-ellipsis-h"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    @if(!$c->hasStarted())
                                                        <a href="{{ route('admin.airdrop.edit', $c->id) }}">
                                                            <button class="dropdown-item" type="button">
                                                                <i class="fa fa-edit mr-1"></i> {{ __('Edit') }}
                                                            </button>
                                                        </a>
                                                    @endif

                                                    <a href="{{ route('admin.airdrop.toggleActive', $c->id) }}"
                                                       onclick="return confirm('{{ __('Toggle campaign status?') }}')">
                                                        <button class="dropdown-item" type="button">
                                                            <i class="fa fa-{{ $c->is_active ? 'ban' : 'check' }} mr-1"></i>
                                                            {{ $c->is_active ? __('Deactivate') : __('Activate') }}
                                                        </button>
                                                    </a>

                                                    <a href="{{ route('admin.airdrop.claims', $c->id) }}">
                                                        <button class="dropdown-item" type="button">
                                                            <i class="fa fa-list mr-1"></i> {{ __('Claims') }}
                                                        </button>
                                                    </a>

                                                    <a href="{{ route('admin.airdrop.unlocks', $c->id) }}">
                                                        <button class="dropdown-item" type="button">
                                                            <i class="fa fa-key mr-1"></i> {{ __('Unlocks') }}
                                                        </button>
                                                    </a>

                                                    @if($c->hasEnded() && !$c->fee_revealed)
                                                        <button class="dropdown-item" type="button"
                                                                data-toggle="modal" data-target="#revealFeeModal{{ $c->id }}">
                                                            <i class="fa fa-unlock mr-1"></i> {{ __('Reveal Fee') }}
                                                        </button>
                                                    @endif
                                                </div>
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
                                                            {{ __('Set the USDT fee users must pay to unlock their locked airdrop OBX. This is revealed once and cannot be changed.') }}
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
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            <i class="fa fa-parachute-box fa-2x mb-2 d-block"></i>
                                            {{ __('No airdrop campaigns yet. Create your first campaign!') }}
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

