<div class="header-bar">
    <div class="table-title">
        <h3>{{__('Payment Gateway Settings')}}</h3>
    </div>
</div>

<div class="profile-info-form">
    <form action="{{route('adminSavePaymentSettings')}}" method="post" enctype="multipart/form-data">
        @csrf

        {{-- ─── NOWPayments ─────────────────────────────────────────── --}}
        <div class="settings-section mb-4">
            <h5 class="section-sub-title mb-3 text-info">
                <i class="fa fa-credit-card mr-1"></i> {{__('NOWPayments')}}
                <small class="text-muted ml-2" style="font-size:12px;">{{__('Accept any crypto via NOWPayments')}}</small>
            </h5>
            <div class="row">
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('NOWPayments API Key')}}</label>
                        <input class="form-control" type="text" name="nowpayments_api_key"
                               autocomplete="off"
                               placeholder="{{__('Get from nowpayments.io → Stores → API Keys')}}"
                               value="{{settings('nowpayments_api_key') ?? ''}}">
                        <small class="text-muted">{{__('Required for creating payment invoices.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('NOWPayments IPN Secret')}}</label>
                        <input class="form-control" type="password" name="nowpayments_ipn_secret"
                               autocomplete="off"
                               placeholder="{{__('IPN secret for webhook signature verification')}}"
                               value="{{settings('nowpayments_ipn_secret') ?? ''}}">
                        <small class="text-muted">
                            {{__('Get from nowpayments.io → Stores → IPN Secret.')}}
                            {{__('Webhook URL')}}: <code>{{ url('/api/nowpayments/ipn') }}</code>
                        </small>
                    </div>
                </div>

                <div class="col-lg-3 col-12 mt-20">
                    <div class="form-group">
                        <label class="d-block">{{__('Enable NOWPayments')}}</label>
                        <div class="custom-control custom-switch mt-1">
                            <input type="checkbox" class="custom-control-input" id="nowpayments_enabled"
                                   name="nowpayments_enabled" value="1"
                                   @if(settings('nowpayments_enabled') == '1') checked @endif>
                            <label class="custom-control-label" for="nowpayments_enabled">
                                {{__('Active')}}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-12 mt-20">
                    <div class="form-group">
                        <label class="d-block">{{__('Sandbox / Test Mode')}}</label>
                        <div class="custom-control custom-switch mt-1">
                            <input type="checkbox" class="custom-control-input" id="nowpayments_sandbox_mode"
                                   name="nowpayments_sandbox_mode" value="1"
                                   @if(settings('nowpayments_sandbox_mode') == '1') checked @endif>
                            <label class="custom-control-label" for="nowpayments_sandbox_mode">
                                {{__('Sandbox')}}
                            </label>
                        </div>
                        <small class="text-muted">{{__('Use sandbox.nowpayments.io for test payments.')}}</small>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- ─── WalletConnect ───────────────────────────────────────── --}}
        <div class="settings-section mb-4">
            <h5 class="section-sub-title mb-3 text-success">
                <i class="fa fa-link mr-1"></i> {{__('WalletConnect')}}
                <small class="text-muted ml-2" style="font-size:12px;">{{__('Direct on-chain USDT payment via connected wallet')}}</small>
            </h5>
            <div class="row">
                <div class="col-lg-8 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('WalletConnect Project ID')}}</label>
                        <input class="form-control" type="text" name="walletconnect_project_id"
                               autocomplete="off"
                               placeholder="{{__('Get from cloud.walletconnect.com')}}"
                               value="{{settings('walletconnect_project_id') ?? ''}}">
                        <small class="text-muted">
                            {{__('Create a project at')}}
                            <a href="https://cloud.walletconnect.com" target="_blank">cloud.walletconnect.com</a>
                            {{__('and paste the Project ID here.')}}
                        </small>
                    </div>
                </div>
                <div class="col-lg-4 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('WalletConnect Chain ID')}}</label>
                        <select class="form-control" name="walletconnect_chain_id">
                            <option value="56"  @if((settings('walletconnect_chain_id') ?? '56') == '56')  selected @endif>56 — BSC Mainnet</option>
                            <option value="97"  @if(settings('walletconnect_chain_id') == '97')  selected @endif>97 — BSC Testnet</option>
                            <option value="1"   @if(settings('walletconnect_chain_id') == '1')   selected @endif>1 — Ethereum</option>
                            <option value="137" @if(settings('walletconnect_chain_id') == '137') selected @endif>137 — Polygon</option>
                        </select>
                    </div>
                </div>

                <div class="col-lg-3 col-12 mt-20">
                    <div class="form-group">
                        <label class="d-block">{{__('Enable WalletConnect')}}</label>
                        <div class="custom-control custom-switch mt-1">
                            <input type="checkbox" class="custom-control-input" id="walletconnect_enabled"
                                   name="walletconnect_enabled" value="1"
                                   @if(settings('walletconnect_enabled') == '1') checked @endif>
                            <label class="custom-control-label" for="walletconnect_enabled">
                                {{__('Active')}}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- ─── On-Chain Contracts & Network ───────────────────────── --}}
        <div class="settings-section mb-4">
            <h5 class="section-sub-title mb-3 text-warning">
                <i class="fa fa-cube mr-1"></i> {{__('On-Chain Contracts & Network')}}
                <small class="text-muted ml-2" style="font-size:12px;">{{__('Primary source for all blockchain operations — overrides .env values')}}</small>
            </h5>

            {{-- Chain / RPC --}}
            <div class="row">
                <div class="col-lg-4 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Chain ID')}}</label>
                        @php $pChain = settings('presale_chain_id') ?: config('blockchain.presale_chain_id', 56); @endphp
                        <select class="form-control" name="presale_chain_id">
                            <option value="56"  @if($pChain == '56')  selected @endif>56 — BSC Mainnet</option>
                            <option value="97"  @if($pChain == '97')  selected @endif>97 — BSC Testnet</option>
                            <option value="1"   @if($pChain == '1')   selected @endif>1 — Ethereum</option>
                            <option value="137" @if($pChain == '137') selected @endif>137 — Polygon</option>
                        </select>
                        <small class="text-muted">{{__('Network chain ID for all on-chain operations.')}}</small>
                    </div>
                </div>
                <div class="col-lg-8 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('RPC URL')}}</label>
                        <input class="form-control" type="text" name="bsc_rpc_url"
                               placeholder="https://bsc-dataseed.binance.org/"
                               value="{{settings('bsc_rpc_url') ?: config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/')}}">
                        <small class="text-muted">{{__('JSON-RPC endpoint used for all eth_call and event polling.')}}</small>
                    </div>
                </div>
            </div>

            {{-- Contracts --}}
            <div class="row">
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('OBX Token Contract')}}</label>
                        <input class="form-control" type="text" name="contract_address"
                               placeholder="0x…"
                               value="{{settings('contract_address') ?: config('blockchain.obx_token_contract')}}">
                        <small class="text-muted">{{__('OBXToken.sol — used for withdrawals, transfers and presale funding.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Presale Contract')}}</label>
                        <input class="form-control" type="text" name="presale_contract"
                               placeholder="0x…"
                               value="{{settings('presale_contract') ?: config('blockchain.presale_contract')}}">
                        <small class="text-muted">{{__('OBXPresale.sol — handles ICO phases and token sales.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Airdrop Contract')}}</label>
                        <input class="form-control" type="text" name="airdrop_contract"
                               placeholder="0x…"
                               value="{{settings('airdrop_contract') ?: ''}}">
                        <small class="text-muted">{{__('OBXAirdrop.sol — used for airdrop distributions.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Staking Contract')}}</label>
                        <input class="form-control" type="text" name="staking_contract"
                               placeholder="0x…"
                               value="{{settings('staking_contract') ?: ''}}">
                        <small class="text-muted">{{__('OBXStaking.sol — used for staking pool management.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Treasury Wallet Address')}}</label>
                        <input class="form-control" type="text" name="treasury_wallet"
                               placeholder="0x…"
                               value="{{settings('treasury_wallet') ?: ''}}">
                        <small class="text-muted">{{__('Wallet that receives USDT from presale sales.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('BSCScan API Key')}}</label>
                        <input class="form-control" type="text" name="bscscan_api_key"
                               autocomplete="off"
                               placeholder="{{__('Get from bscscan.com → My Account → API Keys')}}"
                               value="{{settings('bscscan_api_key') ?: ''}}">
                        <small class="text-muted">{{__('Used for querying TokensPurchased events (presale sync).')}}</small>
                    </div>
                </div>
            </div>

            {{-- Admin wallet & scan start block --}}
            <div class="row">
                <div class="col-lg-8 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Owner / Admin Private Key')}}</label>
                        <input class="form-control" type="password" name="owner_private_key"
                               autocomplete="new-password"
                               placeholder="0x…"
                               value="{{settings('owner_private_key') ?: ''}}">
                        <small class="text-danger">
                            <i class="fa fa-lock mr-1"></i>
                            {{__('Keep this secret. Used to sign phase management transactions (addPhase, updatePhase, fundPresale).')}}
                        </small>
                    </div>
                </div>
                <div class="col-lg-4 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Presale Start Block')}}</label>
                        <input class="form-control number_only" type="text" name="presale_start_block"
                               placeholder="0"
                               value="{{settings('presale_start_block') ?? 0}}">
                        <small class="text-muted">{{__('Block number at contract deployment — limits event scan range.')}}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-lg-2 col-12">
                <button type="submit" class="button-primary theme-btn">{{__('Save Settings')}}</button>
            </div>
        </div>
    </form>
</div>

<div class="profile-info-form">
    <form action="{{route('adminSavePaymentSettings')}}" method="post"
          enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('COIN PAYMENT PUBLIC KEY')}}</label>
                    <input class="form-control" type="text" name="COIN_PAYMENT_PUBLIC_KEY"
                           autocomplete="off" placeholder=""
                           value="{{settings('COIN_PAYMENT_PUBLIC_KEY')}}">
                </div>
            </div>
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('COIN PAYMENT PRIVATE KEY')}}</label>
                    <input class="form-control" type="text" name="COIN_PAYMENT_PRIVATE_KEY"
                           autocomplete="off" placeholder=""
                           value="{{settings('COIN_PAYMENT_PRIVATE_KEY')}}">
                </div>
            </div>
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('COIN PAYMENT IPN MERCHANT ID')}}</label>
                    <input class="form-control" type="text" name="ipn_merchant_id"
                           autocomplete="off" placeholder=""
                           value="{{isset(settings()['ipn_merchant_id']) ? settings('ipn_merchant_id') : ''}}">
                </div>
            </div>
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('COIN PAYMENT IPN SECRET')}}</label>
                    <input class="form-control" type="text" name="ipn_secret"
                           autocomplete="off" placeholder=""
                           value="{{isset(settings()['ipn_secret']) ? settings('ipn_secret') : ''}}">
                </div>
            </div>
            {{--                                    <div class="col-lg-6 col-12  mt-20">--}}
            {{--                                        <div class="form-group">--}}
            {{--                                            <label for="#">{{__('Coin Payment Base Coin Type')}}</label>--}}
            {{--                                            <input class="form-control" type="text" name="base_coin_type"--}}
            {{--                                                   placeholder="{{__('Coin Type eg. BTC')}}"--}}
            {{--                                                   value="{{isset($settings['base_coin_type']) ? $settings['base_coin_type'] : ''}}">--}}
            {{--                                        </div>--}}
            {{--                                    </div>--}}
        </div>
        <hr>
        <div class="header-bar">
            <div class="table-title">
                <h3>{{__('Stripe Details')}}</h3>
            </div>
        </div>
        <div class="row">

            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('STRIPE PUBLISHABLE KEY')}}</label>
                    <input class="form-control" type="text" name="STRIPE_KEY"
                           autocomplete="off" placeholder=""
                           value="{{isset(settings()['STRIPE_KEY']) ? settings()['STRIPE_KEY'] : ''}}">
                </div>
            </div>
            <div class="col-lg-6 col-12 mt-20">
                <div class="form-group">
                    <label for="#">{{__('STRIPE SECRET KEY')}}</label>
                    <input class="form-control" type="text" name="STRIPE_SECRET"
                           autocomplete="off" placeholder=""
                           value="{{isset(settings()['STRIPE_SECRET']) ? settings()['STRIPE_SECRET'] : ''}}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-2 col-12 mt-20">
                <button type="submit" class="button-primary theme-btn">{{__('Update')}}</button>
            </div>
        </div>
    </form>
</div>
