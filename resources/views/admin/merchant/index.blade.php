@extends('admin.master', ['menu' => 'merchants'])
@section('title', 'Merchant API Keys')

@section('content')
<div class="custom-breadcrumb">
    <div class="row">
        <div class="col-12">
            <ul>
                <li>Payment Gateway</li>
                <li class="active-item">Merchant API Keys</li>
            </ul>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Merchant API Keys</h5>
                <form class="form-inline" method="GET" action="{{ route('admin.merchant.index') }}">
                    <input type="text" name="search" class="form-control form-control-sm mr-2"
                           placeholder="Search name / key…" value="{{ request('search') }}">
                    <button class="btn btn-sm btn-primary">Search</button>
                    @if(request('search'))
                        <a href="{{ route('admin.merchant.index') }}" class="btn btn-sm btn-secondary ml-1">Clear</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Key Name</th>
                                <th>API Key</th>
                                <th>Status</th>
                                <th>Last Used</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($keys as $key)
                            <tr>
                                <td>{{ $key->id }}</td>
                                <td>
                                    <a href="{{ route('adminUserProfile', ['id' => $key->user_id]) }}">
                                        {{ $key->user->email ?? '—' }}
                                    </a>
                                </td>
                                <td>{{ $key->name }}</td>
                                <td>
                                    <code style="font-size:11px">{{ substr($key->api_key, 0, 16) }}…</code>
                                </td>
                                <td>
                                    @if($key->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : '—' }}</td>
                                <td>{{ $key->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <a href="{{ route('admin.merchant.orders', $key->id) }}"
                                       class="btn btn-xs btn-info" title="View Orders">Orders</a>

                                    <form method="POST" action="{{ route('admin.merchant.toggle', $key->id) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Toggle status for this key?')">
                                        @csrf
                                        <button class="btn btn-xs {{ $key->is_active ? 'btn-warning' : 'btn-success' }}">
                                            {{ $key->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>

                                    <a href="{{ route('admin.merchant.destroy', $key->id) }}"
                                       class="btn btn-xs btn-danger"
                                       onclick="return confirm('Permanently delete this API key and all its orders?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No merchant API keys found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($keys->hasPages())
            <div class="card-footer">
                {{ $keys->links() }}
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
