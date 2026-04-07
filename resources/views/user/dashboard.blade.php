@extends('user.master',['menu'=>'dashboard'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
/* ---- Dashboard page styles ---- */
.welcome-card {
    background: linear-gradient(135deg, #1c2333 0%, #21262d 100%);
    border: 1px solid rgba(99,102,241,.25);
    border-radius: 14px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 22px;
}
.welcome-card .wc-info h5 {
    font-size: 17px;
    font-weight: 700;
    color: #e6edf3;
    margin: 0 0 4px;
}
.welcome-card .wc-info p {
    font-size: 12.5px;
    color: #7d8590;
    margin: 0;
}
.welcome-card .wc-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.welcome-card .wc-actions a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all .15s;
}
.wc-btn-primary { background: #6366f1; color: #fff !important; }
.wc-btn-primary:hover { background: #4f46e5; color: #fff !important; }
.wc-btn-outline { background: transparent; border: 1px solid rgba(99,102,241,.4); color: #a5b4fc !important; }
.wc-btn-outline:hover { background: rgba(99,102,241,.12); color: #a5b4fc !important; }

/* stat cards */
.dash-stat-card {
    background: #161b22;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: transform .15s, box-shadow .15s;
}
.dash-stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.4); }
.dash-stat-card .dsc-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.dsc-icon-blue  { background: rgba(99,102,241,.18); color: #a5b4fc; }
.dsc-icon-green { background: rgba(34,197,94,.18);  color: #4ade80; }
.dsc-icon-amber { background: rgba(245,158,11,.18);  color: #fbbf24; }
.dash-stat-card .dsc-body { flex: 1; min-width: 0; }
.dash-stat-card .dsc-label {
    font-size: 11.5px; font-weight: 500; color: #7d8590;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px;
}
.dash-stat-card .dsc-value {
    font-size: 20px; font-weight: 700; color: #e6edf3; line-height: 1.2;
}
.dash-stat-card .dsc-sub {
    font-size: 11px; color: #7d8590; margin-top: 2px;
}

/* chart cards */
.chart-card {
    background: #161b22;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    padding: 18px 20px;
}
.chart-card .chart-card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 4px;
}
.chart-card .chart-card-header h5 {
    font-size: 14px; font-weight: 600; color: #e6edf3; margin: 0;
}
.chart-card .chart-subtitle {
    font-size: 11.5px; color: #7d8590; margin-bottom: 14px;
}

/* transaction table card */
.tx-card {
    background: #161b22;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    overflow: hidden;
}
.tx-card-header {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.tx-card-header h5 { font-size: 14px; font-weight: 600; color: #e6edf3; margin: 0; }
.tx-tabs .nav-link {
    font-size: 12.5px; font-weight: 500; color: #7d8590;
    padding: 5px 14px; border-radius: 6px; border: none;
    transition: all .15s;
}
.tx-tabs .nav-link.active, .tx-tabs .nav-link:hover {
    background: rgba(99,102,241,.15); color: #a5b4fc;
}
.tx-body { padding: 16px 20px; }

/* tradingview */
.tv-ticker-wrap {
    border-radius: 12px; overflow: hidden;
    border: 1px solid rgba(255,255,255,.08);
    margin-bottom: 22px;
}
</style>
@endsection
@section('content')

{{-- Welcome card --}}
<div class="welcome-card">
    <div class="wc-info">
        <h5>{{__('Welcome back')}}, {{ Auth::user()->name }} 👋</h5>
        <p>{{__('Here\'s an overview of your OBXCoin account activity.')}}</p>
    </div>
    <div class="wc-actions">
        <a href="{{route('buyCoin')}}" class="wc-btn-primary">
            <i class="fa fa-shopping-cart"></i> {{__('Buy OBXCoin')}}
        </a>
        <a href="{{route('requestCoin')}}" class="wc-btn-outline">
            <i class="fa fa-exchange"></i> {{__('Send')}}
        </a>
        <a href="{{route('myPocket')}}" class="wc-btn-outline">
            <i class="fa fa-credit-card"></i> {{__('Wallet')}}
        </a>
    </div>
</div>

{{-- TradingView ticker --}}
<div class="tv-ticker-wrap">
    <div class="tradingview-widget-container">
        <div class="tradingview-widget-container__widget"></div>
        <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js" async>
        {
            "symbols": [
                {"proName": "FOREXCOM:SPXUSD","title": "S&P 500"},
                {"proName": "FOREXCOM:NSXUSD","title": "Nasdaq 100"},
                {"proName": "FX_IDC:EURUSD","title": "EUR/USD"},
                {"proName": "BITSTAMP:BTCUSD","title": "BTC/USD"},
                {"proName": "BITSTAMP:ETHUSD","title": "ETH/USD"}
            ],
            "colorTheme": "dark",
            "isTransparent": true,
            "displayMode": "adaptive",
            "locale": "en"
        }
        </script>
    </div>
</div>

{{-- Stat cards --}}
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 col-12 mb-3 mb-xl-0">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-blue">
                <i class="fa fa-coins" style="font-size:18px;"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('Available OBXCoin')}}</div>
                <div class="dsc-value">{{number_format($balance['available_coin'],2)}}</div>
                <div class="dsc-sub">{{__('Used')}}: {{number_format($balance['available_used'],2)}}</div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 col-12 mb-3 mb-xl-0">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-green">
                <i class="fa fa-lock"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('Blocked Coin')}}</div>
                <div class="dsc-value">{{number_format(get_blocked_coin(Auth::id()),2)}}</div>
                <div class="dsc-sub">{{__('Pending / locked funds')}}</div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 col-12">
        <div class="dash-stat-card">
            <div class="dsc-icon dsc-icon-amber">
                <i class="fa fa-shopping-cart"></i>
            </div>
            <div class="dsc-body">
                <div class="dsc-label">{{__('Total Purchased')}}</div>
                <div class="dsc-value">{{number_format($total_buy_coin,2)}}</div>
                <div class="dsc-sub">{{__('All-time buy total')}}</div>
            </div>
        </div>
    </div>
</div>

{{-- Charts row --}}
<div class="row mb-4">
    <div class="col-xl-6 mb-4 mb-xl-0">
        <div class="chart-card h-100">
            <div class="chart-card-header">
                <h5><i class="fa fa-arrow-down" style="color:#4ade80;margin-right:6px;font-size:12px;"></i>{{__('Deposits')}}</h5>
                <span class="chart-subtitle" style="margin:0;">{{__('Current Year')}}</span>
            </div>
            <div class="chart-subtitle">{{__('Monthly deposit activity')}}</div>
            <canvas id="depositChart"></canvas>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="chart-card h-100">
            <div class="chart-card-header">
                <h5><i class="fa fa-arrow-up" style="color:#f87171;margin-right:6px;font-size:12px;"></i>{{__('Withdrawals')}}</h5>
                <span class="chart-subtitle" style="margin:0;">{{__('Current Year')}}</span>
            </div>
            <div class="chart-subtitle">{{__('Monthly withdrawal activity')}}</div>
            <canvas id="withdrawalChart"></canvas>
        </div>
    </div>
</div>

{{-- Buy Coin chart --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="chart-card-header">
                <h5><i class="fa fa-bar-chart" style="color:#a5b4fc;margin-right:6px;font-size:12px;"></i>{{__('OBXCoin Purchase Report')}}</h5>
                <span class="chart-subtitle" style="margin:0;">{{__('Current Year')}}</span>
            </div>
            <div class="chart-subtitle">{{__('Monthly buy coin volume')}}</div>
            <canvas id="myBarChart"></canvas>
        </div>
    </div>
</div>

{{-- Transaction history --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="tx-card">
            <div class="tx-card-header">
                <h5 id="list_title">{{__('Transaction History')}}</h5>
                <ul class="nav tx-tabs" id="myTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="deposit-tab" data-toggle="tab"
                           onclick="$('#list_title').html('Deposit History')"
                           href="#deposit" role="tab" aria-controls="deposit" aria-selected="true">
                            <i class="fa fa-arrow-down" style="font-size:10px;"></i> {{__('Deposits')}}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="withdraw-tab" data-toggle="tab"
                           onclick="$('#list_title').html('Withdrawal History')"
                           href="#withdraw" role="tab" aria-controls="withdraw" aria-selected="false">
                            <i class="fa fa-arrow-up" style="font-size:10px;"></i> {{__('Withdrawals')}}
                        </a>
                    </li>
                </ul>
            </div>
            <div class="tx-body">
                <div class="tab-content">
                    <div id="deposit" class="tab-pane fade in active show">
                        <div class="cp-user-transaction-history-table table-responsive">
                            <table class="table" id="table">
                                <thead>
                                    <tr>
                                        <th>{{__('Address')}}</th>
                                        <th>{{__('Amount')}}</th>
                                        <th>{{__('Transaction Hash')}}</th>
                                        <th>{{__('Status')}}</th>
                                        <th>{{__('Created At')}}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="withdraw" class="tab-pane fade in">
                        <div class="cp-user-transaction-history-table table-responsive">
                            <table class="table" id="withdraw_table">
                                <thead>
                                    <tr>
                                        <th>{{__('Address')}}</th>
                                        <th>{{__('Amount')}}</th>
                                        <th>{{__('Transaction Hash')}}</th>
                                        <th>{{__('Status')}}</th>
                                        <th>{{__('Created At')}}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

        @section('script')
            <script src="{{asset('assets/chart/chart.min.js')}}"></script>
            <script src="{{asset('assets/chart/anychart-base.min.js')}}"></script>
            <!-- Resources -->
            <script src="{{asset('assets/chart/amchart.core.js')}}"></script>
            <script src="{{asset('assets/chart/amchart.charts.js')}}"></script>
            <script src="{{asset('assets/chart/amchart.animated.js')}}"></script>
            <script>
                anychart.onDocumentReady(function () {
                    var chart = anychart.pie([
                        {x: "Complete", value: {!! $completed_withdraw !!}},
                        {x: "Pending", value: {!! $pending_withdraw !!}},
                    ]);
                    chart.innerRadius("60%");
                    var label = anychart.standalones.label();
                    label.text({!! json_encode($pending_withdraw) !!});
                    label.width("100%");
                    label.height("100%");
                    label.adjustFontSize(true);
                    label.fontColor("#7d8590");
                    label.hAlign("center");
                    label.vAlign("middle");
                    chart.center().content(label);
                    chart.container('circle');
                    chart.draw();
                });
            </script>
            <script>
                am4core.ready(function () {
                    am4core.useTheme(am4themes_animated);
                    var chart = am4core.create("container", am4charts.XYChart);
                    chart.data = {!! json_encode($sixmonth_diposites) !!};
                    var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
                    categoryAxis.dataFields.category = "country";
                    categoryAxis.renderer.grid.template.location = 0;
                    categoryAxis.renderer.minGridDistance = 30;
                    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
                    valueAxis.title.text = "Deposit and withdraw";
                    valueAxis.title.fontWeight = 800;
                    var series = chart.series.push(new am4charts.ColumnSeries());
                    series.dataFields.valueY = "year2004";
                    series.dataFields.categoryX = "country";
                    series.clustered = false;
                    series.tooltipText = "Deposit {categoryX}: [bold]{valueY}[/]";
                    var series2 = chart.series.push(new am4charts.ColumnSeries());
                    series2.dataFields.valueY = "year2005";
                    series2.dataFields.categoryX = "country";
                    series2.clustered = false;
                    series2.columns.template.width = am4core.percent(50);
                    series2.tooltipText = "Withdraw {categoryX}: [bold]{valueY}[/]";
                    chart.cursor = new am4charts.XYCursor();
                    chart.cursor.lineX.disabled = true;
                    chart.cursor.lineY.disabled = true;
                }); // end am4core.ready()
            </script>

            <script>
                $(document).ready(function () {
                    $('#withdraw_table').DataTable({
                        processing: true,
                        serverSide: true,
                        pageLength: 10,
                        bLengthChange: true,
                        responsive: false,
                        ajax: '{{route('transactionHistories')}}?type=withdraw',
                        order: [4, 'desc'],
                        autoWidth: false,
                        language: {
                            paginate: {
                                next: 'Next &#8250;',
                                previous: '&#8249; Previous'
                            }
                        },
                        columns: [
                            {"data": "address", "orderable": false},
                            {"data": "amount", "orderable": false},
                            {"data": "hashKey", "orderable": false},
                            {"data": "status", "orderable": false},
                            {"data": "created_at", "orderable": false}
                        ],
                    });
                });
            </script>

            <script>
                $(document).ready(function () {
                    $('#table').DataTable({
                        processing: true,
                        serverSide: true,
                        pageLength: 10,
                        retrieve: true,
                        bLengthChange: true,
                        responsive: false,
                        ajax: '{{route('transactionHistories')}}?type=deposit',
                        order: [4, 'desc'],
                        autoWidth: false,
                        language: {
                            paginate: {
                                next: 'Next &#8250;',
                                previous: '&#8249; Previous'
                            }
                        },
                        columns: [
                            {"data": "address", "orderable": false},
                            {"data": "amount", "orderable": false},
                            {"data": "hashKey", "orderable": false},
                            {"data": "status", "orderable": false},
                            {"data": "created_at", "orderable": false}
                        ],
                    });
                });
            </script>
            <script>
                var ctx = document.getElementById('depositChart').getContext("2d");
                var depositChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Monthly Deposit",
                            borderColor: "#4ade80",
                            pointBorderColor: "#4ade80",
                            pointBackgroundColor: "#4ade80",
                            pointHoverBackgroundColor: "#4ade80",
                            pointHoverBorderColor: "#21262d",
                            pointBorderWidth: 3,
                            pointHoverRadius: 4,
                            pointHoverBorderWidth: 2,
                            pointRadius: 3,
                            fill: true,
                            backgroundColor: "rgba(74,222,128,.07)",
                            borderWidth: 2.5,
                            data: {!! json_encode($monthly_deposit) !!}
                        }]
                    },
                    options: {
                        legend: { position: "bottom", display: true, labels: { fontColor: '#7d8590' } },
                        scales: {
                            yAxes: [{
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", beginAtZero: true, padding: 20 },
                                gridLines: { drawTicks: false, color: "rgba(255,255,255,.05)", zeroLineColor: "rgba(255,255,255,.1)" }
                            }],
                            xAxes: [{
                                gridLines: { display: false, drawTicks: false },
                                ticks: { padding: 20, fontColor: "#7d8590", fontStyle: "bold" }
                            }]
                        }
                    }
                });
            </script>
            <script>
                var ctx2 = document.getElementById('withdrawalChart').getContext("2d");
                var withdrawalChart = new Chart(ctx2, {
                    type: 'line',
                    data: {
                        labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Monthly Withdrawal",
                            borderColor: "#f87171",
                            pointBorderColor: "#f87171",
                            pointBackgroundColor: "#f87171",
                            pointHoverBackgroundColor: "#f87171",
                            pointHoverBorderColor: "#21262d",
                            pointBorderWidth: 3,
                            pointHoverRadius: 4,
                            pointHoverBorderWidth: 2,
                            pointRadius: 3,
                            fill: true,
                            backgroundColor: "rgba(248,113,113,.07)",
                            borderWidth: 2.5,
                            data: {!! json_encode($monthly_withdrawal) !!}
                        }]
                    },
                    options: {
                        legend: { position: "bottom", display: true, labels: { fontColor: '#7d8590' } },
                        scales: {
                            yAxes: [{
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", beginAtZero: true },
                                gridLines: { drawTicks: false, color: "rgba(255,255,255,.05)", zeroLineColor: "rgba(255,255,255,.1)" }
                            }],
                            xAxes: [{
                                gridLines: { display: false, drawTicks: false },
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", autoSkip: false }
                            }]
                        }
                    }
                });
            </script>
            <script>
                var ctx3 = document.getElementById('myBarChart').getContext("2d");
                var myBarChart = new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Monthly Buy Coin",
                            backgroundColor: "rgba(99,102,241,.75)",
                            borderColor: "#6366f1",
                            borderWidth: 1,
                            borderRadius: 5,
                            data: {!! json_encode($monthly_buy_coin) !!}
                        }]
                    },
                    options: {
                        legend: { position: "bottom", display: true, labels: { fontColor: '#7d8590' } },
                        scales: {
                            yAxes: [{
                                ticks: { fontColor: "#7d8590", fontStyle: "bold", beginAtZero: true, maxTicksLimit: 8, padding: 20 },
                                gridLines: { drawTicks: false, color: "rgba(255,255,255,.05)", zeroLineColor: "rgba(255,255,255,.1)" }
                            }],
                            xAxes: [{
                                gridLines: { color: "rgba(99,102,241,.1)", zeroLineColor: "#6366f1" },
                                ticks: { padding: 10, fontColor: "#7d8590", fontStyle: "bold" }
                            }]
                        }
                    }
                });
            </script>
@endsection
