@extends('admin.master', ['menu' => 'merchants'])
@section('title', 'Payment Orders — ' . $key->name)

@section('content')
<div class="custom-breadcrumb">
    <div class="row">
        <div class="col-12">
            <ul>
                <li>Payment Gateway</li>
                <li><a href="{{ route('admin.merchant.index') }}">Merchant API Keys</a></li>
                <li class="active-item">Orders — {{ $key->name }}</li>
            </ul>
        </div>
    </div>
</div>

<div class="row mt-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-2">API Key Details</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Key Name</td><td><strong>{{ $key->name }}</strong></td></tr>
                    <tr><td class="text-muted">API Key</td><td><code style="font-size:11px">{{ $key->api_key }}</code></td></tr>
                    <tr><td class="text-muted">Owner</td><td>{{ $key->user->email ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td>
                            @if($key->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="text-muted">Webhook URL</td><td style="font-size:12px">{{ $key->webhook_url ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Allowed Coins</td><td>{{ $key->allowed_coins ? implode(', ', $key->allowed_coins) : 'All' }}</td></tr>
                    <tr><td class="text-muted">Last Used</td><td>{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Payment Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>UUID</th>
                                <th>Merchant Ref</th>
                                <th>Coin</th>
                                <th>Amount</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>Webhook</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ $order->checkoutUrl() }}" target="_blank"
                                       title="{{ $order->uuid }}" style="font-size:11px;font-family:monospace">
                                        {{ substr($order->uuid, 0, 8) }}…
                                    </a>
                                </td>
                                <td style="font-size:12px">{{ $order->merchant_order_id ?? '—' }}</td>
                                <td>{{ $order->coin_type }}</td>
                                <td>{{ rtrim(rtrim($order->amount,'0'),'.') }}</td>
                                <td>{{ rtrim(rtrim($order->amount_received,'0'),'.') ?: '0' }}</td>
                                <td>
                                    @php
                                        $badgeMap = [
                                            'pending'    => 'badge-primary',
                                            'confirming' => 'badge-info',
                                            'completed'  => 'badge-success',
                                            'expired'    => 'badge-secondary',
                                            'underpaid'  => 'badge-warning',
                                        ];
                                        $cls = $badgeMap[$order->status] ?? 'badge-light';
                                    @endphp
                                    <span class="badge {{ $cls }}">{{ ucfirst($order->status) }}</span>
                                </td>
                                <td style="font-size:11px">{{ $order->expires_at ? $order->expires_at->format('m-d H:i') : '—' }}</td>
                                <td>
                                    @if($order->webhook_sent_at)
                                        <span class="badge badge-success" title="{{ $order->webhook_sent_at }}">Sent</span>
                                    @else
                                        <span class="badge badge-light">—</span>
                                    @endif
                                </td>
                                <td style="font-size:11px">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No orders yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($orders->hasPages())
            <div class="card-footer">
                {{ $orders->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
