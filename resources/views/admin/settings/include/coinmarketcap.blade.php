<div class="header-bar">
    <div class="table-title">
        <h3>{{__('CoinMarketCap Integration')}}</h3>
    </div>
</div>

<div class="profile-info-form">
    <form action="{{route('adminCommonSettings')}}" method="post" enctype="multipart/form-data">
        @csrf

        {{-- ─── API credentials ───────────────────────────────────────────── --}}
        <div class="settings-section mb-4">
            <h5 class="section-sub-title mb-3 text-info">
                <i class="fa fa-key mr-1"></i> {{__('CMC API Credentials')}}
            </h5>
            <div class="row">
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('CMC Pro API Key')}}</label>
                        <input class="form-control" type="text" name="coinmarketcap_api_key"
                               autocomplete="off"
                               placeholder="{{__('Get from coinmarketcap.com → Developers → API Keys')}}"
                               value="{{settings('coinmarketcap_api_key') ?? ''}}">
                        <small class="text-muted">
                            {{__('Used to pull live OBX price every 5 minutes and to report circulating supply.')}}
                            <a href="https://coinmarketcap.com/api/" target="_blank" rel="noopener noreferrer">{{__('Get a free key →')}}</a>
                        </small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('OBX CMC Listing ID')}}</label>
                        <input class="form-control" type="number" name="coinmarketcap_obx_id"
                               placeholder="{{__('Numeric CMC ID, e.g. 1 = Bitcoin, 1027 = ETH')}}"
                               value="{{settings('coinmarketcap_obx_id') ?? ''}}">
                        <small class="text-muted">
                            {{__('Find it on your CMC listing page URL: coinmarketcap.com/currencies/your-token-name/')}}
                            {{__('Leave blank to use coin_symbol for lookup (may be ambiguous).')}}
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Token supply info ─────────────────────────────────────────── --}}
        <div class="settings-section mb-4">
            <h5 class="section-sub-title mb-3 text-info">
                <i class="fa fa-database mr-1"></i> {{__('Token Supply (Self-Reported to CMC)')}}
            </h5>
            <div class="row">
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Total Supply (OBX)')}}</label>
                        <input class="form-control" type="number" name="obx_total_supply" step="any"
                               placeholder="100000000"
                               value="{{settings('obx_total_supply') ?? '100000000'}}">
                        <small class="text-muted">{{__('Initial OBX total supply. Used when reporting to CMC.')}}</small>
                    </div>
                </div>
                <div class="col-lg-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Circulating Supply (OBX)')}}</label>
                        <input class="form-control" type="number" name="obx_circulating_supply" step="any"
                               placeholder="{{__('Auto-updated by cmc:fetch-price')}}"
                               value="{{settings('obx_circulating_supply') ?? ''}}">
                        <small class="text-muted">{{__('Auto-fetched from CMC every 5 min. You can override manually.')}}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Live price status ─────────────────────────────────────────── --}}
        <div class="settings-section mb-4">
            <h5 class="section-sub-title mb-3 text-info">
                <i class="fa fa-line-chart mr-1"></i> {{__('Live Price Status (read-only)')}}
            </h5>
            <div class="row">
                <div class="col-lg-3 col-md-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Current Price (USD)')}}</label>
                        <input class="form-control" type="text" readonly
                               value="${{settings('coin_price') ?? '—'}}">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('24h Change')}}</label>
                        <input class="form-control" type="text" readonly
                               value="{{ (settings('obx_price_change_24h') !== null ? settings('obx_price_change_24h') . '%' : '—') }}">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Market Cap (USD)')}}</label>
                        <input class="form-control" type="text" readonly
                               value="{{ settings('obx_market_cap') ? '$' . number_format((float)settings('obx_market_cap'), 2) : '—' }}">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12 mt-20">
                    <div class="form-group">
                        <label>{{__('Last CMC Update')}}</label>
                        <input class="form-control" type="text" readonly
                               value="{{ settings('obx_price_last_updated') ?? '—' }}">
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <small class="text-muted">
                        <i class="fa fa-refresh mr-1"></i>
                        {{__('Price auto-refreshes every 5 minutes via the')}} <code>cmc:fetch-price</code> {{__('scheduled command.')}}
                        <br>
                        {{__('To run immediately:')}} <code>php artisan cmc:fetch-price</code>
                        &nbsp;|&nbsp;
                        {{__('CMC integration endpoints:')}}
                        <a href="{{ url('/api/cmc/summary') }}" target="_blank">/api/cmc/summary</a>,
                        <a href="{{ url('/api/cmc/ticker') }}" target="_blank">/api/cmc/ticker</a>
                    </small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mt-20">
                <button type="submit" class="btn btn-primary">{{__('Save CMC Settings')}}</button>
            </div>
        </div>
    </form>
</div>
