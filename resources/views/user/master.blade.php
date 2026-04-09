<!DOCTYPE HTML>
<html class="no-js" lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="description" content="The Highly Secured Bitcoin Wallet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:type" content="article" />
    <meta property="og:title" content="{{allsetting('app_title')}}"/>
    <meta property="og:image" content="{{asset('assets/user/images/logo.svg')}}">
    <meta property="og:site_name" content="Cpoket"/>
    <meta property="og:url" content="{{url()->current()}}"/>
    <meta property="og:type" content="{{allsetting('app_title')}}"/>
    <meta itemscope itemtype="{{ url()->current() }}/{{allsetting('app_title')}}" />
    <meta itemprop="headline" content="{{allsetting('app_title')}}" />
    <meta itemprop="image" content="{{asset('assets/user/images/logo.svg')}}" />
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{asset('assets/user/css/bootstrap.min.css')}}">
    <!-- metismenu CSS -->
    <link rel="stylesheet" href="{{asset('assets/user/css/metisMenu.min.css')}}">
    {{--for toast message--}}
    <link href="{{asset('assets/toast/vanillatoasts.css')}}" rel="stylesheet" >
    <!-- Datatable CSS -->
    <link rel="stylesheet" href="{{asset('assets/user/css/datatable/datatables.min.css')}}">
    <link rel="stylesheet" href="{{asset('assets/user/css/datatable/dataTables.bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('assets/user/css/datatable/dataTables.jqueryui.min.css')}}">
    <link rel="stylesheet" href="{{asset('assets/user/css/datatable/jquery.dataTables.min.css')}}">

    <link rel="stylesheet" href="{{asset('assets/user/css/jquery.scrollbar.css')}}">
    <link rel="stylesheet" href="{{asset('assets/user/css/font-awesome.min.css')}}">

    <link rel="stylesheet" href="{{asset('assets/user/css/jquery.countdown.css')}}">


    {{--    dropify css  --}}
    <link rel="stylesheet" href="{{asset('assets/dropify/dropify.css')}}">

    <!-- Style CSS -->
    <link rel="stylesheet" href="{{asset('assets/user/style.css')}}">
    <!-- Responsive CSS -->
    <link rel="stylesheet" href="{{asset('assets/user/css/responsive.css')}}">
    <!-- Google Fonts – Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* ================================================================
       OBXCoin – Dark Mode Dashboard  |  v3
    ================================================================ */
    :root {
        --sidebar-w:    260px;
        --topbar-h:     56px;
        --dark:         #0d1117;
        --dark2:        #161b22;
        --dark3:        #1c2333;
        --dark4:        #21262d;
        --accent:       #6366f1;
        --accent-h:     #4f46e5;
        --accent-glow:  rgba(99,102,241,.25);
        --border:       rgba(255,255,255,.08);
        --border-light: rgba(255,255,255,.05);
        --text:         #e6edf3;
        --text-2:       #c9d1d9;
        --muted:        #7d8590;
        --success:      #3fb950;
        --warning:      #d29922;
        --danger:       #f85149;
        --r:            10px;
        --r-sm:         7px;
        --shadow:       0 1px 3px rgba(0,0,0,.3),0 2px 8px rgba(0,0,0,.2);
        --shadow-md:    0 4px 16px rgba(0,0,0,.4);
        --shadow-lg:    0 10px 32px rgba(0,0,0,.5);
    }
    *{box-sizing:border-box;}
    html{color-scheme:dark;}
    body,body.cp-user-body-bg{
        font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif!important;
        background:var(--dark)!important;
        color:var(--text)!important;
    }
    /* ---- SIDEBAR — full viewport height, starts at top ---- */
    /* style.css sets padding-top:129px — kill it explicitly */
    .cp-user-sidebar{
        position:fixed!important;top:0!important;left:0!important;
        width:var(--sidebar-w)!important;height:100vh!important;
        margin:0!important;padding:0!important;padding-top:0!important;
        background:var(--dark2)!important;z-index:1040!important;
        overflow:hidden!important;display:flex!important;flex-direction:column!important;
        transition:transform .28s cubic-bezier(.4,0,.2,1)!important;
        border-right:1px solid var(--border)!important;
    }
    .cp-user-sidebar.cp-user-sidebar-hide{transform:translateX(-100%)!important;}
    /* Sidebar brand — logo + close btn, same height as topbar */
    .sidebar-brand{
        display:flex!important;align-items:center!important;justify-content:space-between!important;
        padding:0 16px!important;height:var(--topbar-h)!important;min-height:var(--topbar-h)!important;
        flex-shrink:0!important;border-bottom:1px solid var(--border)!important;
    }
    .sidebar-brand img{max-height:28px;max-width:120px;object-fit:contain;}
    .sidebar-close-btn{
        display:none;align-items:center;justify-content:center;
        width:26px;height:26px;border-radius:6px;
        background:rgba(255,255,255,.06);border:none;
        color:var(--muted);cursor:pointer;font-size:12px;transition:all .15s;
    }
    .sidebar-close-btn:hover{background:rgba(255,255,255,.12);color:var(--text);}
    .cp-user-sidebar .cp-user-logo,.cp-user-sidebar .nav-bottom-img,.cp-user-sidebar .mb-sidebar-toggler{display:none!important;}
    .cp-user-sidebar-menu{flex:1;overflow-y:auto;padding:6px 0 16px;}
    .cp-user-sidebar-menu::-webkit-scrollbar{width:2px;}
    .cp-user-sidebar-menu::-webkit-scrollbar-thumb{background:var(--border);border-radius:1px;}
    /* nav label */
    .nav-section-label{
        font-size:9.5px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;
        color:var(--muted);padding:16px 20px 6px;
    }
    #metismenu,#metismenu ul{list-style:none;padding:0;margin:0;}
    #metismenu>li{margin:1px 8px;}
    #metismenu>li>a{
        display:flex!important;align-items:center!important;gap:10px!important;
        padding:9px 12px!important;border-radius:var(--r-sm)!important;
        color:var(--muted)!important;text-decoration:none!important;
        font-size:13px!important;font-weight:500!important;
        transition:all .15s!important;white-space:nowrap!important;
    }
    #metismenu>li>a:hover{background:var(--dark4)!important;color:var(--text-2)!important;}
    #metismenu>li.cp-user-active-page>a{
        background:var(--accent-glow)!important;color:#a5b4fc!important;
        border-left:3px solid var(--accent)!important;padding-left:9px!important;
    }
    #metismenu>li>a.arrow-icon::after{
        content:'\f105';font-family:FontAwesome;margin-left:auto;
        font-size:10px;opacity:.4;transition:transform .2s;
    }
    #metismenu>li.mm-active>a.arrow-icon::after{transform:rotate(90deg);opacity:.7;}
    #metismenu>li>ul{padding:2px 0 4px 34px!important;margin:0 8px 2px!important;}
    #metismenu>li>ul>li>a{
        display:flex!important;align-items:center!important;gap:7px!important;
        padding:6px 10px!important;border-radius:6px!important;
        color:var(--muted)!important;font-size:12px!important;
        text-decoration:none!important;transition:all .15s!important;
    }
    #metismenu>li>ul>li>a::before{content:'';width:3px;height:3px;border-radius:50%;background:currentColor;flex-shrink:0;opacity:.5;}
    #metismenu>li>ul>li>a:hover{color:var(--text-2)!important;background:var(--dark4)!important;}
    #metismenu>li>ul>li.cp-user-submenu-active>a{color:#a5b4fc!important;font-weight:500!important;}
    .cp-user-side-bar-icon,.cp-user-side-bar-icon-hover{display:none!important;}
    .nav-icon{width:18px;height:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;}
    .cp-user-active-page .nav-icon{color:#a5b4fc;}
    /* ---- TOPBAR — sits to the right of the sidebar on desktop ---- */
    /* Kill the original theme's min-height:130px and line-height:130px */
    .cp-user-top-bar,
    .cp-user-top-bar.cp-user-content-expend{
        position:fixed!important;
        top:0!important;
        left:var(--sidebar-w)!important;right:0!important;
        width:auto!important;
        height:var(--topbar-h)!important;
        min-height:var(--topbar-h)!important;
        max-height:var(--topbar-h)!important;
        line-height:1!important;
        background:var(--dark2)!important;
        border-bottom:1px solid var(--border)!important;
        z-index:1030!important;
        padding:0!important;margin:0!important;
        overflow:visible!important;
    }
    /* When sidebar is hidden, topbar stretches full width */
    .cp-user-top-bar.cp-user-content-expend{
        left:0!important;
    }
    .cp-user-top-bar>.container-fluid{
        height:var(--topbar-h)!important;
        min-height:0!important;
        line-height:1!important;
        display:flex!important;align-items:center!important;
        padding:0 3px!important;gap:4px;
        flex-wrap:nowrap;
    }
    /* kill any Bootstrap row/col inside topbar that the theme injected */
    .cp-user-top-bar .row{display:contents!important;}
    /* hide original logo col in topbar */
    .cp-user-top-bar .cp-user-logo{display:none!important;}
    .topbar-left{display:flex;align-items:center;gap:8px;flex-shrink:0;}
    /* topbar-center no longer used — stats moved into topbar-right */
    .topbar-center{display:none!important;}
    .topbar-right{display:flex;align-items:center;gap:6px;flex-shrink:0;margin-left:auto;overflow:visible;min-width:0;}
    /* ---- Inline stats (always visible, next to bell) ---- */
    .topbar-stats-inline{
        display:flex;align-items:center;gap:4px;
        flex-shrink:1;min-width:0;overflow:hidden;
        margin-right:4px;
    }
    /* Full pill style on desktop */
    .topbar-stats-inline .tb-stat{
        padding:4px 10px;
    }
    /* Compact on tablet */
    @media(max-width:991px){
        .topbar-stats-inline .tb-stat{
            background:none;border:none;
            border-right:1px solid var(--border);
            border-radius:0;padding:0 8px;
        }
        .topbar-stats-inline .tb-stat:last-child{border-right:none;}
        .topbar-stats-inline .tb-stat-label{
            display:none;
        }
        .topbar-stats-inline .tb-stat-sub{display:none;}
        .topbar-stats-inline .tb-stat-val{font-size:11px;font-weight:600;}
    }
    /* Hide membership on small phones to save space */
    @media(max-width:480px){
        .topbar-stats-inline .tb-stat-membership{display:none;}
        .topbar-stats-inline .tb-stat-val{font-size:10.5px;}
    }
    .menu-bars{
        cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
        width:34px;height:34px;border-radius:var(--r-sm);transition:background .15s;
        background:transparent;border:none;color:var(--muted);flex-shrink:0;
    }
    .menu-bars:hover{background:var(--dark4);color:var(--text);}
    .menu-bars img{width:18px;filter:invert(1);opacity:.6;}
    /* balance pills */
    .tb-stat{
        display:flex;flex-direction:column;justify-content:center;
        padding:5px 12px;background:var(--dark3);
        border:1px solid var(--border);border-radius:50px;
        white-space:nowrap;flex-shrink:0;min-width:0;
    }
    .tb-stat-label{font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);line-height:1;margin-bottom:2px;}
    .tb-stat-val{font-size:12px;font-weight:600;color:var(--text);line-height:1.2;}
    .tb-stat-sub{font-size:10.5px;font-weight:400;color:var(--muted);line-height:1;}
    .tb-stat-val .accent{color:#a5b4fc;}
    .tb-badge{
        display:inline-block;padding:2px 7px;border-radius:20px;
        font-size:10px;font-weight:600;
        background:var(--accent-glow);color:#a5b4fc;border:1px solid rgba(99,102,241,.3);
    }
    .tb-badge.warning{background:rgba(210,153,34,.15);color:#d29922;border-color:rgba(210,153,34,.3);}
    /* drawer and toggle removed — stats are always inline */
    .topbar-stats-mobile,.stats-toggle-btn{display:none!important;}
    /* notification */
    .notification-btn{
        position:relative!important;background:var(--dark3)!important;
        border:1px solid var(--border)!important;border-radius:50%!important;
        width:34px!important;height:34px!important;
        display:inline-flex!important;align-items:center!important;
        justify-content:center!important;padding:0!important;transition:all .15s!important;
    }
    .notification-btn:hover{background:var(--dark4)!important;border-color:rgba(255,255,255,.15)!important;}
    .notification-btn img{width:16px;filter:invert(1);opacity:.65;}
    .notification-btn::after{display:none!important;}
    .hm-notify-number{
        position:absolute!important;top:-4px!important;right:-4px!important;
        background:var(--danger)!important;color:#fff!important;
        font-size:8px!important;font-weight:700!important;
        min-width:14px!important;height:14px!important;
        border-radius:50%!important;display:flex!important;
        align-items:center!important;justify-content:center!important;
        line-height:1!important;border:2px solid var(--dark2)!important;
    }
    .notification-list{
        width:290px!important;background:var(--dark3)!important;
        border:1px solid var(--border)!important;border-radius:var(--r)!important;
        box-shadow:var(--shadow-lg)!important;padding:0!important;overflow:hidden!important;
    }
    .nt-title{
        font-size:10px!important;font-weight:600!important;color:var(--muted)!important;
        text-transform:uppercase!important;letter-spacing:.5px!important;
        padding:10px 14px!important;background:var(--dark4)!important;
        border-bottom:1px solid var(--border)!important;
    }
    .notification-list .scrollbar-inner{max-height:240px;list-style:none;padding:0;margin:0;overflow-y:auto;}
    .notification-list .scrollbar-inner li a{
        display:block!important;padding:10px 14px!important;font-size:12.5px!important;
        color:var(--text-2)!important;border-bottom:1px solid var(--border-light)!important;
        transition:background .15s!important;white-space:normal!important;
    }
    .notification-list .scrollbar-inner li a:hover{background:var(--dark4)!important;}
    .notification-list .scrollbar-inner .small{color:var(--muted)!important;font-size:10.5px!important;}
    /* profile dropdown */
    .profile-dropdown>.btn{background:none!important;border:none!important;padding:3px!important;box-shadow:none!important;}
    .profile-dropdown>.btn::after{display:none!important;}
    .cp-user-avater{display:flex;align-items:center;gap:6px;}
    .cp-user-img{width:30px;height:30px;border-radius:50%;overflow:hidden;border:2px solid var(--border);flex-shrink:0;}
    .cp-user-img img{width:100%;height:100%;object-fit:cover;}
    .profile-dropdown .dropdown-menu{
        background:var(--dark3)!important;border:1px solid var(--border)!important;
        border-radius:var(--r)!important;box-shadow:var(--shadow-lg)!important;padding:8px!important;min-width:180px!important;
    }
    .big-user-thumb{display:flex;justify-content:center;padding:8px 0 6px;}
    .big-user-thumb img{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
    .user-name{text-align:center;padding-bottom:8px;border-bottom:1px solid var(--border);margin-bottom:6px;}
    .user-name p{font-size:13px;font-weight:600;color:var(--text);margin:0;}
    .profile-dropdown .dropdown-item{padding:0!important;background:none!important;border:none!important;border-radius:6px!important;margin-bottom:2px!important;}
    .profile-dropdown .dropdown-item a{
        display:flex;align-items:center;gap:8px;padding:7px 10px;
        font-size:12.5px;color:var(--text-2);text-decoration:none;
        border-radius:6px;transition:background .15s;width:100%;
    }
    .profile-dropdown .dropdown-item a:hover{background:var(--dark4);color:var(--text);}
    .profile-dropdown .dropdown-item a i{width:13px;color:var(--muted);font-size:12px;}
    /* ---- MAIN WRAPPER ---- */
    /* Offset top by topbar, left by sidebar (both fixed) */
    .cp-user-main-wrapper{
        margin-left:var(--sidebar-w)!important;
        margin-top:var(--topbar-h)!important;
        padding:20px!important;
        min-height:calc(100vh - var(--topbar-h))!important;
        background:var(--dark)!important;
        transition:margin-left .28s cubic-bezier(.4,0,.2,1)!important;
    }
    .cp-user-main-wrapper.cp-user-content-expend{
        margin-left:0!important;
        margin-top:var(--topbar-h)!important;
    }
    .cp-user-main-wrapper>.container-fluid{padding:0!important;}
    .sidebar-overlay{
        display:none;position:fixed;
        top:0;left:0;right:0;bottom:0;
        background:rgba(0,0,0,.65);z-index:1038;
    }
    .sidebar-overlay.active{display:block;}
    /* ---- CARDS ---- */
    .card{
        border:1px solid var(--border)!important;border-radius:var(--r)!important;
        box-shadow:var(--shadow)!important;background:var(--dark2)!important;
        color:var(--text)!important;
    }
    .cp-user-custom-card{background:var(--dark2)!important;border:1px solid var(--border)!important;border-radius:var(--r)!important;}
    .cp-user-custom-card .card-body{padding:18px 20px!important;}
    .cp-user-card-header-area{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
    .cp-user-title h4{font-size:14px!important;font-weight:600!important;color:var(--text)!important;margin:0!important;}
    .subtitle{font-size:11.5px!important;color:var(--muted)!important;margin-bottom:14px!important;}
    /* ---- STAT CARDS ---- */
    .status-card{border:none!important;border-radius:var(--r)!important;box-shadow:var(--shadow-md)!important;transition:transform .2s,box-shadow .2s!important;overflow:hidden!important;}
    .status-card:hover{transform:translateY(-3px)!important;box-shadow:var(--shadow-lg)!important;}
    .status-card .card-body{padding:20px!important;}
    .status-card-bg-blue{background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%)!important;border:none!important;}
    .status-card-bg-green{background:linear-gradient(135deg,#059669 0%,#0284c7 100%)!important;border:none!important;}
    .status-card-bg-read{background:linear-gradient(135deg,#db2777 0%,#ea580c 100%)!important;border:none!important;}
    .status-card-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .status-card .content p{color:rgba(255,255,255,.7)!important;font-size:10.5px!important;font-weight:600!important;text-transform:uppercase!important;letter-spacing:.6px!important;margin-bottom:8px!important;}
    .status-card .content h3{color:#fff!important;font-size:26px!important;font-weight:700!important;margin:0!important;letter-spacing:-.5px!important;line-height:1!important;}
    .status-card .icon{width:46px;height:46px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .status-card .icon img{width:22px;filter:brightness(0) invert(1);}
    /* ---- WELCOME CARD ---- */
    .welcome-card{
        background:var(--dark3);border:1px solid var(--border);
        border-radius:var(--r)!important;padding:20px 24px;margin-bottom:20px;
        display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
    }
    .welcome-card h5{color:var(--text);font-size:16px;font-weight:700;margin:0 0 3px;}
    .welcome-card p{color:var(--muted);font-size:12px;margin:0;}
    .quick-actions{display:flex;gap:7px;flex-wrap:wrap;}
    .quick-action-btn{
        display:inline-flex;align-items:center;gap:6px;padding:8px 14px;
        border-radius:var(--r-sm);font-size:12px;font-weight:500;
        text-decoration:none;transition:all .15s;border:1px solid transparent;
    }
    .qab-primary{background:var(--accent);color:#fff;border-color:var(--accent);}
    .qab-primary:hover{background:var(--accent-h);color:#fff;}
    .qab-outline{background:var(--dark4);color:var(--text-2);border-color:var(--border);}
    .qab-outline:hover{background:var(--dark3);color:var(--text);border-color:rgba(255,255,255,.15);}
    /* ---- TABLES ---- */
    .cp-user-transaction-history-table .table{font-size:12.5px!important;color:var(--text-2)!important;}
    .cp-user-transaction-history-table .table thead th{
        font-size:10.5px!important;font-weight:600!important;text-transform:uppercase!important;
        letter-spacing:.5px!important;color:var(--muted)!important;
        background:var(--dark3)!important;border-bottom:1px solid var(--border)!important;
        padding:10px 12px!important;white-space:nowrap!important;
    }
    .cp-user-transaction-history-table .table td{
        padding:10px 12px!important;color:var(--text-2)!important;
        border-color:var(--border-light)!important;vertical-align:middle!important;
        background:var(--dark2)!important;
    }
    .table{color:var(--text-2)!important;border-color:var(--border)!important;}
    .table td,.table th{border-color:var(--border-light)!important;}
    .table-striped tbody tr:nth-of-type(odd){background:var(--dark3)!important;}
    .deposite-list-area .card-body{padding:0!important;}
    .deposite-list-area .activity-top-area{padding:14px 18px;border-bottom:1px solid var(--border);}
    .tabe-menu .dashboard-tabs{background:var(--dark3)!important;border-radius:7px!important;padding:3px!important;display:flex!important;gap:2px!important;}
    .tabe-menu .dashboard-tabs .nav-link{border:none!important;border-radius:5px!important;padding:6px 13px!important;font-size:12.5px!important;font-weight:500!important;color:var(--muted)!important;transition:all .15s!important;}
    .tabe-menu .dashboard-tabs .nav-link.active{background:var(--dark2)!important;color:#a5b4fc!important;box-shadow:0 1px 4px rgba(0,0,0,.3)!important;}
    /* DataTable */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select{
        background:var(--dark3)!important;border:1px solid var(--border)!important;
        border-radius:var(--r-sm)!important;padding:5px 9px!important;
        font-size:12px!important;color:var(--text)!important;outline:none!important;
    }
    .dataTables_wrapper .dataTables_filter input:focus{border-color:var(--accent)!important;}
    .dataTables_wrapper .dataTables_info{font-size:12px;color:var(--muted);}
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_paginate{color:var(--muted)!important;}
    .page-link{background:var(--dark3)!important;border-color:var(--border)!important;color:#a5b4fc!important;font-size:12px!important;}
    .page-link:hover{background:var(--dark4)!important;color:var(--text)!important;}
    .page-item.active .page-link{background:var(--accent)!important;border-color:var(--accent)!important;color:#fff!important;}
    .page-item.disabled .page-link{color:var(--muted)!important;}
    /* Modals */
    .modal-content{background:var(--dark2)!important;border:1px solid var(--border)!important;border-radius:var(--r)!important;color:var(--text)!important;}
    .modal-header{background:var(--dark3)!important;border-bottom:1px solid var(--border)!important;}
    .modal-footer{border-top:1px solid var(--border)!important;}
    .dark-modal .modal-title{color:var(--text)!important;font-size:14px!important;font-weight:600!important;}
    .close{color:var(--muted)!important;opacity:1!important;text-shadow:none!important;}
    .close:hover{color:var(--text)!important;}
    #n_title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;}
    #n_date{font-size:11.5px;color:var(--muted);margin-bottom:10px;}
    #n_notice{font-size:13px;color:var(--text-2);line-height:1.7;}
    /* Forms */
    .form-control{
        background:var(--dark3)!important;border:1px solid var(--border)!important;
        border-radius:var(--r-sm)!important;font-size:13px!important;
        padding:8px 12px!important;color:var(--text)!important;
        transition:border-color .2s,box-shadow .2s!important;
    }
    .form-control:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px var(--accent-glow)!important;outline:none!important;}
    .form-control::placeholder{color:var(--muted)!important;}
    label{font-size:12px;font-weight:500;color:var(--muted);}
    select.form-control option{background:var(--dark3);color:var(--text);}
    /* Buttons */
    .cp-user-move-btn,.btn-primary{
        background:var(--accent)!important;border-color:var(--accent)!important;
        border-radius:var(--r-sm)!important;font-size:13px!important;font-weight:500!important;
        padding:9px 18px!important;transition:all .15s!important;color:#fff!important;
    }
    .cp-user-move-btn:hover{background:var(--accent-h)!important;border-color:var(--accent-h)!important;}
    .btn-secondary{background:var(--dark4)!important;border-color:var(--border)!important;color:var(--text-2)!important;}
    .btn-secondary:hover{background:var(--dark3)!important;}
    /* Socket alert */
    #web_socket_notification{
        background:rgba(59,130,246,.12)!important;border:1px solid rgba(59,130,246,.25)!important;
        color:#93c5fd!important;border-radius:var(--r-sm)!important;
        font-size:13px!important;font-weight:500!important;margin-bottom:16px!important;
    }
    /* Chart containers */
    .tradingview-widget-container{border-radius:var(--r-sm);overflow:hidden;}
    canvas{max-width:100%;}
    /* Badge / status pills */
    .badge{font-size:10.5px;font-weight:600;padding:3px 8px;border-radius:20px;}
    .badge-success{background:rgba(63,185,80,.15)!important;color:var(--success)!important;}
    .badge-warning{background:rgba(210,153,34,.15)!important;color:var(--warning)!important;}
    .badge-danger{background:rgba(248,81,73,.15)!important;color:var(--danger)!important;}
    /* ---- RESPONSIVE ---- */
    @media(max-width:991px){
        /* Topbar stretches full width, sidebar overlays */
        .cp-user-top-bar,
        .cp-user-top-bar.cp-user-content-expend{
            left:0!important;
        }
        .cp-user-main-wrapper,
        .cp-user-main-wrapper.cp-user-content-expend{
            margin-left:0!important;
            padding:14px!important;
        }
        /* Show close button inside sidebar on tablet/mobile */
        .sidebar-close-btn{display:inline-flex;}
    }
    @media(max-width:767px){
        :root{--topbar-h:46px;}
        .menu-bars{width:30px!important;height:30px!important;}
        .notification-btn{width:30px!important;height:30px!important;}
        .cp-user-img{width:26px;height:26px;}
        .cp-user-top-bar>.container-fluid{padding:0 10px!important;gap:6px;}
        .welcome-card{padding:14px 16px;}
        .status-card .content h3{font-size:22px!important;}
        .cp-user-main-wrapper,
        .cp-user-main-wrapper.cp-user-content-expend{
            margin-top:46px!important;
            min-height:calc(100vh - 46px)!important;
        }
    }
    @media(max-width:575px){
        :root{--topbar-h:44px;}
        .cp-user-main-wrapper,
        .cp-user-main-wrapper.cp-user-content-expend{
            margin-top:44px!important;
            min-height:calc(100vh - 44px)!important;
            padding:10px!important;
        }
        .status-card{margin-bottom:10px;}
    }
    </style>
    @yield('style')
    <title>{{allsetting('app_title')}} — @yield('title')</title>
    <!-- Favicon and Touch Icons -->
    <link rel="shortcut icon" href="{{landingPageImage('favicon','images/fav.png')}}/">
</head>

<body class="cp-user-body-bg">
<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>
@php $clubInfo = get_plan_info(Auth::id()) @endphp

<!-- top bar -->
<div class="cp-user-top-bar">
    <div class="container-fluid">
        @php
            $notifications = \App\Model\Notification::where(['user_id'=> Auth::user()->id, 'status' => 0])->orderBy('id', 'desc')->get();
            $balance = getUserBalance(Auth::id());
        @endphp
        {{-- Left: hamburger --}}
        <div class="topbar-left">
            <button class="menu-bars" title="Toggle sidebar">
                <i class="fa fa-bars" style="font-size:15px;"></i>
            </button>
        </div>
        {{-- Right: stats always visible + notification + profile --}}
        <div class="topbar-right">
            {{-- Stats inline — always visible on all screens --}}
            <div class="topbar-stats-inline">
                <div class="tb-stat">
                    <span class="tb-stat-label">{{__('Balance')}}</span>
                    <span class="tb-stat-val"><span class="accent">{{number_format($balance['available_coin'],2)}}</span> {{allsetting('coin_name')}}</span>
                    <span class="tb-stat-sub">≈ {{number_format($balance['available_used'],2)}} USD</span>
                </div>
                <div class="tb-stat">
                    <span class="tb-stat-label">{{__('Blocked OBXCoins')}}</span>
                    <span class="tb-stat-val">{{number_format(get_blocked_coin(Auth::id()),2)}}</span>
                </div>
                <div class="tb-stat tb-stat-membership">
                    <span class="tb-stat-label">{{__('Staking Plan')}}</span>
                    @if(!empty($clubInfo['club_id']))
                        <span class="tb-badge">{{ $clubInfo['plan_name'] }}</span>
                    @else
                        <span class="tb-badge warning">{{__('No Staking Plan')}}</span>
                    @endif
                </div>
            </div>
            {{-- Notifications --}}
            <div class="hm-notify" id="notification_item">
                <div class="btn-group dropdown">
                    <button type="button" class="btn notification-btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="notify-value hm-notify-number">@if(isset($notifications) && $notifications->count() > 0){{ $notifications->count() }}@else 0 @endif</span>
                        <i class="fa fa-bell" style="font-size:14px;color:rgba(255,255,255,.6);"></i>
                    </button>
                    @if(!empty($notifications))
                        <div class="dropdown-menu notification-list dropdown-menu-right">
                            <div class="text-center nt-title">{{__('Notifications')}}</div>
                            <ul class="scrollbar-inner">
                                @foreach($notifications as $item)
                                    <li>
                                        <a href="javascript:void(0);" data-toggle="modal" data-id="{{$item->id}}" data-target="#notificationShow" class="dropdown-item viewNotice">
                                            <span class="small d-block">{{ date('d M y', strtotime($item->created_at)) }}</span>
                                            {{ $item->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
            {{-- Profile --}}
            <div class="btn-group profile-dropdown">
                <button type="button" class="btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="cp-user-avater">
                        <span class="cp-user-img">
                            <img src="{{show_image(Auth::user()->id,'user')}}" alt="">
                        </span>
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <span class="big-user-thumb">
                        <img src="{{show_image(Auth::user()->id,'user')}}" alt="">
                    </span>
                    <div class="user-name">
                        <p>{{Auth::user()->first_name.' '.Auth::user()->last_name}}</p>
                    </div>
                    <button class="dropdown-item" type="button"><a href="{{route('userProfile')}}"><i class="fa fa-user-circle-o"></i> {{__('Profile')}}</a></button>
                    <button class="dropdown-item" type="button"><a href="{{route('userSetting')}}"><i class="fa fa-cog"></i> {{__('Settings')}}</a></button>
                    <button class="dropdown-item" type="button"><a href="{{route('myPocket')}}"><i class="fa fa-money"></i> {{__('My Wallet')}}</a></button>
                    <button class="dropdown-item" type="button"><a href="{{route('logOut')}}"><i class="fa fa-sign-out"></i> {{__('Logout')}}</a></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /top bar -->

<!-- Start sidebar -->
<div class="cp-user-sidebar">
    <div class="sidebar-brand">
        <a href="{{route('userDashboard')}}" style="text-decoration:none;">
            <img src="{{show_image(Auth::id(),'logo')}}" alt="{{allsetting('app_title')}}">
        </a>
        <button class="sidebar-close-btn menu-bars" title="Close">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <div class="cp-user-sidebar-menu scrollbar-inner">
        <nav>
            <ul id="metismenu">
                <li class="@if(isset($menu) && $menu == 'dashboard') cp-user-active-page @endif">
                    <a href="{{route('userDashboard')}}">
                        <span class="nav-icon"><i class="fa fa-th-large"></i></span>
                        <span class="cp-user-name">{{__('Dashboard')}}</span>
                    </a>
                </li>
                <li class="@if(isset($menu) && $menu == 'coin') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-shopping-cart"></i></span>
                        <span class="cp-user-name">{{__('Buy Coin')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'coin') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'buy_coin') cp-user-submenu-active @endif">
                            <a href="{{route('buyCoin')}}">{{__('Buy Coin')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'buy_coin_history') cp-user-submenu-active @endif">
                            <a href="{{route('buyCoinHistory')}}">{{__('Buy History')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'buy_coin_referral_history') cp-user-submenu-active @endif">
                            <a href="{{route('buyCoinReferralHistory')}}">{{__('Referral History')}}</a>
                        </li>
                    </ul>
                </li>
                <li class="@if(isset($menu) && $menu == 'coin_request') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-exchange"></i></span>
                        <span class="cp-user-name">{{__('Send / Receive')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'coin_request') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'give_coin') cp-user-submenu-active @endif">
                            <a href="{{route('requestCoin')}}">{{__('Send / Request OBXCoin')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'give_request_history') cp-user-submenu-active @endif">
                            <a href="{{route('giveCoinHistory')}}">{{__('Send History')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'received_history') cp-user-submenu-active @endif">
                            <a href="{{route('receiveCoinHistory')}}">{{__('Receive History')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'pending_request') cp-user-submenu-active @endif">
                            <a href="{{route('pendingRequest')}}">{{__('Pending Requests')}}</a>
                        </li>
                    </ul>
                </li>
                <li class="@if(isset($menu) && $menu == 'pocket') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-credit-card"></i></span>
                        <span class="cp-user-name">{{__('My Wallet')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'pocket') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'my_pocket') cp-user-submenu-active @endif">
                            <a href="{{route('myPocket')}}">{{__('Wallet Overview')}}</a>
                        </li>
                        @if(getSwapStatus())
                            <li class="@if(isset($sub_menu) && $sub_menu == 'swap_history') cp-user-submenu-active @endif">
                                <a href="{{route('coinSwapHistory')}}">{{__('Swap History')}}</a>
                            </li>
                        @endif
                    </ul>
                </li>
                @if(getSwapStatus())
                <li class="@if(isset($menu) && $menu == 'coin_swap') cp-user-active-page @endif">
                    <a href="{{route('coinSwap')}}">
                        <span class="nav-icon"><i class="fa fa-refresh"></i></span>
                        <span class="cp-user-name">{{__('Swap Coin')}}</span>
                    </a>
                </li>
                @endif
                <li class="@if(isset($menu) && $menu == 'airdrop') cp-user-active-page @endif">
                    <a href="{{route('user.airdrop')}}">
                        <span class="nav-icon"><i class="fa fa-gift"></i></span>
                        <span class="cp-user-name">{{__('Airdrop')}}</span>
                    </a>
                </li>
                <li class="@if(isset($menu) && $menu == 'staking') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-lock"></i></span>
                        <span class="cp-user-name">{{__('Staking')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'staking') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'stake') cp-user-submenu-active @endif">
                            <a href="{{route('user.staking.index')}}">{{__('Stake OBX')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'staking_history') cp-user-submenu-active @endif">
                            <a href="{{route('user.staking.history')}}">{{__('My Stakes')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'staking_transactions') cp-user-submenu-active @endif">
                            <a href="{{route('user.staking.transactions')}}">{{__('Transactions')}}</a>
                        </li>
                    </ul>
                </li>
                <li class="@if(isset($menu) && $menu == 'member') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-star"></i></span>
                        <span class="cp-user-name">{{__('Membership')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'member') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'coin_transfer') cp-user-submenu-active @endif">
                            <a href="{{route('membershipClubPlan')}}">{{__('Transfer Coin')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'my_membership') cp-user-submenu-active @endif">
                            <a href="{{route('myMembership')}}">{{__('My Membership')}}</a>
                        </li>
                    </ul>
                </li>
                <li class="@if(isset($menu) && $menu == 'profile') cp-user-active-page @endif">
                    <a href="{{route('userProfile')}}">
                        <span class="nav-icon"><i class="fa fa-user"></i></span>
                        <span class="cp-user-name">{{__('My Profile')}}</span>
                    </a>
                </li>
                <li class="@if(isset($menu) && $menu == 'referral') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-users"></i></span>
                        <span class="cp-user-name">{{__('Referral')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'referral') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'referral') cp-user-submenu-active @endif">
                            <a href="{{route('myReferral')}}">{{__('My Referrals')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'referral_history') cp-user-submenu-active @endif">
                            <a href="{{route('myReferralEarning')}}">{{__('Earnings')}}</a>
                        </li>
                    </ul>
                </li>
                <li class="@if(isset($menu) && $menu == 'setting') cp-user-active-page mm-active @endif">
                    <a class="arrow-icon" href="#" aria-expanded="true">
                        <span class="nav-icon"><i class="fa fa-cog"></i></span>
                        <span class="cp-user-name">{{__('Settings')}}</span>
                    </a>
                    <ul class="@if(isset($menu) && $menu == 'setting') mm-show @endif">
                        <li class="@if(isset($sub_menu) && $sub_menu == 'setting') cp-user-submenu-active @endif">
                            <a href="{{route('userSetting')}}">{{__('My Settings')}}</a>
                        </li>
                        <li class="@if(isset($sub_menu) && $sub_menu == 'faq') cp-user-submenu-active @endif">
                            <a href="{{route('userFaq')}}">{{__('FAQ')}}</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
    <div style="padding:10px 18px;border-top:1px solid rgba(255,255,255,.07);">
        <a href="{{route('logOut')}}" style="display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:7px;color:#7d8590;font-size:12.5px;font-weight:500;text-decoration:none;transition:all .15s;">
            <i class="fa fa-sign-out" style="width:14px;font-size:13px;"></i> {{__('Logout')}}
        </a>
    </div>
</div>
<!-- End sidebar -->

{{--notification modal--}}

<div class="modal fade" id="notificationShow" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content dark-modal">
            <div class="modal-header align-items-center">
                <h5 class="modal-title" id="exampleModalCenterTitle">{{__('New Notification')}}  </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="hm-form">
                    <div class="row">
                        <div class="col-12">
                            <h6 id="n_title"></h6>
                            <p id="n_date"></p>
                            <p id="n_notice"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- main wrapper -->
<div class="cp-user-main-wrapper">
    <div class="container-fluid">
{{--        <div style="color: #155724;background-color: #d4edda;border-color: #c3e6cb;"--}}
{{--             class="alert-float alert alert-success  d-none" id="web_socket_notification">--}}
{{--            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button>--}}
{{--            <div class="web_socket_notification_html"></div>--}}
{{--        </div>--}}
        <div class="alert alert-success alert-dismissible fade show d-none" role="alert" id="web_socket_notification">
            <span id="socket_message"></span>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="modal fade" id="confirm-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <img src="{{asset('assets/user/images/close.svg')}}" class="img-fluid" alt="">
                    </button>
                    <div class="text-center">
                        <img src="{{asset('assets/user/images/add-pockaet-vector.svg')}}" class="img-fluid img-vector" alt="">
                        <h3 id="confirm-title"></h3>
                    </div>
                    <div class="modal-body">
                        <a id="confirm-link" href="#" class="btn btn-block cp-user-move-btn">{{__('Confirm')}}</a>
                    </div>
                </div>
            </div>
        </div>

        @if(session()->has('impersonating_admin_id'))
        <div style="position:sticky;top:0;z-index:9999;background:#6c63ff;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;font-size:14px;font-weight:500;">
            <span>
                <i class="fa fa-exclamation-triangle mr-2"></i>
                {{__('Admin mode: you are viewing this account as')}} <strong>{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</strong> ({{ Auth::user()->email }})
            </span>
            <a href="{{ route('admin.stop.impersonating') }}" class="btn btn-sm btn-light" style="color:#6c63ff;font-weight:600;">
                <i class="fa fa-sign-out mr-1"></i>{{__('Stop Impersonating')}}
            </a>
        </div>
        @endif

        @yield('content')
    </div>
</div>
<!-- /main wrapper -->

<!-- js file start -->
{{--<script src="{{asset('js/app.js')}}"></script>--}}
<!-- JavaScript -->
<script src="{{asset('assets/user/js/jquery.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="{{asset('assets/user/js/bootstrap.min.js')}}"></script>
<script src="{{asset('assets/user/js/metisMenu.min.js')}}"></script>
{{--toast message--}}
<script src="{{asset('assets/toast/vanillatoasts.js')}}"></script>
<!-- Datatable -->
<script src="{{asset('assets/user/js/datatable/datatables.min.js')}}"></script>
<script src="{{asset('assets/user/js/datatable/dataTables.bootstrap.min.js')}}"></script>
<script src="{{asset('assets/user/js/datatable/dataTables.jqueryui.min.js')}}"></script>
<script src="{{asset('assets/user/js/datatable/jquery.dataTables.min.js')}}"></script>

<script src="{{asset('assets/user/js/jquery.scrollbar.min.js')}}"></script>

<script src="{{asset('assets/user/js/jquery.plugin.min.js')}}"></script>
<script src="{{asset('assets/user/js/jquery.countdown.min.js')}}"></script>

{{--dropify--}}
<script src="{{asset('assets/dropify/dropify.js')}}"></script>
<script src="{{asset('assets/dropify/form-file-uploads.js')}}"></script>

<script src="{{asset('assets/user/js/main.js')}}"></script>

<script src="https://js.pusher.com/3.0/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.8.1/echo.iife.min.js"></script>
<script>
    let my_env_socket_port = "{{ config('broadcasting.connections.pusher.options.port', 6001) }}";
    window.Echo = new Echo({
        broadcaster: 'pusher',
        wsHost: window.location.hostname,
        wsPort: my_env_socket_port,
        wssPort: 443,
        key: '{{ config('broadcasting.connections.pusher.key') }}',
        cluster: 'mt1',
        encrypted: true,
        disableStats: true
    });
</script>
<script>

    Echo.channel('usernotification_' + '{{Auth::id()}}')
        .listen('.receive_notification', (data) => {
            if (data.success == true) {
                let message = data.message
                $('#web_socket_notification').removeClass('d-none');
                $('#socket_message').html(message);

                $.ajax({
                    type: "GET",
                    url: '{{ route('getNotification') }}',
                    data: {
                        '_token': '{{ csrf_token() }}',
                        'user_id': data.user_id,
                    },
                    success: function (datas) {
                        $('#notification_item').html(datas.data)
                    }
                });
            }
        });
</script>
<script>
    $(document).ready(function() {
        $('.cp-user-custom-table').DataTable({
            responsive: true,
            paging: true,
            searching: true,
            ordering:  true,
            select: false,
            bDestroy: true
        });


    });
</script>
@if(session()->has('success'))
    <script>
        window.onload = function () {
            VanillaToasts.create({
                //  title: 'Message Title',
                text: '{{session('success')}}',
                backgroundColor: "linear-gradient(135deg, #73a5ff, #5477f5)",
                type: 'success',
                timeout: 10000
            });
        }

    </script>

@elseif(session()->has('dismiss'))
    <script>
        window.onload = function () {

            VanillaToasts.create({
                // title: 'Message Title',
                text: '{{session('dismiss')}}',
                type: 'warning',
                timeout: 10000

            });
        }
    </script>

@elseif($errors->any())
    @foreach($errors->getMessages() as $error)
        <script>
            window.onload = function () {
                VanillaToasts.create({
                    // title: 'Message Title2',
                    text: '{{ $error[0] }}',
                    type: 'warning',
                    timeout: 10000

                });
            }
        </script>

        @break
    @endforeach

@endif

<script>
    $(document).on('click', '.viewNotice', function (e) {
        var id = $(this).data('id');
        // alert(id);
        $.ajax({
            type: "GET",
            url: '{{ route('showNotification') }}',
            data: {
                '_token': '{{ csrf_token() }}',
                'id': id,
            },
            success: function (data) {
                $("#n_title").text(data['data']['title']);
                $("#n_date").text(data['data']['date']);
                $("#n_notice").text(data['data']['notice']);

                $('#notification_item').html(data['data']['html'])
            }
        });
    });
</script>

{{--confirm modal script--}}
<script>
    $(document).on("click", ".confirm-modal", function (){
        $("#confirm-title").text($(this).data('title'));
        $("#confirm-link").attr('href', $(this).data('href'));
        $("#confirm-modal").modal("show");
    });
</script>
<!-- End js file -->

<!-- Sidebar overlay JS -->
<script>
(function($){
    // Sidebar overlay for mobile
    $('.menu-bars').on('click', function(){
        if($(window).width() <= 991){
            var sidebarHidden = $('.cp-user-sidebar').hasClass('cp-user-sidebar-hide');
            if(sidebarHidden){
                $('#sidebarOverlay').addClass('active');
            } else {
                $('#sidebarOverlay').removeClass('active');
            }
        }
    });
    $('#sidebarOverlay').on('click', function(){
        $('.cp-user-sidebar').addClass('cp-user-sidebar-hide');
        $('.cp-user-top-bar, .cp-user-main-wrapper').addClass('cp-user-content-expend');
        $(this).removeClass('active');
    });
    // Auto-close sidebar when a leaf nav link is tapped on mobile
    $('#metismenu a:not(.arrow-icon)').on('click', function(){
        if($(window).width() <= 991){
            $('.cp-user-sidebar').addClass('cp-user-sidebar-hide');
            $('.cp-user-top-bar, .cp-user-main-wrapper').addClass('cp-user-content-expend');
            $('#sidebarOverlay').removeClass('active');
        }
    });
})(jQuery);
</script>

@yield('script')
</body>
</html>

