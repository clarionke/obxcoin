@php
    $obxChainId = (int) (settings('chain_id') ?: settings('walletconnect_chain_id') ?: settings('presale_chain_id') ?: 0);
    $obxChainLink = strtolower(trim((string) (settings('chain_link') ?: settings('bsc_rpc_url') ?: config('blockchain.bsc_rpc_url', ''))));
    if ($obxChainId <= 0) {
        if (str_contains($obxChainLink, 'prebsc') || str_contains($obxChainLink, 'testnet') || str_contains($obxChainLink, '97')) {
            $obxChainId = 97;
        } else {
            $obxChainId = 56;
        }
    }
    $obxNetworkName = ((int)$obxChainId === 97) ? 'BSC Testnet' : 'BSC Mainnet';
@endphp

<div class="row mt-4">
    <div class="col-lg-4 offset-lg-1">
        <div class="qr-img text-center">
            @if(!empty($wallet_address) && !empty($wallet_address->address))  {!! QrCode::size(300)->generate($wallet_address->address); !!}
            @else
                {!! QrCode::size(300)->generate('0'); !!}
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="cp-user-copy tabcontent-right">
            <form action="#">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="button" class="copy_to_clip btn">{{__('Copy')}}</button>
                    </div>
                    <input readonly value="{{isset($wallet_address) ? $wallet_address->address : 0}}"
                           type="text" class="form-control" id="addressVal">
                </div>
            </form>
            <div class="aenerate-address">
                @if(empty($wallet_address) || empty($wallet_address->address))
                    <a class="btn cp-user-buy-btn"  href="{{route('generateNewAddress')}}?wallet_id={{$wallet->id}}">
                        {{__('Generate address')}}
                    </a>
                @endif
            </div>
        </div>
        <div class="card mt-4">
            <h5 class="card-header">{{__("Token Info")}}</h5>
            <div class="card-body">
                <p> <label for="">{{__('Chain link')}} : </label></p>
                <p>
                    <label for="">{{allsetting('chain_link')}}</label>
                </p>
                <p><label for="">{{__('Contract address')}} :</label></p>
                <p>
                    <label for="">
                        {{allsetting('contract_address')}}
                    </label>
                </p>
                <p><label for="">{{__('Token Symbol')}} :</label></p>
                <p>
                    <label for="">
                        {{isset(allsetting()['coin_name']) ? allsetting()['coin_name'] : ''}}
                    </label>
                </p>
            </div>
        </div>

        <div class="card mt-4">
            <h5 class="card-header">{{__('Past OBX Wallet Addresses')}}</h5>
            <div class="card-body p-0">
                <div class="table-responsive mb-0">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>{{__('Address')}}</th>
                            <th>{{__('Token')}}</th>
                            <th>{{__('Network')}}</th>
                            <th>{{__('Contract')}}</th>
                            <th>{{__('Created')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if(isset($address_histories) && count($address_histories) > 0)
                            @foreach($address_histories as $address_history)
                                <tr>
                                    <td style="word-break:break-all;">{{$address_history->address}}</td>
                                    <td>{{isset(allsetting()['coin_name']) ? allsetting()['coin_name'] : 'OBX'}}</td>
                                    <td>{{$obxNetworkName}}</td>
                                    <td style="word-break:break-all;">{{allsetting('contract_address')}}</td>
                                    <td>{{$address_history->created_at}}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center">{{__('No past address found')}}</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($address_histories) && count($address_histories) > 0)
                <div class="card-footer">
                    {{ $address_histories->appends(request()->input())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ── On-chain deposit scanner ──────────────────────────────────────────── --}}
@php
    $scanWalletAddress = isset($wallet_address) ? ($wallet_address->address ?? '') : '';
    $scanContractAddress = settings('contract_address') ?: '';
    $scanRpcUrl = settings('chain_link') ?: settings('bsc_rpc_url') ?: 'https://bsc-dataseed.binance.org/';
    $scanChainId = (int)(settings('chain_id') ?: settings('walletconnect_chain_id') ?: 56);
    $scanExplorer = $scanChainId === 97 ? 'https://testnet.bscscan.com' : 'https://bscscan.com';
@endphp

@if(!empty($scanWalletAddress) && !empty($scanContractAddress))
<div class="mt-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" style="padding:12px 16px;">
            <h5 class="mb-0" style="font-size:13px;font-weight:600;">
                <i class="fa fa-history mr-1"></i> {{ __('On-Chain Deposit History') }}
            </h5>
            <button id="obx-deposit-scan-btn" class="btn btn-sm btn-primary" style="font-size:12px;padding:4px 12px;">
                <i class="fa fa-refresh" id="obx-scan-icon"></i> {{ __('Scan Now') }}
            </button>
        </div>
        <div class="card-body p-0">
            <div id="obx-scan-status" class="px-3 py-2" style="font-size:12px;color:var(--muted,#888);display:none;"></div>
            <div class="table-responsive mb-0">
                <table class="table mb-0" id="obx-deposit-scan-table">
                    <thead>
                        <tr>
                            <th>{{ __('Tx Hash') }}</th>
                            <th>{{ __('From') }}</th>
                            <th>{{ __('Amount (OBX)') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="obx-deposit-scan-tbody">
                        <tr><td colspan="4" class="text-center" style="font-size:12px;color:var(--muted,#888);">{{ __('Click "Scan Now" to check for incoming deposits') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var WALLET_ADDRESS   = '{{ strtolower($scanWalletAddress) }}';
    var CONTRACT_ADDRESS = '{{ $scanContractAddress }}';
    var RPC_URL          = '{{ $scanRpcUrl }}';
    var DEPOSIT_URL      = '{{ route('depositCallback') }}';
    var CSRF_TOKEN       = '{{ csrf_token() }}';
    var EXPLORER         = '{{ $scanExplorer }}';

    // ERC-20 Transfer event topic
    var TRANSFER_TOPIC   = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    // Pad address to 32-byte topic
    var paddedTo         = '0x000000000000000000000000' + WALLET_ADDRESS.replace('0x', '');

    var scanBtn  = document.getElementById('obx-deposit-scan-btn');
    var scanIcon = document.getElementById('obx-scan-icon');
    var statusEl = document.getElementById('obx-scan-status');
    var tbody    = document.getElementById('obx-deposit-scan-tbody');

    function setStatus(msg) {
        statusEl.textContent = msg;
        statusEl.style.display = msg ? 'block' : 'none';
    }

    function shortenHash(h) {
        if (!h) return '';
        return h.substr(0, 10) + '…' + h.substr(-6);
    }

    function renderRows(txs) {
        if (!txs || txs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="font-size:12px;color:var(--muted,#888);">{{ __('No incoming OBX transfers found in recent blocks') }}</td></tr>';
            return;
        }
        var rows = '';
        txs.forEach(function(tx) {
            var link = '<a href="' + EXPLORER + '/tx/' + tx.hash + '" target="_blank" rel="noopener noreferrer">' + shortenHash(tx.hash) + '</a>';
            var from = tx.from ? (tx.from.substr(0, 8) + '…' + tx.from.substr(-6)) : '—';
            var recorded = tx.recorded
                ? '<span style="color:#3fb950;">✓ {{ __('Recorded') }}</span>'
                : '<span style="color:var(--muted,#888);">{{ __('Pending register') }}</span>';
            rows += '<tr><td style="font-size:11px;">' + link + '</td>'
                  + '<td style="font-size:11px;">' + from + '</td>'
                  + '<td style="font-size:12px;font-weight:600;">' + parseFloat(tx.amount).toFixed(4) + '</td>'
                  + '<td style="font-size:11px;">' + recorded + '</td></tr>';
        });
        tbody.innerHTML = rows;
    }

    async function rpcCall(method, params) {
        var res = await fetch(RPC_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: method, params: params })
        });
        var json = await res.json();
        if (json.error) throw new Error(json.error.message);
        return json.result;
    }

    async function registerDeposit(log) {
        // Decode amount from data field (uint256, 18 decimals)
        var rawHex = log.data || '0x0';
        var raw    = BigInt(rawHex);
        var amount = Number(raw) / 1e18;
        var from   = '0x' + (log.topics[1] || '').slice(26);

        try {
            var resp = await fetch(DEPOSIT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    transactionHash: log.transactionHash,
                    from: from,
                    value: amount,
                    blockNumber: parseInt(log.blockNumber, 16),
                    transactionIndex: parseInt(log.transactionIndex || '0x0', 16)
                })
            });
            var data = await resp.json();
            return data && (data.success === true || (data.message && data.message.toLowerCase().includes('already')));
        } catch (e) {
            return false;
        }
    }

    async function scan() {
        scanBtn.disabled = true;
        scanIcon.className = 'fa fa-spinner fa-spin';
        setStatus('{{ __('Fetching latest block…') }}');

        try {
            // Get latest block number
            var latestHex = await rpcCall('eth_blockNumber', []);
            var latest    = parseInt(latestHex, 16);
            var fromBlock = '0x' + Math.max(0, latest - 2000).toString(16);

            setStatus('{{ __('Scanning last 2,000 blocks for incoming OBX transfers…') }}');

            var logs = await rpcCall('eth_getLogs', [{
                address:   CONTRACT_ADDRESS,
                fromBlock: fromBlock,
                toBlock:   'latest',
                topics:    [TRANSFER_TOPIC, null, paddedTo]
            }]);

            if (!logs || logs.length === 0) {
                setStatus('{{ __('No incoming OBX transfers found in the last 2,000 blocks.') }}');
                renderRows([]);
                return;
            }

            setStatus('{{ __('Found') }} ' + logs.length + ' {{ __('transfer(s). Registering…') }}');

            var results = [];
            for (var i = 0; i < logs.length; i++) {
                var log    = logs[i];
                var rawHex = log.data || '0x0';
                var amount = Number(BigInt(rawHex)) / 1e18;
                var from   = '0x' + (log.topics[1] || '').slice(26);
                var recorded = await registerDeposit(log);
                results.push({
                    hash: log.transactionHash,
                    from: from,
                    amount: amount,
                    recorded: recorded
                });
            }

            renderRows(results);
            setStatus('{{ __('Done. Refresh the Activity tab to see updated deposit history.') }}');

        } catch (e) {
            setStatus('{{ __('Scan error: ') }}' + (e.message || e));
        } finally {
            scanBtn.disabled = false;
            scanIcon.className = 'fa fa-refresh';
        }
    }

    scanBtn.addEventListener('click', scan);

    // Auto-scan once when the deposit tab is shown
    scan();
})();
</script>
@endif
