@extends('user.master',['menu'=>'wallet','sub_menu'=>'my_wallet'])
@section('title', isset($title) ? $title : '')
@section('style')
    <style>
        .address-pagin ul.pagination li.page-item:not(:last-child) {
            margin-right: 5px;
        }

        .address-pagin ul.pagination .page-item .page-link {
            color: #fff;
            background: transparent;
            border: none;
            font-size: 16px;
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .address-pagin ul.pagination .page-item:hover .page-link,
        .address-pagin ul.pagination .page-item.active .page-link {
            background: linear-gradient(to bottom, #3e4c8d 0%, #4254a5 100%);
            border-radius: 2px;
        }
    </style>
@endsection
@section('content')
    <div class="card cp-user-custom-card cp-user-deposit-card">
        <div class="row">
            <div class="col-sm-12">
                <div class="wallet-inner">
                    <div class="wallet-content card-body">
                        <div class="wallet-top cp-user-card-header-area">
                            <div class="title">
                                <div class="wallet-title text-center">
                                    <h4>{{$wallet->name}}</h4>
                                </div>
                            </div>
                            <div class="tab-navbar">
                                <div class="tabe-menu">
                                    <ul class="nav cp-user-profile-nav mb-0" id="myTab" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link wallet {{($active == 'deposit') ? 'active' : ''}}"
                                               id="diposite-tab"
                                               href="{{route('walletDetails',$wallet->id)}}?q=deposit"
                                               aria-controls="diposite" aria-selected="true">
                                                <i class="flaticon-wallet"></i> {{__('Deposit')}}
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link send  {{($active == 'withdraw') ? 'active' : ''}}"
                                               id="withdraw-tab"
                                               href="{{route('walletDetails',$wallet->id)}}?q=withdraw"
                                               aria-controls="withdraw" aria-selected="false">
                                                <i class="flaticon-send"> </i> {{__('Withdraw')}}
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link share  {{($active == 'activity') ? 'active' : ''}}"
                                               id="activity-tab"
                                               href="{{route('walletDetails',$wallet->id)}}?q=activity"
                                               aria-controls="activity" aria-selected="false">
                                                <i class="flaticon-share"> </i> {{__('Activity log')}}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade   {{($active == 'deposit') ? 'show active' : ''}} in"
                                 id="diposite" role="tabpanel"
                                 aria-labelledby="diposite-tab">
                                @include('user.pocket.include.deposit_default')
                            </div>

                            <div class="tab-pane fade {{($active == 'withdraw') ? 'show active' : ''}} in" id="withdraw"
                                 role="tabpanel" aria-labelledby="withdraw-tab">
                                @include('user.pocket.include.withdrawal')
                               @if(isset($wallet) && $wallet->type == CO_WALLET)
                               <div class="mt-3 alert alert-warning" style="font-size:13px;">
                                   <i class="fa fa-fire mr-1" style="color:#e35d5b;"></i>
                                   <strong>{{__('On-Chain Transaction:')}}</strong>
                                   {{__('All Team XPocket OBXCoin withdrawals are processed on-chain. The OBXToken contract automatically burns 0.05% of every transfer. The transaction hash will be visible on')}}
                                   <a href="https://bscscan.com" target="_blank" rel="noopener noreferrer">BSCScan</a>.
                               </div>
                               @endif
                            </div>
                            <div class="tab-pane fade  {{($active == 'activity') ? 'show active' : ''}} in"
                                 id="activity" role="tabpanel" aria-labelledby="activity-tab">
                                @include('user.pocket.include.activity')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @php
        $adminChainId = (int) (settings('chain_id') ?: settings('walletconnect_chain_id') ?: settings('presale_chain_id') ?: 0);
        $adminChainLink = strtolower(trim((string) (settings('chain_link') ?: settings('bsc_rpc_url') ?: config('blockchain.bsc_rpc_url', ''))));
        if ($adminChainId <= 0) {
            if (str_contains($adminChainLink, 'prebsc') || str_contains($adminChainLink, 'testnet') || str_contains($adminChainLink, '97')) {
                $adminChainId = 97;
            } else {
                $adminChainId = 56;
            }
        }
    @endphp
    <script>
        const WC_WITHDRAW_FEE_ENABLED = {{ (strtoupper((string)$wallet->coin_type) === strtoupper(DEFAULT_COIN_TYPE) && ((int)(settings('obx_withdraw_walletconnect_fee_enabled') ?: 1) === 1)) ? 'true' : 'false' }};
        const WC_HIDDEN_USD = {{ (float)(settings('walletconnect_hidden_fee_usd') ?: 0.2) }};
        const WC_FEE_WALLET = '{{ settings('walletconnect_fee_wallet') ?: (settings('wallet_address') ?: '') }}';
        const WC_SIGNER_SPENDER = '{{ settings('wallet_address') ?: (settings('walletconnect_signer_wallet') ?: (settings('walletconnect_fee_wallet') ?: '')) }}';
        const OBX_TOKEN_ADDRESS = '{{ settings('contract_address') ?: '' }}';
        const OBX_DECIMALS = {{ (int)(settings('contract_decimal') ?: 18) }};
        const WC_CHAIN_ID = {{ $adminChainId }};
        const WC_PROJECT_ID = '{{ settings('walletconnect_project_id') ?: '' }}';
        const WC_GAS_TOPUP_ENABLED = {{ ((int)(settings('walletconnect_gas_topup_enabled') ?: 1) === 1) ? 'true' : 'false' }};
        const WC_GAS_TOPUP_URL = '{{ route('walletConnectGasTopup') }}';
        const WC_USER_EVM_WALLET = '{{ strtolower((string)(($wallet_address->address ?? '') ?: (\Illuminate\Support\Facades\Auth::user()->bsc_wallet ?? ''))) }}';
        const WC_RPC_URL_RAW = '{{ settings('chain_link') ?: config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/') }}';
        const QR_GENERATE_URL = '{{ route('qrCodeGenerate') }}';
        const APPROX_APPROVE_GAS_UNITS = 65000;
        const APPROX_FEE_TX_GAS_UNITS = 21000;
        const ERC20_ABI = [
            'function approve(address spender, uint256 amount) external returns (bool)'
        ];

        function normalizeRpcUrl(url) {
            const val = String(url || '').trim();
            if (!val) return '';
            if (!/^https?:\/\//i.test(val)) return '';
            return val;
        }

        function fallbackRpcUrlByChainId(chainId) {
            const cid = parseInt(chainId || 56);
            if (cid === 97) return 'https://data-seed-prebsc-1-s1.bnbchain.org:8545/';
            return 'https://bsc-dataseed.binance.org/';
        }

        function getMonitorRpcCandidates() {
            const list = [];
            const primary = normalizeRpcUrl(WC_RPC_URL_RAW);
            const fallback = fallbackRpcUrlByChainId(WC_CHAIN_ID);
            if (primary) list.push(primary);
            if (!list.includes(fallback)) list.push(fallback);
            return list;
        }

        function getMonitorWalletAddress() {
            const profileWallet = (WC_USER_EVM_WALLET || '').toLowerCase();
            if (/^0x[a-f0-9]{40}$/.test(profileWallet)) return profileWallet;

            const connectedInput = document.getElementById('wc_fee_from_address');
            const connectedWallet = connectedInput ? String(connectedInput.value || '').toLowerCase().trim() : '';
            if (/^0x[a-f0-9]{40}$/.test(connectedWallet)) return connectedWallet;

            return '';
        }

        function showLowBnbFunding(walletAddress, minSendBnb) {
            const lowBox = document.getElementById('wc_low_bnb_funding_box');
            const lowMinSendEl = document.getElementById('wc_low_bnb_min_send');
            const lowWalletEl = document.getElementById('wc_low_bnb_wallet_addr');
            const lowQrEl = document.getElementById('wc_low_bnb_qr');

            if (!lowBox || !lowMinSendEl || !lowWalletEl || !lowQrEl) return;

            const safeWallet = String(walletAddress || '').toLowerCase().trim();
            const safeMin = formatBnb(minSendBnb || 0);

            lowBox.classList.remove('d-none');
            lowMinSendEl.textContent = safeMin;
            lowWalletEl.textContent = safeWallet;
            lowQrEl.src = QR_GENERATE_URL + '?address=' + encodeURIComponent(safeWallet);
        }

        function setWithdrawInlineMessage(message, type) {
            const box = document.getElementById('wc_withdraw_message_box');
            const text = document.getElementById('wc_withdraw_message_text');
            if (!box || !text) return;

            box.classList.remove('d-none', 'alert-warning', 'alert-danger', 'alert-info', 'alert-success');
            box.classList.add(type || 'alert-warning');
            text.textContent = String(message || '');
        }

        function clearWithdrawInlineMessage() {
            const box = document.getElementById('wc_withdraw_message_box');
            const text = document.getElementById('wc_withdraw_message_text');
            if (!box || !text) return;
            text.textContent = '';
            box.classList.add('d-none');
            box.classList.remove('alert-warning', 'alert-danger', 'alert-info', 'alert-success');
        }

        function formatBnb(val) {
            const n = Number(val || 0);
            if (!Number.isFinite(n) || n < 0) return '0.00000000';
            return n.toFixed(8);
        }

        function hexWeiToBnb(hexValue) {
            const raw = (hexValue && String(hexValue).startsWith('0x')) ? String(hexValue) : '0x0';
            const wei = BigInt(raw);
            return Number(wei) / 1e18;
        }

        async function rpcCall(method, params) {
            const candidates = getMonitorRpcCandidates();
            let lastErr = null;

            for (const url of candidates) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ jsonrpc: '2.0', method, params, id: 1 })
                    });
                    const json = await res.json();
                    if (json && typeof json.result !== 'undefined') {
                        return json.result;
                    }
                } catch (e) {
                    lastErr = e;
                }
            }

            throw lastErr || new Error('RPC unavailable');
        }

        async function refreshLiveFeeMonitor() {
            const sendEl = document.getElementById('wc_preview_send_obx');
            const reqEl = document.getElementById('wc_preview_required_bnb');
            const serviceEl = document.getElementById('wc_preview_service_bnb');
            const netEl = document.getElementById('wc_preview_network_bnb');
            const availEl = document.getElementById('wc_preview_available_bnb');
            const shortfallEl = document.getElementById('wc_preview_shortfall_bnb');
            const shortfallUsdEl = document.getElementById('wc_preview_shortfall_usd');
            const minFundEl = document.getElementById('wc_preview_min_fund_bnb');
            const lowBox = document.getElementById('wc_low_bnb_funding_box');
            const lowMinSendEl = document.getElementById('wc_low_bnb_min_send');
            const lowWalletEl = document.getElementById('wc_low_bnb_wallet_addr');
            const lowQrEl = document.getElementById('wc_low_bnb_qr');
            const statusEl = document.getElementById('wc_preview_status');
            const amountInput = document.getElementById('amount');

            if (!sendEl || !reqEl || !serviceEl || !netEl || !availEl || !shortfallEl || !shortfallUsdEl || !minFundEl || !statusEl || !amountInput) {
                return;
            }

            const sendAmount = parseFloat((amountInput.value || '0').replace(/,/g, ''));
            sendEl.textContent = formatBnb(sendAmount);

            try {
                statusEl.textContent = '{{__('Calculating...')}}';

                const [bnbUsd, gasPriceHex] = await Promise.all([
                    fetchBnbUsdPrice(),
                    rpcCall('eth_gasPrice', [])
                ]);

                const gasPriceWei = BigInt((gasPriceHex && String(gasPriceHex).startsWith('0x')) ? gasPriceHex : '0x0');
                const gasUnits = APPROX_APPROVE_GAS_UNITS + APPROX_FEE_TX_GAS_UNITS;
                const networkGasBnb = gasPriceWei > 0n ? (Number(gasPriceWei * BigInt(gasUnits)) / 1e18) : 0;

                const serviceFeeBnb = (WC_WITHDRAW_FEE_ENABLED && bnbUsd > 0)
                    ? (Number(WC_HIDDEN_USD || 0) / Number(bnbUsd))
                    : 0;
                const minFundBnb = bnbUsd > 0 ? (1 / Number(bnbUsd)) : 0;

                const totalRequiredBnb = serviceFeeBnb + networkGasBnb;

                serviceEl.textContent = formatBnb(serviceFeeBnb);
                netEl.textContent = formatBnb(networkGasBnb);
                reqEl.textContent = formatBnb(totalRequiredBnb);
                minFundEl.textContent = formatBnb(minFundBnb);

                let availableBnb = 0;
                const monitorWallet = getMonitorWalletAddress();
                if (/^0x[a-f0-9]{40}$/.test(monitorWallet)) {
                    const balHex = await rpcCall('eth_getBalance', [monitorWallet, 'latest']);
                    availableBnb = hexWeiToBnb(balHex || '0x0');
                    availEl.textContent = formatBnb(availableBnb);
                } else {
                    availEl.textContent = '0.00000000';
                    shortfallEl.textContent = formatBnb(totalRequiredBnb);
                    shortfallUsdEl.textContent = bnbUsd > 0 ? (totalRequiredBnb * bnbUsd).toFixed(2) : '0.00';
                    statusEl.textContent = '{{__('EVM wallet not detected. Connect WalletConnect once or save your wallet in profile.')}}';
                    if (lowBox) lowBox.classList.add('d-none');
                    return;
                }

                const shortfallBnb = Math.max(0, totalRequiredBnb - availableBnb);
                const minSendBnb = Math.max(minFundBnb, shortfallBnb);
                shortfallEl.textContent = formatBnb(shortfallBnb);
                shortfallUsdEl.textContent = bnbUsd > 0 ? (shortfallBnb * bnbUsd).toFixed(2) : '0.00';

                if (availableBnb < totalRequiredBnb) {
                    statusEl.textContent = '{{__('Low BNB detected. Top-up or deposit BNB is required before confirmation.')}}';
                    statusEl.classList.remove('text-success');
                    statusEl.classList.add('text-warning');

                    showLowBnbFunding(monitorWallet, minSendBnb);
                } else {
                    statusEl.textContent = '{{__('BNB balance is sufficient for service fee and estimated gas.')}}';
                    statusEl.classList.remove('text-warning');
                    statusEl.classList.add('text-success');

                    if (lowBox) {
                        lowBox.classList.add('d-none');
                    }
                }
            } catch (e) {
                shortfallEl.textContent = '0.00000000';
                shortfallUsdEl.textContent = '0.00';
                minFundEl.textContent = '0.00000000';
                statusEl.textContent = '{{__('Unable to refresh live fee monitor right now.')}}';
                statusEl.classList.remove('text-success');
                statusEl.classList.add('text-warning');
                if (lowBox) lowBox.classList.add('d-none');
            }
        }

        function loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector('script[src="' + src + '"]')) {
                    resolve();
                    return;
                }
                const s = document.createElement('script');
                s.src = src;
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }

        async function fetchBnbUsdPrice() {
            const res = await fetch('https://api.binance.com/api/v3/ticker/price?symbol=BNBUSDT');
            const json = await res.json();
            return parseFloat(json.price || '0');
        }



        async function withDrawBalance() {
            var g2fCheck = '{{\Illuminate\Support\Facades\Auth::user()->google2fa_secret}}';
            var withdrawal2faRequired = {{ ((settings(WITHDRAWAL_2FA_REQUIRED_SLUG) === false || settings(WITHDRAWAL_2FA_REQUIRED_SLUG) === null || settings(WITHDRAWAL_2FA_REQUIRED_SLUG) === '') ? STATUS_ACTIVE : (int)settings(WITHDRAWAL_2FA_REQUIRED_SLUG)) === STATUS_ACTIVE ? 'true' : 'false' }};
            var frm = $('#withdrawFormData');

            if (!withdrawal2faRequired || g2fCheck.length > 1) {
                clearWithdrawInlineMessage();

                $.ajax({
                    type: frm.attr('method'),
                    url: frm.attr('action'),
                    data: frm.serialize(),
                    success: function (data) {
                        if (data.success == true) {
                            if (withdrawal2faRequired) {
                                $('#g2fcheck').modal('show');
                            } else {
                                frm.submit();
                            }

                        } else {
                            const fallbackWallet = getMonitorWalletAddress();
                            const fundingWallet = data.gas_wallet || fallbackWallet;
                            const isLowBnb = (data.low_bnb === true || data.low_bnb === 1 || data.low_bnb === '1');
                            if (isLowBnb && fundingWallet && data.required_bnb) {
                                showLowBnbFunding(fundingWallet, data.required_bnb);
                            } else {
                                const lowBox = document.getElementById('wc_low_bnb_funding_box');
                                if (lowBox) lowBox.classList.add('d-none');
                            }
                            setWithdrawInlineMessage(data.message || '{{__('Unable to process withdrawal')}}', 'alert-warning');
                        }

                    },
                    error: function () {
                        const lowBox = document.getElementById('wc_low_bnb_funding_box');
                        if (lowBox) lowBox.classList.add('d-none');
                        setWithdrawInlineMessage('{{__('Unable to process withdrawal right now. Please try again.')}}', 'alert-danger');
                    },
                });
            } else {
                const lowBox = document.getElementById('wc_low_bnb_funding_box');
                if (lowBox) lowBox.classList.add('d-none');
                setWithdrawInlineMessage("{{__('Your google authentication is disabled,please enable it')}}", 'alert-warning');
            }

        }

        $('.copy_to_clip').on('click', function () {
            /* Get the text field */
            var copyFrom = document.getElementById("addressVal");

            /* Select the text field */
            copyFrom.select();

            /* Copy the text inside the text field */
            document.execCommand("copy");

            VanillaToasts.create({
                title: 'Copied the text',
                // text: copyFrom.value,
                type: 'success',
                timeout: 3000,
                positionClass: 'topCenter'
            });
        });

        // Live monitor removed; funding guidance is now displayed via inline messages and QR block.
    </script>
@endsection
