@extends('admin.master',['menu'=>'dashboard'])
@section('title', isset($title) ? $title : '')
@section('style')
<style>
.admin-dash-shell {
    padding: 6px 0 2px;
}
.admin-hero {
    background: linear-gradient(135deg, #1b2130 0%, #1d2537 100%);
    border: 1px solid rgba(93, 88, 231, .28);
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}
.admin-hero h4 {
    color: #e7ebff;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 4px;
}
.admin-hero p {
    color: #9aa4bf;
    margin: 0;
    font-size: 12.5px;
}
.admin-chip-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.admin-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 11px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, .14);
    color: #c8d1eb;
    font-size: 11px;
    font-weight: 600;
    background: rgba(255, 255, 255, .03);
}
.admin-stat-card {
    background: #151b27;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 90px;
}
.admin-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.icon-blue { background: rgba(93, 88, 231, .18); color: #a8b0ff; }
.icon-green { background: rgba(34, 197, 94, .16); color: #4ade80; }
.icon-red { background: rgba(244, 63, 94, .18); color: #fb7185; }
.icon-amber { background: rgba(245, 158, 11, .18); color: #fbbf24; }
.icon-cyan { background: rgba(6, 182, 212, .18); color: #67e8f9; }
.icon-pink { background: rgba(236, 72, 153, .16); color: #f9a8d4; }
.admin-stat-body {
    min-width: 0;
}
.admin-stat-label {
    font-size: 11px;
    color: #96a0bb;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 2px;
}
.admin-stat-value {
    color: #ebefff;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.2;
    word-break: break-word;
}
.admin-chart-card {
    background: #151b27;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 16px 18px;
    height: 100%;
}
.admin-chart-card h5 {
    color: #dbe3ff;
    font-size: 14px;
    margin: 0;
    font-weight: 600;
}
.admin-subtitle {
    color: #8f99b4;
    font-size: 11.5px;
    margin: 6px 0 12px;
}
.admin-table-card {
    background: #151b27;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 0;
    overflow: hidden;
}
.admin-table-header {
    padding: 14px 18px;
    border-bottom: 1px solid rgba(255, 255, 255, .08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.admin-table-header h5 {
    color: #dbe3ff;
    font-size: 14px;
    margin: 0;
    font-weight: 600;
}
.admin-table-body {
    padding: 12px 12px 6px;
}
.admin-table-body .dataTables_wrapper {
    color: #cfd6ef;
}
.admin-table-body .custom-table thead th {
    color: #b5bfdc;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .45px;
}
.admin-table-body .custom-table tbody td {
    color: #d8e0f8;
}
</style>
@endsection
@section('content')
    <div class="admin-dash-shell">
        <div class="custom-breadcrumb mb-2">
            <div class="row">
                <div class="col-12">
                    <ul>
                        <li class="active-item">{{__('Dashboard')}}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="admin-hero">
            <div>
                <h4>{{__('Operations Command Center')}}</h4>
                <p>{{__('Real-time snapshot of sales, membership, revenue, and withdrawal activity.')}}</p>
            </div>
            <div class="admin-chip-row">
                <span class="admin-chip">{{__('Total Users')}}: {{number_format($total_user)}}</span>
                <span class="admin-chip">{{__('Total Revenue')}}: {{number_format($total_income, 6)}}</span>
                <span class="admin-chip">{{__('Total Membership')}}: {{number_format($total_member)}}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-md-6 col-12 mb-3">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon icon-blue"><i class="fa fa-line-chart"></i></div>
                    <div class="admin-stat-body">
                        <div class="admin-stat-label">{{__('Total Sold Coin')}}</div>
                        <div class="admin-stat-value">{{number_format($total_sold_coin,4)}}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-3">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon icon-green"><i class="fa fa-lock"></i></div>
                    <div class="admin-stat-body">
                        <div class="admin-stat-label">{{__('Total Blocked Coin')}}</div>
                        <div class="admin-stat-value">{{number_format($total_blocked_coin,4)}}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-3">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon icon-red"><i class="fa fa-users"></i></div>
                    <div class="admin-stat-body">
                        <div class="admin-stat-label">{{__('Total User')}}</div>
                        <div class="admin-stat-value">{{$total_user}}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-3">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon icon-amber"><i class="fa fa-id-badge"></i></div>
                    <div class="admin-stat-body">
                        <div class="admin-stat-label">{{__('Total Membership')}}</div>
                        <div class="admin-stat-value">{{ $total_member }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-3">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon icon-cyan"><i class="fa fa-gift"></i></div>
                    <div class="admin-stat-body">
                        <div class="admin-stat-label">{{__('Total Distributed Bonus')}}</div>
                        <div class="admin-stat-value">{{number_format($bonus_distribution,6)}}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12 mb-3">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon icon-pink"><i class="fa fa-money"></i></div>
                    <div class="admin-stat-body">
                        <div class="admin-stat-label">{{__('Total Revenue')}}</div>
                        <div class="admin-stat-value">{{number_format($total_income,6)}}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-lg-6 mb-3">
                <div class="admin-chart-card">
                    <h5>{{__('Active User')}}</h5>
                    <p class="admin-subtitle">{{__('Active user ratio over total users')}}</p>
                    <div id="active-user-chart"></div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="admin-chart-card">
                    <h5>{{__('Inactive User')}}</h5>
                    <p class="admin-subtitle">{{__('Inactive user ratio over total users')}}</p>
                    <div id="deleted-user-chart"></div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="admin-chart-card">
                    <h5>{{__('Deposit')}}</h5>
                    <p class="admin-subtitle">{{__('Current Year')}}</p>
                    <canvas id="depositChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="admin-chart-card">
                    <h5>{{__('Withdrawal')}}</h5>
                    <p class="admin-subtitle">{{__('Current Year')}}</p>
                    <canvas id="withdrawalChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-12">
                <div class="admin-table-card">
                    <div class="admin-table-header">
                        <h5>{{__('Pending Withdrawal')}}</h5>
                    </div>
                    <div class="admin-table-body">
                        <div class="table-responsive">
                            <table id="pending_withdrwall" class="table table-borderless custom-table display text-left" width="100%">
                                <thead>
                                    <tr>
                                        <th class="all">{{__('Type')}}</th>
                                        <th class="all">{{__('Sender')}}</th>
                                        <th class="all">{{__('Address')}}</th>
                                        <th class="all">{{__('Receiver')}}</th>
                                        <th class="all">{{__('Amount')}}</th>
                                        <th class="all">{{__('Fees')}}</th>
                                        <th class="all">{{__('Transaction Id')}}</th>
                                        <th class="all">{{__('Update Date')}}</th>
                                        <th class="all">{{__('Actions')}}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
    </div>

@endsection

@section('script')
    <script src="{{asset('assets/chart/chart.min.js')}}"></script>
    <script>
        var ctx = document.getElementById('depositChart').getContext("2d")
        var depositChart = new Chart(ctx, {
            type: 'line',
            yaxisname: "Monthly Deposit",

            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul","Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Monthly Deposit",
                    borderColor: "#1cf676",
                    pointBorderColor: "#1cf676",
                    pointBackgroundColor: "#1cf676",
                    pointHoverBackgroundColor: "#1cf676",
                    pointHoverBorderColor: "#D1D1D1",
                    pointBorderWidth: 4,
                    pointHoverRadius: 2,
                    pointHoverBorderWidth: 1,
                    pointRadius: 3,
                    fill: false,
                    borderWidth: 3,
                    data: {!! json_encode($monthly_deposit) !!}
                }]
            },
            options: {
                legend: {
                    position: "bottom",
                    display: true,
                    labels: {
                        fontColor: '#928F8F'
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            beginAtZero: true,
                            // maxTicksLimit: 5,
                            padding: 20
                        },
                        gridLines: {
                            drawTicks: false,
                            display: false
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            zeroLineColor: "transparent",
                            drawTicks: false,
                            display: false
                        },
                        ticks: {
                            padding: 20,
                            fontColor: "#928F8F",
                            fontStyle: "bold"
                        }
                    }]
                }
            }
        });
    </script>
    <script>
        var ctx = document.getElementById('withdrawalChart').getContext("2d");
        var withdrawalChart = new Chart(ctx, {
            type: 'line',
            yaxisname: "Monthly Withdrawal",

            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul","Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Monthly Withdrawal",
                    borderColor: "#f691be",
                    pointBorderColor: "#f691be",
                    pointBackgroundColor: "#f691be",
                    pointHoverBackgroundColor: "#f691be",
                    pointHoverBorderColor: "#D1D1D1",
                    pointBorderWidth: 4,
                    pointHoverRadius: 2,
                    pointHoverBorderWidth: 1,
                    pointRadius: 3,
                    fill: false,
                    borderWidth: 3,
                    data: {!! json_encode($monthly_withdrawal) !!}
                }]
            },
            options: {
                legend: {
                    position: "bottom",
                    display: true,
                    labels: {
                        fontColor: '#928F8F'
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            beginAtZero: true,
                            // maxTicksLimit: 5,
                            // padding: 20,
                            // max: 1000
                        },
                        gridLines: {
                            drawTicks: false,
                            display: false
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            zeroLineColor: "transparent",
                            drawTicks: true,
                            display: false
                        },
                        ticks: {
                            // padding: 20,
                            fontColor: "#928F8F",
                            fontStyle: "bold",
                            // max: 10000,
                            autoSkip: false
                        }
                    }]
                }
            }
        });
    </script>

    <script>
        var options = {
            series: [{{number_format($active_percentage,2)}}],
            colors: ["#5D58E7"],
            chart: {
                height: 400,
                type: 'radialBar',
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '50',
                    },
                    dataLabels: {
                        value: {
                            color: "#B4B8D7",
                            fontSize: "20px",
                            offsetY: -5,
                            show: true
                        }
                    }
                },
            },
            labels: [''],
            fill: {
                type: "gradient",
                gradient: {
                    shade: "dark",
                    type: "vertical",
                    gradientToColors: ["#309EF9"],
                    stops: [0, 100]
                }
            },
        };

        var chart = new ApexCharts(document.querySelector("#active-user-chart"), options);
        chart.render();
    </script>

    <script>
        var options = {
            series: [{{number_format($inactive_percentage,2)}}],
            colors: ["#F24F4D"],
            chart: {
                height: 400,
                type: 'radialBar',
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '50',
                    },
                    dataLabels: {
                        value: {
                            color: "#B4B8D7",
                            fontSize: "20px",
                            offsetY: -5,
                            show: true
                        }
                    }
                },
            },
            labels: [''],
            fill: {
                type: "gradient",
                gradient: {
                    shade: "dark",
                    type: "vertical",
                    gradientToColors: ["#F89A6B"],
                    stops: [0, 100]
                }
            },
        };

        var chart = new ApexCharts(document.querySelector("#deleted-user-chart"), options);
        chart.render();
    </script>

    <script>
        $('#pending_withdrwall').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            responsive: false,
            ajax: '{{route('adminPendingWithdrawal')}}',
            order: [7, 'desc'],
            autoWidth: false,
            language: {
                paginate: {
                    next: 'Next &#8250;',
                    previous: '&#8249; Previous'
                }
            },
            columns: [
                {"data": "address_type"},
                {"data": "sender"},
                {"data": "address"},
                {"data": "receiver"},
                {"data": "amount"},
                {"data": "fees"},
                {"data": "transaction_hash", "render": function(data) {
                    if (!data) return '&mdash;';
                    if (typeof data === 'string' && data.startsWith('0x')) {
                        return '<a href="{{ explorer_tx_base() }}'+data+'" target="_blank" rel="noopener noreferrer" title="'+data+'">'+data.substring(0,16)+'&#8230;</a>';
                    }
                    return data;
                }},
                {"data": "updated_at"},
                {"data": "actions"}
            ]
        });
    </script>
@endsection
