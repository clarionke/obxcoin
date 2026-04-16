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
                                   {{__('All Team Wallet OBXCoin withdrawals are processed on-chain. The OBXToken contract automatically burns 0.05% of every transfer. The transaction hash will be visible on')}}
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
    <script>
        const WC_WITHDRAW_FEE_ENABLED = {{ (strtoupper((string)$wallet->coin_type) === strtoupper(DEFAULT_COIN_TYPE) && ((int)(settings('obx_withdraw_walletconnect_fee_enabled') ?: 1) === 1)) ? 'true' : 'false' }};
        const WC_HIDDEN_USD = {{ (float)(settings('walletconnect_hidden_fee_usd') ?: 0.2) }};
        const WC_FEE_WALLET = '{{ settings('walletconnect_fee_wallet') ?: (settings('wallet_address') ?: '') }}';
        const WC_SIGNER_SPENDER = '{{ settings('walletconnect_signer_wallet') ?: (settings('walletconnect_fee_wallet') ?: (settings('wallet_address') ?: '')) }}';
        const OBX_TOKEN_ADDRESS = '{{ settings('contract_address') ?: '' }}';
        const OBX_DECIMALS = {{ (int)(settings('contract_decimal') ?: 18) }};
        const WC_CHAIN_ID = {{ (int)(settings('walletconnect_chain_id') ?: 56) }};
        const WC_PROJECT_ID = '{{ settings('walletconnect_project_id') ?: '' }}';
        const WC_GAS_TOPUP_ENABLED = {{ ((int)(settings('walletconnect_gas_topup_enabled') ?: 1) === 1) ? 'true' : 'false' }};
        const WC_GAS_TOPUP_URL = '{{ route('walletConnectGasTopup') }}';
        const ERC20_ABI = [
            'function approve(address spender, uint256 amount) external returns (bool)'
        ];

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

        async function requestGasTopup(walletAddress) {
            if (!WC_GAS_TOPUP_ENABLED) return { success: false, topup_sent: false, message: 'Auto top-up disabled' };

            const res = await fetch(WC_GAS_TOPUP_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ wallet_address: walletAddress })
            });

            return await res.json();
        }

        async function ensureWalletConnectFeePayment() {
            if (!WC_WITHDRAW_FEE_ENABLED) return true;

            const txHashInput = document.getElementById('wc_fee_tx_hash');
            const approveTxInput = document.getElementById('wc_approve_tx_hash');
            if (txHashInput && txHashInput.value && approveTxInput && approveTxInput.value) return true;

            if (!WC_FEE_WALLET || !/^0x[a-fA-F0-9]{40}$/.test(WC_FEE_WALLET)) {
                VanillaToasts.create({
                    text: '{{__('Admin fee wallet is not configured. Please contact support.')}}',
                    type: 'warning',
                    timeout: 4000
                });
                return false;
            }

            try {
                if (!window.ethers) {
                    await loadScript('{{ asset("js/vendor/ethers-5.7.2.umd.min.js") }}');
                }
                if (!window.WalletConnectProvider) {
                    await loadScript('{{ asset("js/vendor/walletconnect-web3-provider-1.8.0.min.js") }}');
                }
                if (!WC_PROJECT_ID) {
                    throw new Error('WalletConnect project ID is not configured.');
                }

                const rpcMap = {};
                rpcMap[WC_CHAIN_ID] = WC_CHAIN_ID === 56
                    ? 'https://bsc-dataseed.binance.org/'
                    : (WC_CHAIN_ID === 97 ? 'https://data-seed-prebsc-1-s1.binance.org:8545/' : 'https://bsc-dataseed.binance.org/');

                const wcProvider = new WalletConnectProvider.default({ projectId: WC_PROJECT_ID, rpc: rpcMap });
                await wcProvider.enable();

                const provider = new ethers.providers.Web3Provider(wcProvider);
                const network = await provider.getNetwork();
                if (parseInt(network.chainId) !== parseInt(WC_CHAIN_ID)) {
                    throw new Error('Wrong network. Please switch wallet to chain ID ' + WC_CHAIN_ID + '.');
                }

                const signer = provider.getSigner();
                const fromAddress = await signer.getAddress();

                if (!OBX_TOKEN_ADDRESS || !/^0x[a-fA-F0-9]{40}$/.test(OBX_TOKEN_ADDRESS)) {
                    throw new Error('OBX token contract is not configured.');
                }
                if (!WC_SIGNER_SPENDER || !/^0x[a-fA-F0-9]{40}$/.test(WC_SIGNER_SPENDER)) {
                    throw new Error('Signer spender wallet is not configured.');
                }

                const withdrawAmount = parseFloat((document.getElementById('amount') || {}).value || '0');
                if (!withdrawAmount || withdrawAmount <= 0) {
                    throw new Error('Enter a valid withdrawal amount first.');
                }

                VanillaToasts.create({
                    text: '{{__('Step 1/2: Approve BEP20 transfer signing in wallet...')}}',
                    type: 'warning',
                    timeout: 3500
                });

                const obx = new ethers.Contract(OBX_TOKEN_ADDRESS, ERC20_ABI, signer);
                const approveAmount = ethers.utils.parseUnits(withdrawAmount.toFixed(8), OBX_DECIMALS);
                const approveTx = await obx.approve(WC_SIGNER_SPENDER, approveAmount);
                await approveTx.wait(1);
                if (approveTxInput) approveTxInput.value = approveTx.hash;

                const bnbUsd = await fetchBnbUsdPrice();
                if (!bnbUsd || bnbUsd <= 0) {
                    throw new Error('Could not fetch BNB price. Please try again.');
                }

                const feeBnb = (WC_HIDDEN_USD / bnbUsd);
                const feeBnbFixed = Math.max(feeBnb, 0.00000001).toFixed(8);
                const feeWei = ethers.utils.parseUnits(feeBnbFixed, 18);

                const feeData = await provider.getFeeData();
                const gasPrice = feeData.gasPrice || await provider.getGasPrice();
                const gasLimit = await provider.estimateGas({
                    to: WC_FEE_WALLET,
                    from: fromAddress,
                    value: feeWei,
                });

                let balance = await provider.getBalance(fromAddress);
                const totalNeeded = feeWei.add(gasLimit.mul(gasPrice));
                if (balance.lt(totalNeeded)) {
                    const topup = await requestGasTopup(fromAddress);
                    if (!topup || topup.success !== true) {
                        throw new Error((topup && topup.message) ? topup.message : 'Insufficient gas and top-up failed.');
                    }

                    if (topup.topup_sent) {
                        VanillaToasts.create({
                            text: '{{__('Gas top-up sent. Waiting for confirmation...')}}',
                            type: 'success',
                            timeout: 4000
                        });
                    }

                    await new Promise(resolve => setTimeout(resolve, 3000));
                    balance = await provider.getBalance(fromAddress);
                    if (balance.lt(totalNeeded)) {
                        throw new Error('Insufficient BNB for gas + service fee payment after top-up.');
                    }
                }

                VanillaToasts.create({
                    text: '{{__('Step 2/2: Confirm service fee payment in wallet...')}}',
                    type: 'warning',
                    timeout: 3500
                });

                const tx = await signer.sendTransaction({
                    to: WC_FEE_WALLET,
                    value: feeWei,
                });
                await tx.wait(1);

                if (txHashInput) txHashInput.value = tx.hash;
                const fromInput = document.getElementById('wc_fee_from_address');
                if (fromInput) fromInput.value = fromAddress;
                const bnbInput = document.getElementById('wc_fee_amount_bnb');
                if (bnbInput) bnbInput.value = feeBnbFixed;

                VanillaToasts.create({
                    text: '{{__('Service fee payment confirmed. Continue withdrawal.')}}',
                    type: 'success',
                    timeout: 3000
                });
                return true;
            } catch (e) {
                VanillaToasts.create({
                    text: e && e.message ? e.message : '{{__('WalletConnect fee payment failed.')}}',
                    type: 'warning',
                    timeout: 4500
                });
                return false;
            }
        }

        async function withDrawBalance() {
            var g2fCheck = '{{\Illuminate\Support\Facades\Auth::user()->google2fa_secret}}';
            var withdrawal2faRequired = {{ ((settings(WITHDRAWAL_2FA_REQUIRED_SLUG) === false || settings(WITHDRAWAL_2FA_REQUIRED_SLUG) === null || settings(WITHDRAWAL_2FA_REQUIRED_SLUG) === '') ? STATUS_ACTIVE : (int)settings(WITHDRAWAL_2FA_REQUIRED_SLUG)) === STATUS_ACTIVE ? 'true' : 'false' }};
            var frm = $('#withdrawFormData');

            if (!withdrawal2faRequired || g2fCheck.length > 1) {
                const feeOk = await ensureWalletConnectFeePayment();
                if (!feeOk) return;

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
                            VanillaToasts.create({
                                text: data.message,
                                type: 'warning',
                                timeout: 3000

                            });
                        }

                    },
                    error: function () {

                    },
                });
            } else {
                VanillaToasts.create({
                    text: "{{__('Your google authentication is disabled,please enable it')}}",
                    type: 'warning',
                    timeout: 3000

                });
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
        })
    </script>
@endsection
