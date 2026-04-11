<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="{{ allsetting('app_title') }}">
    <title>@yield('title') {{ settings('app_title') }}</title>
    <link rel="shortcut icon" href="{{ landingPageImage('favicon','images/fav.png') }}">
    <link href="{{ asset('assets/css/gfont.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/toast/vanillatoasts.css') }}" rel="stylesheet">
    <!-- fontawesome for eye icons -->
    <link rel="stylesheet" href="{{ asset('assets/admin/css/font-awesome.min.css') }}">
    @if (isset(allsetting()['google_recapcha']) && (allsetting()['google_recapcha'] == STATUS_ACTIVE))
        {!! NoCaptcha::renderJs() !!}
    @endif
    <style>
        /* ── Design tokens (mirrors landing page) ──────────────────── */
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
        body { background: var(--bg); color: var(--text); font-family: 'Inter','Segoe UI',sans-serif; line-height: 1.65; min-height: 100vh; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; }

        /* ── Animated background orbs ──────────────────────────────── */
        .auth-bg { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
        .auth-bg span { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .18; }
        .auth-bg .orb1 { width: 500px; height: 500px; background: var(--accent); top: -160px; left: -100px; animation: drift1 18s ease-in-out infinite alternate; }
        .auth-bg .orb2 { width: 400px; height: 400px; background: var(--accent3); bottom: -120px; right: -80px; animation: drift2 22s ease-in-out infinite alternate; }
        .auth-bg .orb3 { width: 260px; height: 260px; background: var(--accent2); top: 50%; left: 50%; transform: translate(-50%,-50%); animation: drift1 14s ease-in-out infinite alternate; }
        @keyframes drift1 { from { transform: translate(0,0); } to { transform: translate(40px, 30px); } }
        @keyframes drift2 { from { transform: translate(0,0); } to { transform: translate(-40px,-30px); } }

        /* ── Page wrapper ──────────────────────────────────────────── */
        .auth-page { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 16px; }

        /* ── Card ──────────────────────────────────────────────────── */
        .auth-card { background: var(--card); border: 1px solid var(--border); border-radius: 24px; box-shadow: var(--shadow), var(--glow); width: 100%; max-width: 460px; padding: 44px 40px; }
        @media(max-width:500px){ .auth-card { padding: 32px 22px; } }

        /* ── Logo & header ─────────────────────────────────────────── */
        .auth-logo-wrap { text-align: center; margin-bottom: 28px; }
        .auth-logo-wrap img { height: 44px; }
        .auth-heading { font-size: 1.55rem; font-weight: 800; text-align: center; margin-bottom: 6px; }
        .auth-sub { color: var(--muted); font-size: .9rem; text-align: center; margin-bottom: 28px; }
        .gradient-text { background: linear-gradient(135deg,var(--accent2) 0%,var(--accent3) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

        /* ── Form ──────────────────────────────────────────────────── */
        .auth-label { display: block; font-size: .82rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
        .auth-label .req { color: var(--accent2); margin-left: 2px; }
        .auth-field { margin-bottom: 18px; }
        .auth-input-wrap { position: relative; }
        .auth-input { width: 100%; background: var(--bg3); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-size: .95rem; padding: 11px 16px; outline: none; transition: border-color .2s, box-shadow .2s; font-family: inherit; }
        .auth-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108,99,255,.18); }
        .auth-input.has-eye { padding-right: 44px; }
        .auth-eye { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; font-size: 1rem; line-height: 1; }
        .auth-eye:hover { color: var(--accent2); }
        .auth-error { display: block; color: var(--red); font-size: .78rem; margin-top: 5px; }

        /* ── Submit button ─────────────────────────────────────────── */
        .auth-btn { width: 100%; background: linear-gradient(135deg,#6C63FF 0%,#a78bfa 100%); color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; padding: 13px; cursor: pointer; transition: transform .2s, box-shadow .2s; box-shadow: 0 4px 20px rgba(108,99,255,.4); margin-top: 8px; font-family: inherit; }
        .auth-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(108,99,255,.55); }
        .auth-btn:active { transform: translateY(0); }

        /* ── Divider / footer links ────────────────────────────────── */
        .auth-divider { text-align: center; color: var(--muted); font-size: .88rem; margin-top: 22px; }
        .auth-divider a { color: var(--accent2); font-weight: 600; transition: color .2s; }
        .auth-divider a:hover { color: var(--accent3); }
        .auth-forgot { text-align: right; margin-top: -10px; margin-bottom: 18px; }
        .auth-forgot a { font-size: .82rem; color: var(--muted); transition: color .2s; }
        .auth-forgot a:hover { color: var(--accent2); }

        /* ── Back-to-home link ─────────────────────────────────────── */
        .auth-back { margin-top: 20px; text-align: center; font-size: .82rem; color: var(--muted); }
        .auth-back a { color: var(--muted); display: inline-flex; align-items: center; gap: 5px; transition: color .2s; }
        .auth-back a:hover { color: var(--text); }

        /* ── reCAPTCHA container ───────────────────────────────────── */
        .auth-captcha { margin-bottom: 18px; }
        .auth-captcha .g-recaptcha { transform-origin: left top; }

        /* ── Alert strip ───────────────────────────────────────────── */
        .auth-alert { border-radius: 10px; padding: 12px 16px; font-size: .88rem; margin-bottom: 18px; border: 1px solid; }
        .auth-alert.success { background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.3); color: #6ee7b7; }
        .auth-alert.danger  { background: rgba(248,113,113,.1);  border-color: rgba(248,113,113,.3); color: #fca5a5; }
    </style>
    @yield('style')
</head>
<body>
<div class="auth-bg" aria-hidden="true">
    <span class="orb1"></span>
    <span class="orb2"></span>
    <span class="orb3"></span>
</div>

@yield('content')

<script src="{{ asset('assets/admin/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/toast/vanillatoasts.js') }}"></script>

@if(session()->has('success'))
<script>
window.addEventListener('load', function(){
    VanillaToasts.create({ text: '{{ addslashes(session('success')) }}', type: 'success', timeout: 10000 });
});
</script>
@elseif(session()->has('dismiss'))
<script>
window.addEventListener('load', function(){
    VanillaToasts.create({ text: '{{ addslashes(session('dismiss')) }}', type: 'warning', timeout: 10000 });
});
</script>
@elseif($errors->any())
@php $firstError = $errors->first(); @endphp
<script>
window.addEventListener('load', function(){
    VanillaToasts.create({ text: '{{ addslashes($firstError) }}', type: 'warning', timeout: 10000 });
});
</script>
@endif

@yield('script')
</body>
</html>

