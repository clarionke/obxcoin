<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="{{ allsetting('app_title') }}">
    <title>{{ settings('app_title') }}</title>
    <link rel="shortcut icon" href="{{ landingPageImage('favicon','images/fav.png') }}">
    <link href="{{ asset('assets/css/gfont.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/toast/vanillatoasts.css') }}" rel="stylesheet">
    @if (isset(allsetting()['google_recapcha']) && (allsetting()['google_recapcha'] == STATUS_ACTIVE))
        {!! NoCaptcha::renderJs() !!}
    @endif
    <style>
        /* ─── Tokens ──────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:     #0b0e1a;   --bg2:    #111422;   --bg3:    #161b2e;
            --card:   #1a1f35;   --card2:  #1f2440;
            --border: rgba(255,255,255,.07);
            --accent: #6C63FF;   --accent2:#a78bfa;   --accent3:#38bdf8;
            --green:  #10b981;   --red:    #f87171;
            --text:   #e2e8f0;   --muted:  #94a3b8;
            --radius: 14px;
            --shadow: 0 8px 40px rgba(0,0,0,.5);
            --glow:   0 0 40px rgba(108,99,255,.25);
        }
        html { scroll-behavior: smooth; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter','Segoe UI',sans-serif; line-height: 1.65; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; }
        ul { list-style: none; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .section { padding: 100px 0; }
        .section-label { font-size: .75rem; letter-spacing: .18em; text-transform: uppercase; color: var(--accent2); font-weight: 600; margin-bottom: 12px; }
        .section-title { font-size: clamp(1.9rem,3.5vw,2.8rem); font-weight: 800; line-height: 1.2; margin-bottom: 16px; }
        .section-sub { color: var(--muted); max-width: 560px; margin: 0 auto 56px; text-align: center; }
        .text-center { text-align: center; }
        .gradient-text { background: linear-gradient(135deg,var(--accent2) 0%,var(--accent3) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }

        /* ─── Buttons ─────────────────────────────────────────────────── */
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 22px; border-radius: 10px; font-size: .88rem; font-weight: 600; cursor: pointer; transition: all .2s; border: none; }
        .btn-ghost  { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent2); }
        .btn-primary { background: linear-gradient(135deg,#6C63FF 0%,#a78bfa 100%); color: #fff; box-shadow: 0 4px 20px rgba(108,99,255,.4); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(108,99,255,.5); }
        .btn-lg { padding: 13px 32px; font-size: 1rem; border-radius: 12px; }
        .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: var(--accent2); background: rgba(108,99,255,.08); }

        /* ─── Navbar ─────────────────────────────────────────────────── */
        #navbar { position: fixed; top:0; left:0; right:0; z-index:1000; display:flex; align-items:center; justify-content:space-between; padding:18px 40px; transition: background .35s,padding .35s,box-shadow .35s; }
        #navbar.scrolled { background: rgba(11,14,26,.88); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); padding:12px 40px; box-shadow:0 1px 0 var(--border),var(--shadow); }
        .nav-logo img { height: 36px; }
        .nav-links { display:flex; align-items:center; gap:6px; }
        .nav-links a { color:var(--muted); font-size:.88rem; font-weight:500; padding:7px 14px; border-radius:8px; transition:color .2s,background .2s; }
        .nav-links a:hover { color:var(--text); background:var(--card); }
        .nav-auth { display:flex; align-items:center; gap:10px; }
        .nav-price { display:inline-flex; align-items:center; gap:8px; background:rgba(108,99,255,.1); border:1px solid rgba(108,99,255,.25); border-radius:100px; padding:5px 14px; font-size:.8rem; font-weight:700; color:var(--text); white-space:nowrap; flex-shrink:0; }
        .nav-price .np-dot { width:6px; height:6px; border-radius:50%; background:var(--green); box-shadow:0 0 6px var(--green); animation:pulse 2s infinite; flex-shrink:0; }
        .nav-price .np-val { color:var(--accent2); }
        .nav-price .np-change { font-size:.72rem; font-weight:700; }
        .nav-price .np-change.up { color:var(--green); }
        .nav-price .np-change.down { color:var(--red); }
        .hamburger { display:none; flex-direction:column; gap:5px; cursor:pointer; padding:6px; }
        .hamburger span { display:block; width:24px; height:2px; background:var(--text); border-radius:2px; transition:.3s; }
        .hamburger.open span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
        .hamburger.open span:nth-child(2) { opacity:0; }
        .hamburger.open span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }
        /* ─── Mobile drawer ──────────────────────────────────── */
        .mobile-menu {
            display:block; position:fixed; top:0; left:0; right:0; z-index:998;
            background:rgba(11,14,26,.97); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
            border-bottom:1px solid var(--border);
            padding:80px 24px 28px;
            transform:translateY(-110%);
            transition:transform .35s cubic-bezier(.4,0,.2,1), opacity .3s ease;
            opacity:0;
            pointer-events:none;
        }
        .mobile-menu.open { transform:translateY(0); opacity:1; pointer-events:auto; }
        .mobile-menu-price {
            display:flex; align-items:center; gap:10px;
            background:rgba(108,99,255,.12); border:1px solid rgba(108,99,255,.25);
            border-radius:12px; padding:12px 18px; margin-bottom:20px;
        }
        .mobile-menu-price .mm-label { font-size:.75rem; color:var(--muted); font-weight:500; flex:1; }
        .mobile-menu-price .mm-price { font-size:1.1rem; font-weight:800; color:var(--accent2); }
        .mobile-menu-price .mm-change { font-size:.75rem; font-weight:700; padding:3px 8px; border-radius:6px; }
        .mobile-menu-price .mm-change.up { background:rgba(16,185,129,.15); color:var(--green); }
        .mobile-menu-price .mm-change.down { background:rgba(248,113,113,.15); color:var(--red); }
        .mobile-menu-divider { height:1px; background:var(--border); margin:16px 0; }
        .mobile-nav-links { display:flex; flex-direction:column; gap:4px; }
        .mobile-nav-links a {
            display:flex; align-items:center; gap:12px;
            font-size:1rem; font-weight:600; color:var(--text);
            padding:12px 16px; border-radius:10px; transition:background .2s, color .2s;
        }
        .mobile-nav-links a:hover, .mobile-nav-links a:active { background:var(--card); color:var(--accent2); }
        .mobile-menu-actions { display:flex; gap:10px; margin-top:20px; }
        .mobile-menu-actions .btn { flex:1; justify-content:center; }
        .mobile-close-row { display:flex; justify-content:flex-end; position:absolute; top:16px; right:20px; }
        .mobile-close-btn {
            width:36px; height:36px; border-radius:10px; background:var(--card); border:1px solid var(--border);
            display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--muted);
            font-size:1.1rem; transition:background .2s, color .2s;
        }
        .mobile-close-btn:hover { background:rgba(108,99,255,.15); color:var(--text); }

        /* ─── Hero ─────────────────────────────────────────────────── */
        #hero { min-height:100vh; display:flex; align-items:center; position:relative; overflow:hidden;
            background: radial-gradient(ellipse 80% 60% at 50% 0%,rgba(108,99,255,.18) 0%,transparent 70%),
                        radial-gradient(ellipse 60% 40% at 80% 60%,rgba(56,189,248,.1) 0%,transparent 60%),
                        var(--bg); }
        #hero::before { content:''; position:absolute; inset:0; background-image:url("{{ landingPageImage('landing_page_logo','images/banner/hero.jpg') }}"); background-size:cover; background-position:center; opacity:.06; }
        .hero-grid { position:absolute; inset:0; pointer-events:none; background-image:linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px); background-size:60px 60px; mask-image:radial-gradient(ellipse at center,black 20%,transparent 80%); }
        .hero-inner { position:relative; z-index:2; display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:center; padding:120px 0 80px; }
        .hero-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(108,99,255,.12); border:1px solid rgba(108,99,255,.3); border-radius:100px; padding:5px 16px; font-size:.78rem; font-weight:600; color:var(--accent2); margin-bottom:24px; }
        .hero-badge .dot { width:7px; height:7px; border-radius:50%; background:var(--green); box-shadow:0 0 8px var(--green); animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
        .hero-title { font-size:clamp(2.4rem,5vw,3.8rem); font-weight:900; line-height:1.1; margin-bottom:22px; }
        .hero-desc { color:var(--muted); font-size:1.05rem; line-height:1.75; margin-bottom:36px; max-width:480px; }
        .hero-btns { display:flex; gap:14px; flex-wrap:wrap; }

        /* Hero mockup card */
        .hero-visual { position:relative; display:flex; align-items:center; justify-content:center; }
        .hero-card-glow { position:absolute; width:340px; height:340px; border-radius:50%; background:radial-gradient(circle,rgba(108,99,255,.25) 0%,transparent 70%); animation:floatglow 6s ease-in-out infinite; }
        @keyframes floatglow { 0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-20px) scale(1.05)} }
        .hero-mockup { position:relative; z-index:2; background:var(--card); border:1px solid var(--border); border-radius:24px; padding:28px; box-shadow:var(--shadow),var(--glow); width:100%; max-width:380px; animation:float 5s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)} }
        .mock-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .mock-header h4 { font-size:.82rem; color:var(--muted); font-weight:500; }
        .mock-live { display:flex; align-items:center; gap:6px; font-size:.72rem; color:var(--green); font-weight:600; }
        .mock-live .live-dot { width:6px; height:6px; border-radius:50%; background:var(--green); box-shadow:0 0 6px var(--green); animation:pulse 1.5s infinite; }
        .mock-price-row { display:flex; align-items:baseline; gap:12px; margin-bottom:6px; }
        .mock-price { font-size:2.2rem; font-weight:900; }
        .mock-change { font-size:.85rem; font-weight:700; padding:3px 10px; border-radius:8px; }
        .mock-change.up { background:rgba(16,185,129,.15); color:var(--green); }
        .mock-change.down { background:rgba(248,113,113,.15); color:var(--red); }
        .mock-updated { font-size:.72rem; color:var(--muted); margin-bottom:20px; }
        .mock-chart { height:70px; margin-bottom:20px; display:flex; align-items:flex-end; gap:5px; }
        .mock-chart span { flex:1; background:linear-gradient(180deg,var(--accent) 0%,var(--accent2) 100%); border-radius:4px 4px 0 0; opacity:.7; }
        .mock-row { display:flex; justify-content:space-between; padding:9px 0; border-top:1px solid var(--border); font-size:.82rem; }
        .mock-row span:last-child { color:var(--muted); }
        .mock-row .val-green { color:var(--green); font-weight:600; }
        .mock-coins { display:flex; gap:8px; margin-top:18px; flex-wrap:wrap; }
        .mock-coin { background:var(--card2); border-radius:8px; padding:6px 12px; font-size:.78rem; font-weight:600; border:1px solid var(--border); }

        /* ─── Token Info Bar ──────────────────────────────────────── */
        #token-info { background:var(--bg2); border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:22px 0; }
        .tib-inner { display:flex; flex-wrap:wrap; gap:0; justify-content:center; }
        .tib-item { display:flex; flex-direction:column; align-items:center; padding:10px 32px; border-right:1px solid var(--border); min-width:160px; }
        .tib-item:last-child { border-right:none; }
        .tib-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.12em; color:var(--muted); font-weight:600; margin-bottom:4px; }
        .tib-value { font-size:.92rem; font-weight:700; color:var(--text); word-break:break-all; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tib-value a { color:var(--accent2); text-decoration:none; }
        .tib-value a:hover { text-decoration:underline; }
        @media(max-width:600px){ .tib-item { border-right:none; border-bottom:1px solid var(--border); width:100%; } .tib-item:last-child{border-bottom:none;} }

        /* ─── Tokenomics ─────────────────────────────────────────── */
        #tokenomics { background:linear-gradient(180deg,var(--bg2) 0%,var(--bg) 100%); }
        .toko-inner { display:grid; grid-template-columns:1fr 1.15fr; gap:72px; align-items:center; }
        @media(max-width:900px){ .toko-inner { grid-template-columns:1fr; gap:48px; } }

        /* SVG donut */
        .toko-chart-wrap { position:relative; width:280px; height:280px; margin:0 auto; }
        .toko-chart-wrap svg { width:280px; height:280px; transform:rotate(-90deg); }
        .toko-seg { fill:none; stroke-width:38; transition:stroke-dasharray .9s cubic-bezier(.4,0,.2,1), opacity .2s; cursor:pointer; stroke-linecap:butt; }
        .toko-seg:hover { opacity:.75; }
        .toko-tip { position:fixed; background:var(--card); border:1px solid var(--border); border-radius:10px; padding:10px 16px; pointer-events:none; opacity:0; transition:opacity .15s; white-space:nowrap; z-index:9999; box-shadow:0 8px 24px rgba(0,0,0,.45); }
        .toko-tip.show { opacity:1; }
        .toko-tip .tt-name { font-size:.85rem; font-weight:700; margin-bottom:3px; }
        .toko-tip .tt-pct  { font-size:1.15rem; font-weight:900; }
        .toko-chart-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none; }
        .tdc-val  { font-size:1.7rem; font-weight:900; }
        .tdc-sup  { font-size:.7rem; color:var(--muted); display:block; margin-top:2px; }

        /* cards grid */
        .toko-cards { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media(max-width:500px){ .toko-cards { grid-template-columns:1fr; } }
        .toko-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px 20px; transition:border-color .2s,transform .2s; position:relative; overflow:hidden; }
        .toko-card::before { content:''; position:absolute; inset:0; opacity:0; transition:opacity .2s; }
        .toko-card:hover { transform:translateY(-3px); }
        .toko-card-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
        .toko-card-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .toko-card-label { font-size:.88rem; font-weight:700; flex:1; }
        .toko-card-pct { font-size:1.25rem; font-weight:900; line-height:1; }
        .toko-card-bar-bg { height:4px; background:var(--border); border-radius:100px; overflow:hidden; }
        .toko-card-bar { height:100%; border-radius:100px; width:0; transition:width 1.2s cubic-bezier(.4,0,.2,1); }
        .toko-card-tokens { font-size:.72rem; color:var(--muted); margin-top:8px; font-weight:500; }

        /* ─── ICO Phase Banner ─────────────────────────────────────── */
        #ico-phase { background: linear-gradient(135deg,rgba(108,99,255,.12) 0%,rgba(56,189,248,.08) 100%); border-top:1px solid rgba(108,99,255,.2); border-bottom:1px solid rgba(108,99,255,.2); padding:60px 0; }
        .ico-inner { display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:center; }
        .ico-text .phase-tag { display:inline-block; background:rgba(108,99,255,.2); color:var(--accent2); border:1px solid rgba(108,99,255,.35); border-radius:100px; padding:4px 16px; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; margin-bottom:16px; }
        .ico-text h2 { font-size:clamp(1.6rem,2.5vw,2.2rem); font-weight:800; margin-bottom:14px; }
        .ico-text p { color:var(--muted); margin-bottom:28px; line-height:1.75; }
        .ico-meta { display:flex; gap:28px; margin-bottom:24px; flex-wrap:wrap; }
        .ico-meta-item { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:12px 20px; }
        .ico-meta-item .im-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); margin-bottom:4px; }
        .ico-meta-item .im-val { font-size:1.2rem; font-weight:800; }
        .ico-meta-item .im-val.accent { color:var(--accent2); }
        .ico-progress-wrap { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:28px; }
        .ico-progress-wrap h4 { font-size:.82rem; color:var(--muted); text-transform:uppercase; letter-spacing:.1em; margin-bottom:16px; }
        .progress-bar-outer { background:var(--bg); border-radius:100px; height:12px; overflow:hidden; margin-bottom:12px; }
        .progress-bar-inner { height:100%; border-radius:100px; background:linear-gradient(90deg,var(--accent) 0%,var(--accent2) 100%); transition:width 1.5s cubic-bezier(.4,0,.2,1); }
        .progress-labels { display:flex; justify-content:space-between; font-size:.78rem; color:var(--muted); }
        .ico-dates { display:flex; gap:16px; margin-top:20px; font-size:.82rem; }
        .ico-dates .ico-date-item span:first-child { color:var(--muted); margin-right:6px; }

        /* ─── Coin Ticker ─────────────────────────────────────────── */
        #ticker { background:var(--bg3); border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:16px 0; overflow:hidden; }
        .ticker-track { display:flex; animation:ticker 35s linear infinite; width:max-content; }
        .ticker-track:hover { animation-play-state:paused; }
        @keyframes ticker { 0%{transform:translateX(0)}100%{transform:translateX(-50%)} }
        .ticker-item { display:flex; align-items:center; gap:10px; padding:0 32px; border-right:1px solid var(--border); white-space:nowrap; }
        .ticker-item img { width:26px; height:26px; border-radius:50%; object-fit:cover; }
        .ti-icon { width:26px; height:26px; border-radius:50%; background:linear-gradient(135deg,var(--accent) 0%,var(--accent2) 100%); display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; flex-shrink:0; }
        .ti-name { font-weight:600; font-size:.85rem; }
        .ti-type { background:rgba(108,99,255,.15); color:var(--accent2); border-radius:6px; padding:2px 8px; font-size:.7rem; font-weight:600; }
        .ti-sign { color:var(--muted); font-size:.78rem; }

        /* ─── Features ────────────────────────────────────────────── */
        .features-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
        .feature-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:36px 30px; transition:transform .25s,border-color .25s,box-shadow .25s; }
        .feature-card:hover { transform:translateY(-6px); border-color:rgba(108,99,255,.4); box-shadow:var(--glow); }
        .feature-icon-wrap { width:56px; height:56px; border-radius:14px; margin-bottom:22px; background:linear-gradient(135deg,rgba(108,99,255,.2) 0%,rgba(56,189,248,.1) 100%); display:flex; align-items:center; justify-content:center; }
        .feature-icon-wrap img { width:28px; height:28px; object-fit:contain; filter:brightness(0) invert(.8) sepia(1) saturate(5) hue-rotate(209deg); }
        .feature-card h3 { font-size:1.1rem; font-weight:700; margin-bottom:10px; }
        .feature-card p { color:var(--muted); font-size:.9rem; line-height:1.7; }

        /* ─── About ────────────────────────────────────────────────── */
        .about-grid { display:grid; grid-template-columns:1fr 1fr; gap:80px; align-items:center; margin-bottom:80px; }
        .about-grid:last-of-type { margin-bottom:0; }
        .about-img-wrap { position:relative; }
        .about-img-wrap img { width:100%; border-radius:20px; }
        .about-img-wrap::before { content:''; position:absolute; inset:-14px; border-radius:24px; background:linear-gradient(135deg,rgba(108,99,255,.15) 0%,transparent 70%); border:1px solid rgba(108,99,255,.15); z-index:-1; }
        .about-text h2 { font-size:clamp(1.6rem,2.5vw,2.2rem); font-weight:800; margin-bottom:18px; line-height:1.25; }
        .about-text p { color:var(--muted); line-height:1.8; }
        .badge-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:28px; }
        .badge { background:rgba(108,99,255,.12); border:1px solid rgba(108,99,255,.25); color:var(--accent2); border-radius:100px; padding:5px 16px; font-size:.78rem; font-weight:600; }

        /* ─── Roadmap ──────────────────────────────────────────────── */
        .roadmap-line { position:relative; }
        .roadmap-line::before { content:''; position:absolute; left:50%; top:0; bottom:0; width:2px; background:linear-gradient(180deg,var(--accent) 0%,var(--accent2) 50%,transparent 100%); transform:translateX(-50%); }
        .roadmap-items { display:flex; flex-direction:column; }
        .roadmap-item { display:grid; grid-template-columns:1fr 60px 1fr; align-items:start; }
        .rm-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:28px; margin:0 24px 40px; transition:border-color .25s; }
        .rm-card:hover { border-color:rgba(108,99,255,.5); }
        .rm-card .rm-date { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.12em; color:var(--accent2); margin-bottom:10px; }
        .rm-card h3 { font-size:1rem; font-weight:700; margin-bottom:8px; }
        .rm-card p { color:var(--muted); font-size:.88rem; line-height:1.65; }
        .rm-dot-wrap { display:flex; justify-content:center; align-items:flex-start; padding-top:28px; }
        .rm-dot { width:18px; height:18px; border-radius:50%; background:linear-gradient(135deg,var(--accent) 0%,var(--accent2) 100%); box-shadow:0 0 0 5px rgba(108,99,255,.2),0 0 20px rgba(108,99,255,.4); z-index:1; }

        /* ─── Staking Pools ───────────────────────────────────────── */
        #staking { background:var(--bg3); }
        .pools-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
        .pool-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:32px; display:flex; flex-direction:column; gap:16px; transition:transform .25s,border-color .25s,box-shadow .25s; }
        .pool-card:hover { transform:translateY(-6px); border-color:rgba(108,99,255,.4); box-shadow:var(--glow); }
        .pool-card-header { display:flex; align-items:center; justify-content:space-between; }
        .pool-apy { font-size:2rem; font-weight:900; }
        .pool-apy-label { font-size:.72rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.1em; }
        .pool-tag { background:rgba(16,185,129,.12); color:var(--green); border:1px solid rgba(16,185,129,.25); border-radius:8px; padding:4px 12px; font-size:.75rem; font-weight:700; }
        .pool-name { font-size:1.1rem; font-weight:700; }
        .pool-meta { display:flex; flex-direction:column; gap:8px; }
        .pool-meta-row { display:flex; justify-content:space-between; font-size:.83rem; }
        .pool-meta-row span:first-child { color:var(--muted); }
        .pool-meta-row span:last-child { font-weight:600; }
        .pool-desc { color:var(--muted); font-size:.85rem; line-height:1.65; flex:1; }
        .pool-cta { margin-top:auto; }

        /* ─── Integration ─────────────────────────────────────────── */
        .integration-grid { display:grid; grid-template-columns:1fr 1fr; gap:80px; align-items:center; }
        .integration-text h2 { font-size:clamp(1.6rem,2.5vw,2.2rem); font-weight:800; margin-bottom:18px; line-height:1.25; }
        .integration-text p { color:var(--muted); line-height:1.8; margin-bottom:30px; }
        .integration-visual { display:flex; justify-content:center; }
        .integration-visual img { border-radius:20px; box-shadow:var(--shadow),var(--glow); }

        /* ─── Coins List ──────────────────────────────────────────── */
        #coins-section { background:var(--bg2); }
        .coins-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:18px; }
        .coin-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:24px 20px; display:flex; align-items:center; gap:16px; transition:transform .2s,border-color .2s; }
        .coin-card:hover { transform:translateY(-4px); border-color:rgba(108,99,255,.35); }
        .coin-icon-wrap { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,rgba(108,99,255,.2) 0%,rgba(56,189,248,.1) 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .coin-icon-wrap img { width:30px; height:30px; border-radius:50%; object-fit:cover; }
        .coin-abbr { font-size:1rem; font-weight:800; color:var(--accent2); }
        .coin-info .coin-name { font-size:.9rem; font-weight:700; margin-bottom:2px; }
        .coin-info .coin-badge { background:rgba(108,99,255,.12); color:var(--accent2); border-radius:6px; padding:2px 8px; font-size:.68rem; font-weight:600; }

        /* ─── FAQ ─────────────────────────────────────────────────── */
        .faq-list { max-width:760px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
        .faq-item { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
        .faq-q { width:100%; display:flex; align-items:center; justify-content:space-between; padding:20px 24px; cursor:pointer; font-weight:600; font-size:.95rem; background:none; border:none; color:var(--text); text-align:left; transition:color .2s; }
        .faq-q:hover { color:var(--accent2); }
        .faq-icon { width:24px; height:24px; border-radius:6px; background:rgba(108,99,255,.15); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:transform .3s,background .3s; }
        .faq-icon svg { width:14px; height:14px; stroke:var(--accent2); }
        .faq-item.open .faq-icon { background:rgba(108,99,255,.3); transform:rotate(45deg); }
        .faq-a { display:none; padding:0 24px 20px; color:var(--muted); font-size:.9rem; line-height:1.75; }
        .faq-item.open .faq-a { display:block; }

        /* ─── Contact ─────────────────────────────────────────────── */
        .contact-grid { display:grid; grid-template-columns:1fr 1fr; gap:60px; }
        .contact-info-list { display:flex; flex-direction:column; gap:24px; margin-top:12px; }
        .ci-item { display:flex; gap:18px; align-items:flex-start; }
        .ci-icon { width:46px; height:46px; border-radius:12px; background:rgba(108,99,255,.12); border:1px solid rgba(108,99,255,.2); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .ci-icon img { width:22px; height:22px; object-fit:contain; filter:brightness(0) invert(.8) sepia(1) saturate(5) hue-rotate(209deg); }
        .ci-item h4 { font-size:.82rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:4px; }
        .ci-item p { font-size:.95rem; font-weight:500; }
        .contact-form-wrap { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:36px; }
        .contact-form-wrap h3 { font-size:1.25rem; font-weight:700; margin-bottom:24px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group label { font-size:.78rem; color:var(--muted); font-weight:500; }
        .form-group input,.form-group textarea { background:var(--bg2); border:1px solid var(--border); border-radius:10px; padding:11px 16px; color:var(--text); font-size:.9rem; width:100%; transition:border-color .2s,box-shadow .2s; }
        .form-group input:focus,.form-group textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(108,99,255,.15); }
        .form-group textarea { min-height:110px; resize:vertical; }
        .text-danger { font-size:.78rem; color:#f87171; margin-top:3px; }
        .form-submit { margin-top:18px; width:100%; justify-content:center; }

        /* ─── Footer ──────────────────────────────────────────────── */
        #footer { background:var(--bg); border-top:1px solid var(--border); padding:70px 0 0; }
        .footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:48px; padding-bottom:60px; }
        .footer-brand img { height:34px; margin-bottom:20px; display:block; }
        .footer-brand p { color:var(--muted); font-size:.9rem; line-height:1.75; max-width:280px; }
        .footer-col h4 { font-size:.82rem; text-transform:uppercase; letter-spacing:.12em; color:var(--muted); margin-bottom:18px; font-weight:600; }
        .footer-col ul { display:flex; flex-direction:column; gap:11px; }
        .footer-col ul a { color:var(--muted); font-size:.9rem; transition:color .2s; }
        .footer-col ul a:hover { color:var(--text); }
        .social-row { display:flex; gap:10px; margin-top:24px; flex-wrap:wrap; }
        .social-btn { width:38px; height:38px; border-radius:10px; background:var(--card); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:.85rem; transition:all .2s; }
        .social-btn:hover { background:rgba(108,99,255,.15); border-color:rgba(108,99,255,.4); color:var(--accent2); }
        .footer-bottom { border-top:1px solid var(--border); padding:22px 0; text-align:center; color:var(--muted); font-size:.85rem; }
        .footer-bottom a { color:var(--accent2); }

        /* ─── Scroll to top ───────────────────────────────────────── */
        #scrollTop { position:fixed; bottom:28px; right:28px; z-index:500; width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,var(--accent) 0%,var(--accent2) 100%); display:flex; align-items:center; justify-content:center; opacity:0; transform:translateY(14px); transition:all .3s; cursor:pointer; box-shadow:0 4px 18px rgba(108,99,255,.45); }
        #scrollTop.visible { opacity:1; transform:translateY(0); }
        #scrollTop svg { width:18px; height:18px; stroke:#fff; }

        /* ─── Toasts ──────────────────────────────────────────────── */
        #toastMessages { position:fixed; top:80px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:10px; }
        .toast-msg { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:14px 20px; font-size:.88rem; font-weight:500; box-shadow:var(--shadow); animation:toastin .3s ease; max-width:340px; }
        .toast-msg.success { border-left:3px solid var(--green); }
        .toast-msg.error   { border-left:3px solid #f87171; }
        @keyframes toastin { from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)} }

        /* ─── Reveal ──────────────────────────────────────────────── */
        .reveal { opacity:0; transform:translateY(28px); transition:opacity .6s ease,transform .6s ease; }
        .reveal.visible { opacity:1; transform:translateY(0); }

        /* ─── Responsive ──────────────────────────────────────────── */
        @media(max-width:1024px) {
            .features-grid,.pools-grid { grid-template-columns:1fr 1fr; }
            .footer-grid { grid-template-columns:1fr 1fr; gap:36px; }
            .stats-grid { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:768px) {
            #navbar { padding:14px 20px; }
            #navbar.scrolled { padding:10px 20px; }
            .nav-links,.nav-auth { display:none; }
            .hamburger { display:flex; }
            .hero-inner,.about-grid,.integration-grid,.contact-grid,.ico-inner { grid-template-columns:1fr; gap:40px; }
            .hero-visual { display:none; }
            .features-grid,.pools-grid { grid-template-columns:1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .footer-grid { grid-template-columns:1fr; gap:32px; }
            .form-row { grid-template-columns:1fr; }
            .roadmap-line::before { left:24px; }
            .roadmap-item { grid-template-columns:1fr; }
            .rm-dot-wrap { display:none; }
            .rm-card { margin:0 0 20px 48px; }
            .roadmap-items { position:relative; padding-left:24px; }
            .roadmap-items::before { content:''; position:absolute; left:8px; top:0; bottom:0; width:2px; background:linear-gradient(180deg,var(--accent) 0%,var(--accent2) 100%); }
            .ico-meta { gap:12px; }
        }
        @media(max-width:480px) {
            .stats-grid { grid-template-columns:1fr; }
            .hero-title { font-size:2rem; }
        }
    </style>
</head>
<body>

{{-- ─── Toast Messages ─────────────────────────────── --}}
<div id="toastMessages">
    @if(session('success'))
        <div class="toast-msg success">{{ session('success') }}</div>
    @endif
    @if(session('error') || session('dismiss'))
        <div class="toast-msg error">{{ session('error') ?? session('dismiss') }}</div>
    @endif
    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <div class="toast-msg error">{{ $error }}</div>
        @endforeach
    @endif
</div>

{{-- ─── Mobile Drawer ─────────────────────────────────── --}}
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-close-row">
        <div class="mobile-close-btn" id="mobileClose">&times;</div>
    </div>

    {{-- Price pill --}}
    @if(($obx_price ?? 0) > 0)
    @php $navPrice = $obx_price ?? 0; $navChange = $obx_change ?? 0; @endphp
    <div class="mobile-menu-price">
        <span class="np-dot" style="width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);animation:pulse 2s infinite;flex-shrink:0"></span>
        <span class="mm-label">OBX {{ __('Price') }}</span>
        <span class="mm-price" id="mmPriceVal">${{ number_format($navPrice, 4) }}</span>
        <span class="mm-change {{ $navChange >= 0 ? 'up' : 'down' }}" id="mmPriceChange">{{ ($navChange >= 0 ? '+' : '') . number_format($navChange, 2) }}%</span>
    </div>
    @endif

    {{-- Nav links --}}
    <div class="mobile-nav-links">
        <a href="#hero">{{ __('Home') }}</a>
        <a href="#features">{{ __('Features') }}</a>
        <a href="#about">{{ __('About') }}</a>
        <a href="#roadmap">{{ __('Roadmap') }}</a>
        @if(isset($staking_pools) && $staking_pools->count())
        <a href="#staking">{{ __('Staking') }}</a>
        @endif
        <a href="#faq">{{ __('FAQ') }}</a>
        <a href="#contact">{{ __('Contact') }}</a>
        @if(Auth::check())
        <a href="{{ route('logOut') }}">{{ __('Logout') }}</a>
        @endif
    </div>

    {{-- Auth buttons --}}
    @if(!Auth::check())
    <div class="mobile-menu-divider"></div>
    <div class="mobile-menu-actions">
        <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Login') }}</a>
        <a href="{{ route('signUp') }}" class="btn btn-primary">{{ __('Sign Up') }}</a>
    </div>
    @endif
</div>

{{-- ─── Navbar ───────────────────────────────────────── --}}
<nav id="navbar">
    <div class="nav-logo">
        <a href="{{ url('/') }}">
            <img src="{{ landingPageImage('logo','images/logo.svg') }}" alt="{{ settings('app_title') }}">
        </a>
    </div>
    <div class="nav-links">
        <a href="#hero">{{ __('Home') }}</a>
        <a href="#features">{{ __('Features') }}</a>
        <a href="#about">{{ __('About') }}</a>
        <a href="#roadmap">{{ __('Roadmap') }}</a>
        @if(isset($staking_pools) && $staking_pools->count())
        <a href="#staking">{{ __('Staking') }}</a>
        @endif
        <a href="#faq">{{ __('FAQ') }}</a>
        <a href="#contact">{{ __('Contact') }}</a>
    </div>
    @php $navPrice = $obx_price ?? 0; $navChange = $obx_change ?? 0; @endphp
    @if($navPrice > 0)
    <span class="nav-price" id="navPricePill">
        <span class="np-dot"></span>
        <span>OBX</span>
        <span class="np-val" id="navPriceVal">${{ number_format($navPrice, 4) }}</span>
        <span class="np-change {{ $navChange >= 0 ? 'up' : 'down' }}" id="navPriceChange">{{ ($navChange >= 0 ? '+' : '') . number_format($navChange, 2) }}%</span>
    </span>
    @endif
    <div class="nav-auth">
        @if(Auth::check())
            <a href="{{ route('logOut') }}" class="btn btn-ghost">{{ __('Logout') }}</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Login') }}</a>
            <a href="{{ route('signUp') }}" class="btn btn-primary">{{ __('Sign Up') }}</a>
        @endif
    </div>
    <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
</nav>

{{-- ─── Hero ─────────────────────────────────────────── --}}
<section id="hero">
    <div class="hero-grid"></div>
    <div class="container">
        <div class="hero-inner">
            {{-- Left: copy --}}
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="dot"></span>
                    {{ $content['landing_hero_badge'] ?? __('Live & Secure Platform') }}
                </div>
                <h1 class="hero-title">
                    @if(isset($content['landing_title']))
                        {!! clean($content['landing_title']) !!}
                    @else
                        <span class="gradient-text">{{ __('The Future of') }}</span><br>{{ __('Crypto Wallets') }}
                    @endif
                </h1>
                <p class="hero-desc">
                    @if(isset($content['landing_description']))
                        {!! clean($content['landing_description']) !!}
                    @else
                        {{ __('A secure, fast, and intuitive platform to manage, send, and grow your digital assets — all in one place.') }}
                    @endif
                </p>
                <div class="hero-btns">
                    <a href="{{ $content['landing_1st_button_link'] ?? route('signUp') }}" class="btn btn-primary btn-lg">
                        {{ $content['landing_1st_button_text'] ?? __('Get Started') }}
                    </a>
                    <a href="{{ $content['landing_2nd_button_link'] ?? '#about' }}" class="btn btn-outline btn-lg">
                        {{ $content['landing_2nd_button_text'] ?? __('Learn More') }}
                    </a>
                    @if(!empty($content['whitepaper_url']))
                    <a href="{{ $content['whitepaper_url'] }}" target="_blank" rel="noopener" class="btn btn-ghost btn-lg" style="display:inline-flex;align-items:center;gap:8px;">
                        <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        {{ __('Whitepaper') }}
                    </a>
                    @endif
                </div>
            </div>

            {{-- Right: live price mockup --}}
            <div class="hero-visual">
                <div class="hero-card-glow"></div>
                <div class="hero-mockup">
                    <div class="mock-header">
                        <h4>{{ settings('app_title') }} · OBX/USD</h4>
                        <span class="mock-live"><span class="live-dot"></span>{{ __('Live') }}</span>
                    </div>

                    @php
                        $price     = $obx_price  ?? 0;
                        $change    = $obx_change ?? 0;
                        $isUp      = $change >= 0;
                        $changeStr = ($isUp ? '+' : '') . number_format($change, 2) . '%';
                        $priceStr  = '$' . number_format($price, $price > 0 ? 4 : 2);
                    @endphp

                    <div class="mock-price-row">
                        <div id="livePrice" class="mock-price gradient-text">{{ $priceStr }}</div>
                        <span id="liveChange" class="mock-change {{ $isUp ? 'up' : 'down' }}">{{ $changeStr }}</span>
                    </div>
                    <div id="liveUpdated" class="mock-updated">
                        @if(settings('obx_price_last_updated'))
                            {{ __('Updated') }}: {{ \Carbon\Carbon::parse(settings('obx_price_last_updated'))->diffForHumans() }}
                        @else
                            {{ __('Price fetched from admin settings') }}
                        @endif
                    </div>

                    <div class="mock-chart">
                        <span style="height:35%"></span><span style="height:55%"></span><span style="height:42%"></span>
                        <span style="height:70%"></span><span style="height:60%"></span><span style="height:85%"></span>
                        <span style="height:72%"></span><span style="height:95%"></span><span style="height:80%"></span>
                        <span style="height:100%"></span>
                    </div>

                    <div class="mock-row"><span>{{ __('Platform Users') }}</span><span>{{ number_format($stats['total_users'] ?? 0) }}</span></div>
                    <div class="mock-row"><span>{{ __('Supported Coins') }}</span><span>{{ $stats['total_coins'] ?? 0 }}</span></div>
                    <div class="mock-row"><span>{{ __('Total Staked') }}</span><span class="val-green">{{ number_format($stats['total_staked'] ?? 0, 2) }} OBX</span></div>

                    <div class="mock-coins">
                        @foreach($coins->take(4) as $coin)
                            <span class="mock-coin">{{ $coin->sign ?: strtoupper(substr($coin->name,0,3)) }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── Token Info Bar ──────────────────────────────────── --}}
@if(($content['landing_show_token_info'] ?? '1') == '1')
@php
    $tibContract  = $content['contract_address'] ?? '';
    $tibChain     = $content['coin_blockchain_name'] ?? '';
    $tibSupply    = $content['obx_total_supply'] ?? '';
    $tibLaunch    = $content['coin_launch_date'] ?? '';
    $tibChainLink = $content['chain_link'] ?? '';
    $tibCoinName  = settings('coin_name') ?: 'OBXCoin';
@endphp
<div id="token-info">
    <div class="container">
        <div class="tib-inner">
            <div class="tib-item">
                <span class="tib-label">{{ __('Token') }}</span>
                <span class="tib-value">{{ $tibCoinName }}</span>
            </div>
            @if($tibChain)
            <div class="tib-item">
                <span class="tib-label">{{ __('Blockchain') }}</span>
                <span class="tib-value">{{ $tibChain }}</span>
            </div>
            @endif
            @if($tibSupply)
            <div class="tib-item">
                <span class="tib-label">{{ __('Total Supply') }}</span>
                <span class="tib-value">{{ number_format((float)$tibSupply) }}</span>
            </div>
            @endif
            @if($obx_price > 0)
            <div class="tib-item">
                <span class="tib-label">{{ __('Price') }}</span>
                <span class="tib-value gradient-text">${{ number_format($obx_price, 4) }}</span>
            </div>
            @endif
            @if(!empty($content['obx_market_cap']) && (float)$content['obx_market_cap'] > 0)
            <div class="tib-item">
                <span class="tib-label">{{ __('Market Cap') }}</span>
                <span class="tib-value">${{ number_format((float)$content['obx_market_cap']) }}</span>
            </div>
            @endif
            @if($tibLaunch)
            <div class="tib-item">
                <span class="tib-label">{{ __('Launch') }}</span>
                <span class="tib-value">{{ $tibLaunch }}</span>
            </div>
            @endif
            @if($tibContract)
            <div class="tib-item">
                <span class="tib-label">{{ __('Contract') }}</span>
                <span class="tib-value" title="{{ $tibContract }}">
                    @if($tibChainLink)
                    <a href="{{ $tibChainLink }}" target="_blank" rel="noopener">{{ substr($tibContract,0,8).'...' }}</a>
                    @else
                    {{ substr($tibContract,0,8).'...' }}
                    @endif
                </span>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ─── Coin Ticker ─────────────────────────────────── --}}
@if($coins->count())
<div id="ticker">
    <div style="overflow:hidden">
        <div class="ticker-track">
            @foreach($coins as $coin)
            <div class="ticker-item">
                @if(!empty($coin->coin_icon))
                    <img src="{{ show_image_path($coin->coin_icon,'coin/') }}" alt="{{ $coin->name }}">
                @else
                    <span class="ti-icon">{{ strtoupper(substr($coin->name,0,2)) }}</span>
                @endif
                <span class="ti-name">{!! clean($coin->name) !!}</span>
                @if($coin->sign)
                    <span class="ti-sign">{{ $coin->sign }}</span>
                @endif
                <span class="ti-type">{{ strtoupper($coin->type ?? 'TOKEN') }}</span>
            </div>
            @endforeach
            {{-- duplicate for seamless loop --}}
            @foreach($coins as $coin)
            <div class="ticker-item">
                @if(!empty($coin->coin_icon))
                    <img src="{{ show_image_path($coin->coin_icon,'coin/') }}" alt="{{ $coin->name }}">
                @else
                    <span class="ti-icon">{{ strtoupper(substr($coin->name,0,2)) }}</span>
                @endif
                <span class="ti-name">{!! clean($coin->name) !!}</span>
                @if($coin->sign)
                    <span class="ti-sign">{{ $coin->sign }}</span>
                @endif
                <span class="ti-type">{{ strtoupper($coin->type ?? 'TOKEN') }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ─── Active ICO Phase ─────────────────────────────── --}}
@if(isset($active_phase) && $active_phase)
<section id="ico-phase">
    <div class="container">
        <div class="ico-inner reveal">
            <div class="ico-text">
                <span class="phase-tag">{{ __('Active ICO') }}</span>
                <h2>{{ $active_phase->phase_name }}</h2>
                <p>{{ __('Join the active token sale. Purchase OBX tokens at the current phase rate before it ends.') }}</p>
                <div class="ico-meta">
                    <div class="ico-meta-item">
                        <div class="im-label">{{ __('Rate') }}</div>
                        <div class="im-val accent">${{ number_format($active_phase->rate, 4) }}</div>
                    </div>
                    @if($active_phase->bonus > 0)
                    <div class="ico-meta-item">
                        <div class="im-label">{{ __('Bonus') }}</div>
                        <div class="im-val accent">+{{ $active_phase->bonus }}%</div>
                    </div>
                    @endif
                    @if($active_phase->fees > 0)
                    <div class="ico-meta-item">
                        <div class="im-label">{{ __('Fees') }}</div>
                        <div class="im-val">{{ $active_phase->fees }}%</div>
                    </div>
                    @endif
                </div>
                <a href="{{ route('signUp') }}" class="btn btn-primary btn-lg">{{ __('Buy Tokens Now') }}</a>
            </div>
            <div class="ico-progress-wrap">
                <h4>{{ __('Phase Progress') }}</h4>
                @php
                    $phaseTotal = (float) $active_phase->amount;
                    $phaseSold  = 0;
                    try {
                        $phaseSold = (float) \App\Model\BuyCoinHistory::where('phase_id', $active_phase->id)
                            ->where('status', STATUS_SUCCESS)
                            ->sum('coin');
                    } catch (\Exception $e) {}
                    $pct = $phaseTotal > 0 ? min(100, round(($phaseSold / $phaseTotal) * 100, 1)) : 0;
                @endphp
                <div class="progress-bar-outer">
                    <div class="progress-bar-inner" style="width: {{ $pct }}%"></div>
                </div>
                <div class="progress-labels">
                    <span>{{ number_format($phaseSold, 0) }} {{ __('sold') }}</span>
                    <span>{{ $pct }}%</span>
                    <span>{{ number_format($phaseTotal, 0) }} {{ __('total') }}</span>
                </div>
                <div class="ico-dates">
                    @if($active_phase->start_date)
                    <div class="ico-date-item">
                        <span>{{ __('Start') }}:</span>
                        <span>{{ \Carbon\Carbon::parse($active_phase->start_date)->format('M d, Y') }}</span>
                    </div>
                    @endif
                    @if($active_phase->end_date)
                    <div class="ico-date-item">
                        <span>{{ __('End') }}:</span>
                        <span>{{ \Carbon\Carbon::parse($active_phase->end_date)->format('M d, Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ─── Features ─────────────────────────────────────── --}}
<section class="section" id="features">
    <div class="container">
        <div class="text-center reveal">
            <p class="section-label">{{ __('Why Choose Us') }}</p>
            <h2 class="section-title">
                @if(isset($content['landing_feature_title'])) {!! clean($content['landing_feature_title']) !!}
                @else {{ __('Built for the Modern Trader') }}
                @endif
            </h2>
            <p class="section-sub">
                @if(isset($content['landing_feature_subtitle'])) {!! clean($content['landing_feature_subtitle']) !!}
                @else {{ __('Everything you need to manage your crypto portfolio with confidence and ease.') }}
                @endif
            </p>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <div class="feature-icon-wrap">
                    <img src="{{ landingPageImage('1st_feature_icon','images/feature/1.svg') }}" alt="">
                </div>
                <h3>@if(isset($content['1st_feature_title'])) {!! clean($content['1st_feature_title']) !!} @else {{ __('Instant Exchange') }} @endif</h3>
                <p>@if(isset($content['1st_feature_subtitle'])) {!! clean($content['1st_feature_subtitle']) !!} @else {{ __('Swap between supported assets instantly with no hidden fees and market-rate pricing.') }} @endif</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon-wrap">
                    <img src="{{ landingPageImage('2nd_feature_icon','images/feature/2.svg') }}" alt="">
                </div>
                <h3>@if(isset($content['2nd_feature_title'])) {!! clean($content['2nd_feature_title']) !!} @else {{ __('Instant Cashout') }} @endif</h3>
                <p>@if(isset($content['2nd_feature_subtitle'])) {!! clean($content['2nd_feature_subtitle']) !!} @else {{ __('Withdraw your earnings to your bank or crypto wallet instantly, 24/7.') }} @endif</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon-wrap">
                    <img src="{{ landingPageImage('3rd_feature_icon','images/feature/3.svg') }}" alt="">
                </div>
                <h3>@if(isset($content['3rd_feature_title'])) {!! clean($content['3rd_feature_title']) !!} @else {{ __('Safe & Secure') }} @endif</h3>
                <p>@if(isset($content['3rd_feature_subtitle'])) {!! clean($content['3rd_feature_subtitle']) !!} @else {{ __('Military-grade encryption and multi-factor authentication keep your assets safe.') }} @endif</p>
            </div>
        </div>
    </div>
</section>

{{-- ─── Supported Coins ──────────────────────────────── --}}
@if($coins->count())
<section class="section" id="coins-section" style="background:var(--bg2);padding-top:70px;padding-bottom:70px">
    <div class="container">
        <div class="text-center reveal" style="margin-bottom:40px">
            <p class="section-label">{{ __('Supported Assets') }}</p>
            <h2 class="section-title">{{ $stats['total_coins'] }} {{ __('Active Coins & Tokens') }}</h2>
        </div>
        <div class="coins-grid">
            @foreach($coins as $coin)
            <div class="coin-card reveal">
                <div class="coin-icon-wrap">
                    @if(!empty($coin->coin_icon))
                        <img src="{{ show_image_path($coin->coin_icon,'coin/') }}" alt="{{ $coin->name }}">
                    @else
                        <span class="coin-abbr">{{ strtoupper(substr($coin->name,0,2)) }}</span>
                    @endif
                </div>
                <div class="coin-info">
                    <div class="coin-name">{!! clean($coin->name) !!}</div>
                    @if($coin->sign)
                        <div class="coin-badge">{{ $coin->sign }}</div>
                    @elseif($coin->type)
                        <div class="coin-badge">{{ strtoupper($coin->type) }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─── About ────────────────────────────────────────── --}}
<section class="section" id="about">
    <div class="container">
        <div class="about-grid reveal">
            <div class="about-img-wrap">
                <img src="{{ landingPageImage('about_1st_logo','images/banner/ab2.svg') }}" alt="about">
            </div>
            <div class="about-text">
                <p class="section-label">{{ __('About Us') }}</p>
                <h2>@if(isset($content['about_1st_title'])) {!! clean($content['about_1st_title']) !!} @else {{ __("We've Built a Platform You Can Trust") }} @endif</h2>
                <p>@if(isset($content['about_1st_description'])) {!! clean($content['about_1st_description']) !!} @else {{ __('While existing solutions offer to solve just one problem at a time, our team built a secure, useful, and easy-to-use product based on private blockchain technology.') }} @endif</p>
                <div class="badge-row">
                    <span class="badge">{{ __('Non-Custodial') }}</span>
                    <span class="badge">{{ __('Open Source') }}</span>
                    <span class="badge">{{ __('Audited') }}</span>
                </div>
            </div>
        </div>
        <div class="about-grid reveal" style="direction:rtl">
            <div class="about-img-wrap" style="direction:ltr">
                <img src="{{ landingPageImage('about_2nd_logo','images/banner/ab3.svg') }}" alt="about">
            </div>
            <div class="about-text" style="direction:ltr">
                <p class="section-label">{{ __('Our Mission') }}</p>
                <h2>@if(isset($content['about_2nd_title'])) {!! clean($content['about_2nd_title']) !!} @else {{ __('Empowering Everyone in the Digital Economy') }} @endif</h2>
                <p>@if(isset($content['about_2nd_description'])) {!! clean($content['about_2nd_description']) !!} @else {{ __('We aim to integrate all companies, employees, and business assets into a unified blockchain ecosystem, making business truly efficient, transparent, and reliable.') }} @endif</p>
                <div class="badge-row">
                    <span class="badge">{{ __('Decentralized') }}</span>
                    <span class="badge">{{ __('Global') }}</span>
                    <span class="badge">{{ __('Transparent') }}</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── Tokenomics ───────────────────────────────────── --}}
@if(($content['landing_show_tokenomics'] ?? '1') == '1')
@php
    $tokoSlices = [
        ['label' => $content['tokenomics_public_sale_label'] ?? 'Public Sale',     'pct' => (float)($content['tokenomics_public_sale_pct']  ?? 40), 'color' => '#6c63ff'],
        ['label' => $content['tokenomics_team_label']        ?? 'Team & Advisors', 'pct' => (float)($content['tokenomics_team_pct']         ?? 15), 'color' => '#38bdf8'],
        ['label' => $content['tokenomics_ecosystem_label']   ?? 'Ecosystem Fund',  'pct' => (float)($content['tokenomics_ecosystem_pct']    ?? 20), 'color' => '#4ade80'],
        ['label' => $content['tokenomics_reserve_label']     ?? 'Reserve',         'pct' => (float)($content['tokenomics_reserve_pct']      ?? 10), 'color' => '#fb923c'],
        ['label' => $content['tokenomics_liquidity_label']   ?? 'Liquidity',       'pct' => (float)($content['tokenomics_liquidity_pct']    ?? 10), 'color' => '#e879f9'],
        ['label' => $content['tokenomics_marketing_label']   ?? 'Marketing',       'pct' => (float)($content['tokenomics_marketing_pct']    ??  5), 'color' => '#f59e0b'],
    ];
    $tokoSlices = array_values(array_filter($tokoSlices, fn($s) => $s['pct'] > 0));
    $totalSupply = (float)($content['obx_total_supply'] ?? 100000000);
    // SVG donut: r=110, circumference = 2*pi*r ≈ 691.15
    $r = 110; $c = 2 * M_PI * $r; $svgOffset = 0;
@endphp
<section class="section" id="tokenomics">
    <div class="container">
        <div class="text-center reveal" style="margin-bottom:56px">
            <p class="section-label">{{ __('Transparency') }}</p>
            <h2 class="section-title">{{ $content['tokenomics_section_title'] ?? __('Token Distribution') }}</h2>
            @if(!empty($content['tokenomics_section_subtitle']))
            <p class="section-sub">{{ $content['tokenomics_section_subtitle'] }}</p>
            @endif
        </div>
        <div class="toko-inner reveal">

            {{-- SVG Donut --}}
            <div class="toko-chart-wrap" id="tokoChart">
                <svg viewBox="0 0 280 280">
                    <circle cx="140" cy="140" r="{{ $r }}" fill="none" stroke="var(--border)" stroke-width="38"/>
                    @foreach($tokoSlices as $sl)
                    @php
                        $dash = ($sl['pct'] / 100) * $c;
                        $gap  = $c - $dash;
                        $tokoOffset = $c - $svgOffset;
                        $svgOffset += $dash;
                    @endphp
                    <circle
                        cx="140" cy="140" r="{{ $r }}"
                        class="toko-seg"
                        stroke="{{ $sl['color'] }}"
                        stroke-dasharray="0 {{ round($c, 3) }}"
                        stroke-dashoffset="{{ round($tokoOffset, 3) }}"
                        data-dash="{{ round($dash, 3) }}"
                        data-gap="{{ round($gap, 3) }}"
                        data-circ="{{ round($c, 3) }}"
                        data-label="{{ $sl['label'] }}"
                        data-pct="{{ $sl['pct'] }}"
                        data-color="{{ $sl['color'] }}"
                    />
                    @endforeach
                </svg>
                <div class="toko-chart-center">
                    <div class="tdc-val gradient-text">{{ number_format($totalSupply / 1e6, 0) }}M</div>
                    <span class="tdc-sup">{{ __('Total Supply') }}</span>
                </div>
            </div>

            {{-- Cards --}}
            <div class="toko-cards" id="tokoCards">
                @foreach($tokoSlices as $sl)
                @php $tokens = number_format($totalSupply * $sl['pct'] / 100); @endphp
                <div class="toko-card" style="border-color:{{ $sl['color'] }}33" data-bar-pct="{{ $sl['pct'] }}" data-bar-color="{{ $sl['color'] }}">
                    <div class="toko-card-top">
                        <div class="toko-card-dot" style="background:{{ $sl['color'] }};box-shadow:0 0 8px {{ $sl['color'] }}66"></div>
                        <span class="toko-card-label">{{ $sl['label'] }}</span>
                        <span class="toko-card-pct" style="color:{{ $sl['color'] }}">{{ $sl['pct'] }}%</span>
                    </div>
                    <div class="toko-card-bar-bg">
                        <div class="toko-card-bar" style="background:linear-gradient(90deg,{{ $sl['color'] }}99,{{ $sl['color'] }})"></div>
                    </div>
                    <div class="toko-card-tokens">{{ $tokens }} {{ settings('coin_name') ?: 'OBX' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
<script>
(function(){
    var chart = document.getElementById('tokoChart');
    var cards = document.getElementById('tokoCards');
    if (!chart || !cards) return;
    var segs = chart.querySelectorAll('.toko-seg');
    var bars = cards.querySelectorAll('[data-bar-pct]');
    var animated = false;
    function animate() {
        if (animated) return;
        animated = true;
        segs.forEach(function(seg, i) {
            var dash = parseFloat(seg.dataset.dash);
            var gap  = parseFloat(seg.dataset.gap);
            setTimeout(function() {
                seg.style.transition = 'stroke-dasharray .9s cubic-bezier(.4,0,.2,1)';
                seg.style.strokeDasharray = dash + ' ' + gap;
            }, i * 100);
        });
        bars.forEach(function(card, i) {
            var pct = card.dataset.barPct;
            var bar = card.querySelector('.toko-card-bar');
            if (bar) setTimeout(function(){ bar.style.width = pct + '%'; }, 300 + i * 60);
        });
    }
    var obs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e){ if (e.isIntersecting) { animate(); obs.unobserve(e.target); } });
    }, { threshold: 0.2 });
    obs.observe(chart);

    /* ── Tooltip ── */
    var tip = document.createElement('div');
    tip.className = 'toko-tip';
    tip.innerHTML = '<div class="tt-name"></div><div class="tt-pct"></div>';
    document.body.appendChild(tip);
    var tipName = tip.querySelector('.tt-name');
    var tipPct  = tip.querySelector('.tt-pct');

    function positionTip(e) {
        var x = e.clientX + 14, y = e.clientY - 10;
        if (x + tip.offsetWidth + 16 > window.innerWidth) x = e.clientX - tip.offsetWidth - 14;
        if (y + tip.offsetHeight + 8 > window.innerHeight) y = e.clientY - tip.offsetHeight - 8;
        tip.style.left = x + 'px';
        tip.style.top  = y + 'px';
    }

    segs.forEach(function(seg) {
        seg.addEventListener('mouseenter', function(e) {
            tipName.textContent = seg.dataset.label;
            tipName.style.color = seg.dataset.color;
            tipPct.textContent  = seg.dataset.pct + '%';
            tipPct.style.color  = seg.dataset.color;
            tip.style.borderColor = seg.dataset.color + '55';
            positionTip(e);
            tip.classList.add('show');
        });
        seg.addEventListener('mousemove', positionTip);
        seg.addEventListener('mouseleave', function() {
            tip.classList.remove('show');
        });
    });
})();
</script>

{{-- ─── Roadmap ──────────────────────────────────────── --}}
<section class="section" id="roadmap" style="background:var(--bg2)">
    <div class="container">
        <div class="text-center reveal">
            <p class="section-label">{{ __('Our Journey') }}</p>
            <h2 class="section-title">@if(isset($content['landing_roadmap_title'])) {{ $content['landing_roadmap_title'] }} @else {{ __('Project Roadmap') }} @endif</h2>
            <p class="section-sub">@if(isset($content['landing_roadmap_subtitle'])) {!! clean($content['landing_roadmap_subtitle']) !!} @else {{ __('Track our milestones and see what\'s planned for the future.') }} @endif</p>
        </div>
        <div class="roadmap-line">
            <div class="roadmap-items">
                @php
                    $rms = [
                        ['date' => $content['roadmap_1st_date'] ?? __('June 2020'),  'title' => $content['roadmap_1st_title'] ?? __('Project Concept'),      'sub' => $content['roadmap_1st_subtitle'] ?? __('Initial concept whitepaper and team formation.')],
                        ['date' => $content['roadmap_2nd_date'] ?? __('Sep 2020'),   'title' => $content['roadmap_2nd_title'] ?? __('Platform Launch'),       'sub' => $content['roadmap_2nd_subtitle'] ?? __('Public launch of the core wallet platform.')],
                        ['date' => $content['roadmap_3rd_date'] ?? __('Jan 2021'),   'title' => $content['roadmap_3rd_title'] ?? __('Exchange Integration'),  'sub' => $content['roadmap_3rd_subtitle'] ?? __('Token exchange features and liquidity pools.')],
                        ['date' => $content['roadmap_4th_date'] ?? __('Jun 2021'),   'title' => $content['roadmap_4th_title'] ?? __('Staking & Rewards'),     'sub' => $content['roadmap_4th_subtitle'] ?? __('Staking pools with competitive APY rewards.')],
                        ['date' => $content['roadmap_5th_date'] ?? __('2022+'),      'title' => $content['roadmap_5th_title'] ?? __('Global Expansion'),      'sub' => $content['roadmap_5th_subtitle'] ?? __('Multi-chain support and DeFi integrations.')],
                    ];
                @endphp
                @foreach($rms as $i => $rm)
                <div class="roadmap-item reveal">
                    @if($i % 2 === 0)
                        <div></div>
                        <div class="rm-dot-wrap"><div class="rm-dot"></div></div>
                        <div class="rm-card"><div class="rm-date">{{ $rm['date'] }}</div><h3>{!! clean($rm['title']) !!}</h3><p>{!! clean($rm['sub']) !!}</p></div>
                    @else
                        <div class="rm-card"><div class="rm-date">{{ $rm['date'] }}</div><h3>{!! clean($rm['title']) !!}</h3><p>{!! clean($rm['sub']) !!}</p></div>
                        <div class="rm-dot-wrap"><div class="rm-dot"></div></div>
                        <div></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ─── Staking Pools ────────────────────────────────── --}}
@if(isset($staking_pools) && $staking_pools->count())
<section class="section" id="staking">
    <div class="container">
        <div class="text-center reveal">
            <p class="section-label">{{ __('Earn Rewards') }}</p>
            <h2 class="section-title">{{ __('Staking Pools') }}</h2>
            <p class="section-sub">{{ __('Lock your OBX tokens in one of our pools and earn competitive APY rewards.') }}</p>
        </div>
        <div class="pools-grid">
            @foreach($staking_pools as $pool)
            <div class="pool-card reveal">
                <div class="pool-card-header">
                    <div>
                        <div class="pool-apy gradient-text">{{ $pool->apy_percent }}</div>
                        <div class="pool-apy-label">{{ __('APY') }}</div>
                    </div>
                    <span class="pool-tag">{{ __('Active') }}</span>
                </div>
                <div class="pool-name">{{ $pool->name }}</div>
                @if($pool->description)
                    <div class="pool-desc">{{ $pool->description }}</div>
                @endif
                <div class="pool-meta">
                    <div class="pool-meta-row">
                        <span>{{ __('Min. Stake') }}</span>
                        <span>{{ number_format($pool->min_amount, 2) }} OBX</span>
                    </div>
                    <div class="pool-meta-row">
                        <span>{{ __('Duration') }}</span>
                        <span>{{ $pool->duration_days }} {{ __('days') }}</span>
                    </div>
                    @if($pool->burn_on_stake_bps > 0)
                    <div class="pool-meta-row">
                        <span>{{ __('Burn on Stake') }}</span>
                        <span>{{ $pool->burn_stake_pct }}</span>
                    </div>
                    @endif
                    @if($pool->burn_on_unstake_bps > 0)
                    <div class="pool-meta-row">
                        <span>{{ __('Burn on Unstake') }}</span>
                        <span>{{ $pool->burn_unstake_pct }}</span>
                    </div>
                    @endif
                    <div class="pool-meta-row">
                        <span>{{ __('Active Stakes') }}</span>
                        <span>{{ $pool->positions()->where('status', 'active')->count() }}</span>
                    </div>
                </div>
                <div class="pool-cta">
                    <a href="{{ route('signUp') }}" class="btn btn-primary" style="width:100%;justify-content:center">
                        {{ __('Start Staking') }}
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─── Integration ──────────────────────────────────── --}}
<section class="section" id="integration" style="background:var(--bg2)">
    <div class="container">
        <div class="integration-grid reveal">
            <div class="integration-text">
                <p class="section-label">{{ __('Integrations') }}</p>
                <h2>@if(isset($content['landing_integration_title'])) {!! clean($content['landing_integration_title']) !!} @else {{ __('Easy Customization & Secure Payment System') }} @endif</h2>
                <p>@if(isset($content['landing_integration_description'])) {!! clean($content['landing_integration_description']) !!} @else {{ __('Connect with the tools and exchanges you already use. Our open API makes integration simple for businesses of any size.') }} @endif</p>
                <a href="{{ $content['landing_integration_button_link'] ?? '#contact' }}" class="btn btn-primary">{{ __('Know More') }}</a>
            </div>
            <div class="integration-visual">
                <img src="{{ landingPageImage('landing_integration_page_logo','images/banner/ab3.png') }}" alt="integration">
            </div>
        </div>
    </div>
</section>

{{-- ─── FAQ ─────────────────────────────────────────── --}}
<section class="section" id="faq">
    <div class="container">
        <div class="text-center reveal">
            <p class="section-label">{{ __('FAQ') }}</p>
            <h2 class="section-title">{{ __('Frequently Asked Questions') }}</h2>
            <p class="section-sub">{{ __('Find quick answers to common questions about the platform.') }}</p>
        </div>
        <div class="faq-list">
            @if(isset($faqs) && $faqs->count())
                @foreach($faqs as $faq)
                <div class="faq-item reveal">
                    <button class="faq-q" type="button">
                        {!! clean($faq->question) !!}
                        <span class="faq-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
                    </button>
                    <div class="faq-a">{!! clean($faq->answer) !!}</div>
                </div>
                @endforeach
            @else
                @foreach([
                    [__('What cryptocurrencies are supported?'), __('We support OBX and other major tokens. Check the platform for the full list of ').$stats['total_coins'].__(' active assets.')],
                    [__('How do I create a wallet?'), __('Sign up, verify your email, and your wallet is created automatically.')],
                    [__('Is my information secure?'), __('Yes — we use industry-standard encryption and 2FA for all accounts.')],
                    [__('How do I stake my tokens?'), __('Navigate to the Staking section in your dashboard and choose from one of our active pools.')],
                ] as $dfaq)
                <div class="faq-item reveal">
                    <button class="faq-q" type="button">
                        {{ $dfaq[0] }}
                        <span class="faq-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
                    </button>
                    <div class="faq-a">{{ $dfaq[1] }}</div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

{{-- ─── Contact ──────────────────────────────────────── --}}
<section class="section" id="contact" style="background:var(--bg2)">
    <div class="container">
        <div class="text-center reveal">
            <p class="section-label">{{ __('Get in Touch') }}</p>
            <h2 class="section-title">@if(isset($content['contact_title'])) {{ $content['contact_title'] }} @else {{ __('Contact Us') }} @endif</h2>
            <p class="section-sub">@if(isset($content['contact_sub_title'])) {!! clean($content['contact_sub_title']) !!} @else {{ __("Have a question? We'd love to hear from you.") }} @endif</p>
        </div>
        <div class="contact-grid">
            <div>
                <div class="contact-info-list">
                    <div class="ci-item reveal">
                        <div class="ci-icon"><img src="{{ landingPageImage('address_icon','images/feature/pin.svg') }}" alt=""></div>
                        <div>
                            <h4>@if(isset($content['address_field_title'])) {{ $content['address_field_title'] }} @else {{ __('Address') }} @endif</h4>
                            <p>@if(isset($content['address_field_details'])) {!! clean($content['address_field_details']) !!} @else {{ __('245 King Street, Victoria 8520') }} @endif</p>
                        </div>
                    </div>
                    <div class="ci-item reveal">
                        <div class="ci-icon"><img src="{{ landingPageImage('phone_icon','images/feature/call.svg') }}" alt=""></div>
                        <div>
                            <h4>@if(isset($content['phone_field_title'])) {{ $content['phone_field_title'] }} @else {{ __('Phone') }} @endif</h4>
                            <p>@if(isset($content['phone_field_details'])) {!! clean($content['phone_field_details']) !!} @else {{ __('0-123-456-7890') }} @endif</p>
                        </div>
                    </div>
                    <div class="ci-item reveal">
                        <div class="ci-icon"><img src="{{ landingPageImage('email_icon','images/feature/email.svg') }}" alt=""></div>
                        <div>
                            <h4>@if(isset($content['email_field_title'])) {{ $content['email_field_title'] }} @else {{ __('Email') }} @endif</h4>
                            <p>@if(isset($content['email_field_details'])) {!! clean($content['email_field_details']) !!} @else {{ __('support@platform.com') }} @endif</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="contact-form-wrap reveal">
                <h3>{{ __('Quick Contact Form') }}</h3>
                <form method="post" action="{{ route('ContactUs') }}" id="contact-form">
                    {{ csrf_field() }}
                    <div class="form-row">
                        <div class="form-group">
                            <label>{{ __('Your Name') }}</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('John Doe') }}">
                        </div>
                        <div class="form-group">
                            <label>{{ __('Email') }}</label>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('you@example.com') }}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>{{ __('Phone') }}</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="{{ __('Optional') }}">
                        </div>
                        <div class="form-group">
                            <label>{{ __('Address') }}</label>
                            <input type="text" name="address" value="{{ old('address') }}" placeholder="{{ __('Optional') }}">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>{{ __('Message') }}</label>
                        <textarea name="description" placeholder="{{ __('How can we help?') }}">{{ old('description') }}</textarea>
                    </div>
                    @if (isset(allsetting()['google_recapcha']) && (allsetting()['google_recapcha'] == STATUS_ACTIVE))
                    <div class="form-group" style="margin-bottom:14px">
                        {!! app('captcha')->display() !!}
                        @error('g-recaptcha-response')
                        <p class="text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif
                    <button type="submit" class="btn btn-primary btn-lg form-submit">{{ __('Send Message') }}</button>
                </form>
            </div>
        </div>
    </div>
</section>

{{-- ─── Footer ───────────────────────────────────────── --}}
<footer id="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="{{ url('/') }}"><img src="{{ landingPageImage('logo','images/logo.svg') }}" alt="{{ settings('app_title') }}"></a>
                <p>@if(isset($content['footer_description'])) {{ $content['footer_description'] }} @else {{ __('A secure, fast, and intuitive platform to manage and grow your digital assets.') }} @endif</p>
                <div class="social-row">
                    @if(isset($content['landing_facebook_link']) && $content['landing_facebook_link'])
                    <a href="{{ $content['landing_facebook_link'] }}" target="_blank" rel="noopener" class="social-btn" title="Facebook">f</a>
                    @endif
                    @if(isset($content['landing_twitter_link']) && $content['landing_twitter_link'])
                    <a href="{{ $content['landing_twitter_link'] }}" target="_blank" rel="noopener" class="social-btn" title="Twitter">&#x1D54F;</a>
                    @endif
                    @if(isset($content['landing_linkedin_link']) && $content['landing_linkedin_link'])
                    <a href="{{ $content['landing_linkedin_link'] }}" target="_blank" rel="noopener" class="social-btn" title="LinkedIn">in</a>
                    @endif
                    @if(isset($content['landing_youtube_link']) && $content['landing_youtube_link'])
                    <a href="{{ $content['landing_youtube_link'] }}" target="_blank" rel="noopener" class="social-btn" title="YouTube">▶</a>
                    @endif
                    @if(isset($content['landing_instagram_link']) && $content['landing_instagram_link'])
                    <a href="{{ $content['landing_instagram_link'] }}" target="_blank" rel="noopener" class="social-btn" title="Instagram">&#9632;</a>
                    @endif
                    @if(!empty($content['landing_telegram_link']))
                    <a href="{{ $content['landing_telegram_link'] }}" target="_blank" rel="noopener" class="social-btn" title="Telegram">&#x2708;</a>
                    @endif
                    @if(!empty($content['landing_discord_link']))
                    <a href="{{ $content['landing_discord_link'] }}" target="_blank" rel="noopener" class="social-btn" title="Discord">&#x25C6;</a>
                    @endif
                    @if(!empty($content['landing_github_link']))
                    <a href="{{ $content['landing_github_link'] }}" target="_blank" rel="noopener" class="social-btn" title="GitHub" style="font-size:.75rem;font-weight:700;">GH</a>
                    @endif
                </div>
            </div>
            <div class="footer-col">
                <h4>{{ __('Platform') }}</h4>
                <ul>
                    <li><a href="#features">{{ __('Features') }}</a></li>
                    <li><a href="#coins-section">{{ __('Coins') }}</a></li>
                    <li><a href="#staking">{{ __('Staking') }}</a></li>
                    <li><a href="#about">{{ __('About') }}</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>{{ __('Pages') }}</h4>
                <ul>
                    @foreach($custom_links as $link)
                    <li><a href="{{ route('getCustomPage',[$link->id, str_replace(' ','-',$link->key)]) }}">{{ $link->key }}</a></li>
                    @endforeach
                    <li><a href="#faq">{{ __('FAQ') }}</a></li>
                    <li><a href="#contact">{{ __('Contact') }}</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>{{ __('Account') }}</h4>
                <ul>
                    @if(Auth::check())
                        <li><a href="{{ route('logOut') }}">{{ __('Logout') }}</a></li>
                    @else
                        <li><a href="{{ route('login') }}">{{ __('Login') }}</a></li>
                        <li><a href="{{ route('signUp') }}">{{ __('Sign Up') }}</a></li>
                    @endif
                </ul>
                @if($obx_price > 0)
                <div style="margin-top:24px;background:var(--card);border:1px solid rgba(108,99,255,.25);border-radius:10px;padding:14px 16px;">
                    <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px">{{ __('OBX Price') }}</div>
                    <div style="font-size:1.2rem;font-weight:800;" class="gradient-text">${{ number_format($obx_price, 4) }}</div>
                    <div style="font-size:.75rem;color:{{ $obx_change >= 0 ? 'var(--green)' : 'var(--red)' }};font-weight:600">
                        {{ $obx_change >= 0 ? '+' : '' }}{{ number_format($obx_change, 2) }}% (24h)
                    </div>
                </div>
                @endif
            </div>
        </div>
        <div class="footer-bottom">
            <p>{{ settings('copyright_text') }} &mdash; <a href="{{ url('/') }}">{{ settings('app_title') }}</a></p>
        </div>
    </div>
</footer>

{{-- ─── Scroll to Top ────────────────────────────────── --}}
<div id="scrollTop">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
</div>

<script src="{{ asset('assets/toast/vanillatoasts.js') }}"></script>
<script>
(function () {
    'use strict';

    /* ── Navbar scroll ─────────────────────────── */
    var navbar = document.getElementById('navbar');
    function onScroll() {
        navbar.classList.toggle('scrolled', window.scrollY > 60);
        document.getElementById('scrollTop').classList.toggle('visible', window.scrollY > 400);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ── Smooth anchor scroll ──────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var t = document.querySelector(this.getAttribute('href'));
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); closeMenu(); }
        });
    });

    /* ── Mobile menu ───────────────────────────── */
    function closeMenu() {
        document.getElementById('hamburger').classList.remove('open');
        document.getElementById('mobileMenu').classList.remove('open');
    }
    document.getElementById('hamburger').addEventListener('click', function () {
        this.classList.toggle('open');
        document.getElementById('mobileMenu').classList.toggle('open');
    });
    document.getElementById('mobileClose').addEventListener('click', closeMenu);

    /* ── Scroll to top ─────────────────────────── */
    document.getElementById('scrollTop').addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ── Reveal on scroll ──────────────────────── */
    var reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
            });
        }, { threshold: 0.1 });
        reveals.forEach(function (el) { obs.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('visible'); });
    }

    /* ── FAQ accordion ─────────────────────────── */
    document.querySelectorAll('.faq-q').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item   = this.closest('.faq-item');
            var isOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(function (el) { el.classList.remove('open'); });
            if (!isOpen) item.classList.add('open');
        });
    });

    /* ── Live OBX price polling ────────────────── */
    var priceEl      = document.getElementById('livePrice');
    var changeEl     = document.getElementById('liveChange');
    var updatedEl    = document.getElementById('liveUpdated');
    var navPriceVal  = document.getElementById('navPriceVal');
    var navPriceChg  = document.getElementById('navPriceChange');
    var mmPriceVal   = document.getElementById('mmPriceVal');
    var mmPriceChg   = document.getElementById('mmPriceChange');
    function fetchPrice() {
        fetch('/api/obx-price')
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.price) return;
                var p  = parseFloat(d.price) || 0;
                var ch = parseFloat(d.change_24h) || 0;
                var pStr  = '$' + p.toLocaleString(undefined, { minimumFractionDigits: p > 0 ? 4 : 2, maximumFractionDigits: 6 });
                var chStr = (ch >= 0 ? '+' : '') + ch.toFixed(2) + '%';
                var chCls = 'np-change ' + (ch >= 0 ? 'up' : 'down');
                // hero mockup
                if (priceEl)  priceEl.textContent = pStr;
                if (changeEl) { changeEl.textContent = chStr; changeEl.className = 'mock-change ' + (ch >= 0 ? 'up' : 'down'); }
                if (updatedEl) updatedEl.textContent = 'Updated: just now';
                // navbar pill
                if (navPriceVal) navPriceVal.textContent = pStr;
                if (navPriceChg) { navPriceChg.textContent = chStr; navPriceChg.className = chCls; }
                // mobile drawer pill
                if (mmPriceVal) mmPriceVal.textContent = pStr;
                if (mmPriceChg) { mmPriceChg.textContent = chStr; mmPriceChg.className = 'mm-change ' + (ch >= 0 ? 'up' : 'down'); }
            })
            .catch(function () {});
    }
    if (priceEl || navPriceVal || mmPriceVal) { fetchPrice(); setInterval(fetchPrice, 30000); }

    /* ── Auto-dismiss toasts ───────────────────── */
    setTimeout(function () {
        document.querySelectorAll('.toast-msg').forEach(function (t) {
            t.style.cssText = 'opacity:0;transform:translateX(10px);transition:.4s';
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 400);
        });
    }, 5000);

})();
</script>
</body>
</html>
