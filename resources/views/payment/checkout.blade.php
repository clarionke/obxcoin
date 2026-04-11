<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Payment — {{ config('app.name') }}</title>

<style>
/* ── Design tokens ── */
:root {
    --bg:#0b0e1a; --bg2:#111422; --bg3:#161b2e; --card:#1a1f35;
    --border:rgba(255,255,255,.07); --accent:#6C63FF; --accent2:#a78bfa;
    --accent3:#38bdf8; --green:#10b981; --red:#f87171; --orange:#fb923c;
    --text:#e2e8f0; --muted:#94a3b8; --radius:14px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;font-size:15px}
body{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;min-height:100vh;padding:24px 16px 48px}

/* ── Logo bar ── */
.logo-bar{width:100%;max-width:480px;display:flex;align-items:center;gap:10px;margin-bottom:24px}
.logo-bar img{height:36px;width:auto}
.logo-bar span{font-size:18px;font-weight:700;background:linear-gradient(90deg,var(--accent),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent}

/* ── Card ── */
.pay-card{width:100%;max-width:480px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;box-shadow:0 24px 60px rgba(0,0,0,.4)}

/* ── Status badge ── */
.status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:99px;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;margin-bottom:20px}
.status-badge.pending   {background:rgba(108,99,255,.15);color:var(--accent2);border:1px solid rgba(108,99,255,.3)}
.status-badge.confirming{background:rgba(56,189,248,.15);color:var(--accent3);border:1px solid rgba(56,189,248,.3)}
.status-badge.completed {background:rgba(16,185,129,.15);color:var(--green);border:1px solid rgba(16,185,129,.3)}
.status-badge.expired   {background:rgba(248,113,113,.15);color:var(--red);border:1px solid rgba(248,113,113,.3)}
.status-badge.underpaid {background:rgba(251,146,60,.15);color:var(--orange);border:1px solid rgba(251,146,60,.3)}
.status-dot{width:6px;height:6px;border-radius:50%;background:currentColor;animation:pulse 1.4s ease-in-out infinite}
.status-badge.completed .status-dot,
.status-badge.expired   .status-dot{animation:none}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ── Amount row ── */
.amount-row{display:flex;align-items:baseline;gap:8px;margin-bottom:4px}
.amount-val{font-size:28px;font-weight:700;color:#fff}
.coin-sym{font-size:16px;font-weight:600;color:var(--muted)}
.amount-label{font-size:12px;color:var(--muted);margin-bottom:20px}

/* ── Countdown ── */
.countdown-wrap{background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between}
.countdown-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.countdown-val{font-size:17px;font-weight:700;color:var(--accent2);font-variant-numeric:tabular-nums}
.countdown-val.urgent{color:var(--red)}

/* ── QR block ── */
.qr-section{display:flex;flex-direction:column;align-items:center;gap:12px;margin-bottom:20px}
.qr-wrap{background:#fff;border-radius:10px;padding:12px;line-height:0;width:fit-content}
.qr-wrap svg{display:block}
.qr-hint{font-size:11px;color:var(--muted);text-align:center}

/* ── Address block ── */
.addr-block{background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:20px}
.addr-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px}
.addr-row{display:flex;align-items:center;gap:8px}
.addr-text{font-size:13px;font-family:'Courier New',monospace;color:var(--text);flex:1;word-break:break-all;line-height:1.5}
.copy-btn{flex-shrink:0;background:rgba(108,99,255,.2);border:1px solid rgba(108,99,255,.35);color:var(--accent2);font-size:11px;font-weight:600;padding:6px 12px;border-radius:8px;cursor:pointer;transition:all .15s;white-space:nowrap}
.copy-btn:hover{background:rgba(108,99,255,.35)}
.copy-btn.copied{background:rgba(16,185,129,.2);border-color:rgba(16,185,129,.35);color:var(--green)}

/* ── Info rows ── */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.info-cell{background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px}
.info-cell .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.info-cell .val{font-size:13px;font-weight:600;color:var(--text)}

/* ── Progress bar ── */
.progress-wrap{margin-bottom:20px}
.progress-label{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px}
.progress-track{height:6px;background:var(--bg3);border-radius:99px;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:99px;transition:width .4s ease}

/* ── Final states ── */
.final-card{text-align:center;padding:12px 0 4px}
.final-icon{font-size:48px;margin-bottom:12px;display:block}
.final-title{font-size:20px;font-weight:700;margin-bottom:8px}
.final-title.success{color:var(--green)}
.final-title.fail{color:var(--red)}
.final-msg{font-size:13px;color:var(--muted);line-height:1.6}

/* ── Footer ── */
.pay-footer{width:100%;max-width:480px;text-align:center;margin-top:16px;font-size:11px;color:var(--muted)}
.pay-footer a{color:var(--accent2);text-decoration:none}

/* ── Spin animation on polling ── */
@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;animation:spin 1s linear infinite}
</style>
</head>
<body>

<!-- Logo bar -->
<div class="logo-bar">
    <img src="{{ asset(show_image(settings('site_logo'), 'logo.png')) }}" alt="{{ config('app.name') }}">
    <span>{{ settings('coin_name') ?? config('app.name') }}</span>
</div>

<!-- Main card -->
<div class="pay-card" id="payCard">

    <!-- Status badge -->
    <div class="status-badge {{ $order->status }}" id="statusBadge">
        <span class="status-dot"></span>
        <span id="statusText">{{ ucfirst($order->status) }}</span>
    </div>

    @if(in_array($order->status, [\App\Model\PaymentOrder::STATUS_COMPLETED]))
    <!-- ── COMPLETED ── -->
    <div class="final-card">
        <span class="final-icon">✅</span>
        <p class="final-title success">Payment Received!</p>
        <p class="final-msg">Your payment of <strong>{{ $order->amount }} {{ $order->coin_type }}</strong> has been confirmed.<br>The merchant has been notified.</p>
    </div>

    @elseif($order->status === \App\Model\PaymentOrder::STATUS_EXPIRED)
    <!-- ── EXPIRED ── -->
    <div class="final-card">
        <span class="final-icon">⏰</span>
        <p class="final-title fail">Invoice Expired</p>
        <p class="final-msg">This payment request has expired. Please return to the merchant and create a new order.</p>
    </div>

    @elseif($order->status === \App\Model\PaymentOrder::STATUS_UNDERPAID)
    <!-- ── UNDERPAID ── -->
    <div class="final-card">
        <span class="final-icon">⚠️</span>
        <p class="final-title fail">Underpaid</p>
        <p class="final-msg">We received <strong>{{ $order->amount_received }} {{ $order->coin_type }}</strong> but expected <strong>{{ $order->amount }}</strong>. Please contact the merchant.</p>
    </div>

    @else
    <!-- ── PENDING / CONFIRMING ── -->

    <!-- Amount -->
    <div class="amount-row">
        @if($coinIcon)
        <img src="{{ $coinIcon }}" alt="{{ $order->coin_type }}" style="width:24px;height:24px;border-radius:50%;object-fit:cover">
        @endif
        <span class="amount-val" id="amountVal">{{ rtrim(rtrim($order->amount, '0'), '.') }}</span>
        <span class="coin-sym">{{ $order->coin_type }}</span>
    </div>
    <p class="amount-label">Send exactly this amount to the address below</p>

    <!-- Countdown -->
    @if($order->expires_at)
    <div class="countdown-wrap" id="countdownWrap">
        <span class="countdown-label">⏱ Expires in</span>
        <span class="countdown-val" id="countdownVal">--:--</span>
    </div>
    @endif

    <!-- QR code -->
    <div class="qr-section">
        <div class="qr-wrap">{!! $qrCode !!}</div>
        <p class="qr-hint">Scan with your wallet app</p>
    </div>

    <!-- Address -->
    <div class="addr-block">
        <p class="addr-label">Pay to address</p>
        <div class="addr-row">
            <span class="addr-text" id="payAddress">{{ $order->pay_address }}</span>
            <button class="copy-btn" id="copyBtn" onclick="copyAddress()">Copy</button>
        </div>
    </div>

    <!-- Info grid -->
    <div class="info-grid">
        <div class="info-cell">
            <div class="lbl">Coin</div>
            <div class="val">{{ $order->coin_type }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Network</div>
            <div class="val">{{ $order->coin?->name ?? $order->coin_type }}</div>
        </div>
        @if($order->merchant_order_id)
        <div class="info-cell">
            <div class="lbl">Order ref</div>
            <div class="val" style="font-size:12px;word-break:break-all">{{ $order->merchant_order_id }}</div>
        </div>
        @endif
        <div class="info-cell">
            <div class="lbl">Order ID</div>
            <div class="val" style="font-size:11px;font-family:monospace">{{ substr($order->uuid, 0, 8) }}…</div>
        </div>
    </div>

    <!-- Progress bar (amount received) -->
    @if((float)$order->amount_received > 0)
    <div class="progress-wrap" id="progressWrap">
        <div class="progress-label">
            <span>Received</span>
            <span id="progressPct">{{ min(100, round((float)$order->amount_received / (float)$order->amount * 100, 1)) }}%</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill" style="width:{{ min(100, round((float)$order->amount_received / (float)$order->amount * 100)) }}%"></div>
        </div>
    </div>
    @else
    <div class="progress-wrap" id="progressWrap" style="display:none">
        <div class="progress-label">
            <span>Received</span>
            <span id="progressPct">0%</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
    </div>
    @endif

    @endif
</div>

<!-- Footer -->
<div class="pay-footer">
    Secured by <a href="{{ url('/') }}">{{ settings('coin_name') ?? config('app.name') }}</a> payment gateway
</div>

<script>
(function () {
    'use strict';

    const ORDER_UUID    = '{{ $order->uuid }}';
    const EXPIRES_AT_MS = {{ $order->expires_at ? $order->expires_at->getTimestamp() * 1000 : 'null' }};
    const STATUS_URL    = '/api/payment/orders/' + ORDER_UUID + '/status';
    const CHECK_URL     = '/api/payment/orders/' + ORDER_UUID + '/check';
    const POLL_INTERVAL = 12000; // 12 s between polls
    const FINAL_STATES  = ['completed', 'expired', 'underpaid'];

    let currentStatus   = '{{ $order->status }}';
    let pollTimer       = null;
    let countdownTimer  = null;

    // ── Countdown ─────────────────────────────────────────────────────────────
    if (EXPIRES_AT_MS) {
        countdownTimer = setInterval(tickCountdown, 1000);
        tickCountdown();
    }

    function tickCountdown () {
        const el = document.getElementById('countdownVal');
        if (!el) return;
        const ms  = EXPIRES_AT_MS - Date.now();
        if (ms <= 0) {
            el.textContent = '00:00';
            el.classList.add('urgent');
            clearInterval(countdownTimer);
            return;
        }
        const s   = Math.floor(ms / 1000);
        const m   = Math.floor(s / 60);
        const sec = s % 60;
        el.textContent = String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
        if (s <= 120) el.classList.add('urgent');
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    if (!FINAL_STATES.includes(currentStatus)) {
        startPolling();
    }

    function startPolling () {
        // Trigger a deposit check immediately, then poll status
        triggerCheck();
        pollTimer = setInterval(triggerCheck, POLL_INTERVAL);
    }

    async function triggerCheck () {
        try {
            // Ask server to scan for incoming deposit
            await fetch(CHECK_URL, { method: 'POST' });
            // Then fetch the lightweight status
            const res  = await fetch(STATUS_URL);
            const body = await res.json();
            if (body.success) updateUI(body.data);
        } catch (e) {
            // Network error — silently retry next cycle
        }
    }

    function updateUI (d) {
        const prev = currentStatus;
        currentStatus = d.status;

        // Update badge
        const badge = document.getElementById('statusBadge');
        const txt   = document.getElementById('statusText');
        if (badge) { badge.className = 'status-badge ' + d.status; }
        if (txt)   { txt.textContent = cap(d.status); }

        // Update progress bar
        const received = parseFloat(d.amount_received) || 0;
        const expected = parseFloat(d.amount) || 1;
        const pct = Math.min(100, Math.round(received / expected * 100));
        const wrap = document.getElementById('progressWrap');
        const fill = document.getElementById('progressFill');
        const pctEl = document.getElementById('progressPct');
        if (received > 0 && wrap) {
            wrap.style.display = '';
            if (fill) fill.style.width = pct + '%';
            if (pctEl) pctEl.textContent = pct + '%';
        }

        // Reload page on final state for full re-render
        if (FINAL_STATES.includes(d.status) && d.status !== prev) {
            clearInterval(pollTimer);
            clearInterval(countdownTimer);
            setTimeout(() => window.location.reload(), 800);
        }
    }

    // ── Copy address ──────────────────────────────────────────────────────────
    window.copyAddress = function () {
        const addr = document.getElementById('payAddress')?.textContent?.trim();
        if (!addr) return;
        navigator.clipboard.writeText(addr).then(() => {
            const btn = document.getElementById('copyBtn');
            if (!btn) return;
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
        }).catch(() => {
            // Fallback for older browsers
            try {
                const ta = document.createElement('textarea');
                ta.value = addr;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            } catch {}
        });
    };

    function cap (s) { return s.charAt(0).toUpperCase() + s.slice(1); }
})();
</script>
</body>
</html>
