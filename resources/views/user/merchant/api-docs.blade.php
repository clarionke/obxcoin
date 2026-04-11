@extends('user.master',['menu'=>'merchant', 'sub_menu'=>'merchant_docs'])
@section('title', isset($title) ? $title : 'API Documentation')

@section('style')
<style>
/* ── OBXCoin API Docs ──────────────────────────────────────── */
.docs-wrap{max-width:860px;}
.docs-hdr{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;}
.docs-hdr-left h4{font-size:18px;font-weight:700;color:var(--text);margin:0 0 3px;}
.docs-hdr-left p{font-size:12.5px;color:var(--muted);margin:0;}

/* Section */
.doc-section{margin-bottom:36px;}
.doc-section-title{
    font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.9px;
    color:var(--accent);margin:0 0 14px;display:flex;align-items:center;gap:8px;
}
.doc-section-title::after{content:'';flex:1;height:1px;background:var(--border);}

/* Endpoint card */
.ep-card{
    background:var(--dark2);border:1px solid var(--border);
    border-radius:var(--r);overflow:hidden;margin-bottom:20px;
}
.ep-head{
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    padding:12px 18px;background:var(--dark3);
    border-bottom:1px solid var(--border);
}
.ep-path{font-family:'Courier New',monospace;font-size:13px;font-weight:600;color:var(--text);}
.ep-body{padding:16px 18px;}
.ep-body>p{font-size:13px;color:var(--text-2);margin:0 0 12px;line-height:1.65;}

/* Method pills */
.mtd{display:inline-flex;align-items:center;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.3px;flex-shrink:0;}
.mtd-get   {background:rgba(63,185,80,.15);   color:#3fb950;}
.mtd-post  {background:rgba(99,102,241,.15);  color:#a5b4fc;}

/* Auth badge */
.auth-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;flex-shrink:0;}
.auth-hmac  {background:rgba(99,102,241,.1); color:#a5b4fc;border:1px solid rgba(99,102,241,.25);}
.auth-public{background:rgba(63,185,80,.08); color:#3fb950;border:1px solid rgba(63,185,80,.2);}

/* Field table */
.ftable{width:100%;border-collapse:collapse;margin:0 0 14px;font-size:12.5px;}
.ftable th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);padding:7px 12px;background:var(--dark3);border-bottom:1px solid var(--border);}
.ftable td{padding:8px 12px;color:var(--text-2);border-bottom:1px solid var(--border-light);vertical-align:top;}
.ftable td:first-child{font-family:'Courier New',monospace;color:#f9e2af;font-size:12px;}
.req{display:inline-block;padding:1px 6px;border-radius:3px;font-size:9.5px;font-weight:700;background:rgba(248,81,73,.1);color:var(--danger);border:1px solid rgba(248,81,73,.2);}
.opt{display:inline-block;padding:1px 6px;border-radius:3px;font-size:9.5px;font-weight:700;background:rgba(99,102,241,.08);color:#a5b4fc;border:1px solid rgba(99,102,241,.2);}

/* Note */
.note{display:flex;gap:9px;align-items:flex-start;border-radius:var(--r-sm);padding:10px 14px;margin:10px 0;font-size:12.5px;}
.note-i{background:rgba(99,102,241,.07);border:1px solid rgba(99,102,241,.18);}
.note-w{background:rgba(210,153,34,.07);border:1px solid rgba(210,153,34,.18);}
.note-i i{color:#a5b4fc;flex-shrink:0;margin-top:1px;}
.note-w i{color:var(--warning);flex-shrink:0;margin-top:1px;}
.note p{margin:0;color:var(--text-2);line-height:1.6;}

/* ── Unified Language Tabs ── */
.lang-tabs-wrap{margin:12px 0 0;}
.lang-tabs{
    display:flex;gap:2px;flex-wrap:wrap;
    border-bottom:1px solid var(--border);
}
.lang-tab{
    padding:6px 13px;font-size:11.5px;font-weight:600;
    color:var(--muted);cursor:pointer;
    border:1px solid transparent;border-bottom:none;
    border-radius:6px 6px 0 0;transition:all .15s;
    user-select:none;background:transparent;
    display:flex;align-items:center;gap:6px;
}
.lang-tab:hover{color:var(--text-2);background:rgba(255,255,255,.03);}
.lang-tab.active{
    color:#a5b4fc;background:var(--dark3);
    border-color:var(--border);
    margin-bottom:-1px;padding-bottom:7px;
}
.lang-tab .lang-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.dot-json  {background:#f9e2af;}
.dot-php   {background:#8892bf;}
.dot-java  {background:#f89820;}
.dot-flutter{background:#54c5f8;}
.dot-python{background:#4b8bbe;}
.dot-js    {background:#f7df1e;}

.lang-pane{
    display:none;
    background:var(--dark3);border:1px solid var(--border);border-top:none;
    border-radius:0 0 var(--r-sm) var(--r-sm);
    position:relative;
}
.lang-pane.active{display:block;}
.lang-pane-hdr{
    display:flex;align-items:center;justify-content:flex-end;
    padding:4px 10px;border-bottom:1px solid var(--border-light);
}
.cp-btn{
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 9px;background:var(--dark4);
    border:1px solid var(--border);border-radius:4px;
    font-size:11px;color:var(--muted);cursor:pointer;transition:all .15s;
}
.cp-btn:hover{color:var(--text);}
pre.code{
    margin:0;padding:14px 16px;
    font-family:'Courier New',monospace;font-size:12.5px;
    color:#c9d1d9;overflow-x:auto;line-height:1.7;
    white-space:pre;
}

/* Syntax colours */
.k  {color:#ff7b72;}
.s  {color:#a5d6ff;}
.v  {color:#79c0ff;}
.c  {color:#8b949e;}
.n  {color:#f9e2af;}
.num{color:#a8daff;}

/* Status table */
.s-pending   {color:var(--muted)!important;}
.s-confirming{color:var(--warning)!important;}
.s-completed {color:var(--success)!important;}
.s-expired   {color:var(--danger)!important;}
.s-underpaid {color:#fb923c!important;}

/* ── Quick-Start Steps ── */
.qs-steps{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0;}
.qs-step{display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border);}
.qs-step:last-child{border-bottom:none;}
.qs-num{
    width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);
    display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;
    color:#fff;flex-shrink:0;margin-top:1px;
}
.qs-body{flex:1;}
.qs-body h6{font-size:13px;font-weight:700;color:var(--text);margin:0 0 5px;}
.qs-body p{font-size:12.5px;color:var(--text-2);margin:0 0 8px;line-height:1.6;}
.qs-body p:last-child{margin-bottom:0;}
.qs-tag{display:inline-block;padding:1px 7px;border-radius:3px;font-size:11px;font-weight:600;
        background:rgba(99,102,241,.1);color:#a5b4fc;border:1px solid rgba(99,102,241,.2);margin-right:4px;}

/* ── Docs with sidebar layout ── */
.docs-layout{display:flex;gap:28px;align-items:flex-start;}
.docs-sidebar{
    width:190px;flex-shrink:0;position:sticky;top:80px;
    background:var(--dark2);border:1px solid var(--border);border-radius:var(--r);
    padding:14px 0;
}
.docs-sidebar-hdr{
    font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;
    color:var(--muted);padding:0 14px 8px;margin-bottom:4px;
    border-bottom:1px solid var(--border);
}
.docs-nav-item{
    display:flex;align-items:center;gap:7px;padding:6px 14px;font-size:12px;
    color:var(--muted);cursor:pointer;transition:all .15s;text-decoration:none;
    border-left:2px solid transparent;
}
.docs-nav-item:hover{color:var(--text-2);background:rgba(255,255,255,.03);}
.docs-nav-item.active{color:#a5b4fc;border-left-color:#6366f1;background:rgba(99,102,241,.06);}
.docs-nav-item i{width:13px;text-align:center;font-size:11px;}
.docs-nav-sep{height:1px;background:var(--border);margin:7px 0;}
.docs-main{flex:1;min-width:0;}
@media(max-width:768px){.docs-layout{flex-direction:column;}.docs-sidebar{width:100%;position:static;}}
</style>
@endsection

@section('content')
<div class="docs-wrap">

    {{-- Header --}}
    <div class="docs-hdr">
        <div class="docs-hdr-left">
            <h4><i class="fa fa-plug" style="color:#a5b4fc;margin-right:8px;"></i>OBXCoin Payment API</h4>
            <p>Integrate OBXCoin payments into any platform. Base URL: <code style="color:#a5b4fc;font-size:12px;">{{ url('/') }}</code></p>
        </div>
        <a href="{{ route('merchant.keys') }}" style="background:var(--dark3);border:1px solid var(--border);color:var(--text-2);border-radius:var(--r-sm);font-size:12.5px;padding:7px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-key"></i> My API Keys
        </a>
    </div>

    <div class="docs-layout">

    {{-- ── Sidebar Navigation ── --}}
    <nav class="docs-sidebar" id="docs-sidebar">
        <div class="docs-sidebar-hdr">On this page</div>
        <a class="docs-nav-item active" href="#section-quickstart" onclick="navTo(this,'section-quickstart')">
            <i class="fa fa-rocket"></i> Quick Start
        </a>
        <div class="docs-nav-sep"></div>
        <a class="docs-nav-item" href="#section-auth" onclick="navTo(this,'section-auth')">
            <i class="fa fa-lock"></i> Authentication
        </a>
        <div class="docs-nav-sep"></div>
        <div style="padding:5px 14px 3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Endpoints</div>
        <a class="docs-nav-item" href="#ep-coins" onclick="navTo(this,'ep-coins')">
            <i class="fa fa-circle-o" style="font-size:9px;"></i> List Coins
        </a>
        <a class="docs-nav-item" href="#ep-create" onclick="navTo(this,'ep-create')">
            <i class="fa fa-circle-o" style="font-size:9px;"></i> Create Order
        </a>
        <a class="docs-nav-item" href="#ep-get" onclick="navTo(this,'ep-get')">
            <i class="fa fa-circle-o" style="font-size:9px;"></i> Get Order
        </a>
        <a class="docs-nav-item" href="#ep-status" onclick="navTo(this,'ep-status')">
            <i class="fa fa-circle-o" style="font-size:9px;"></i> Poll Status
        </a>
        <a class="docs-nav-item" href="#ep-check" onclick="navTo(this,'ep-check')">
            <i class="fa fa-circle-o" style="font-size:9px;"></i> Check Deposit
        </a>
        <div class="docs-nav-sep"></div>
        <a class="docs-nav-item" href="#section-webhooks" onclick="navTo(this,'section-webhooks')">
            <i class="fa fa-bolt"></i> Webhooks
        </a>
        <a class="docs-nav-item" href="#section-reference" onclick="navTo(this,'section-reference')">
            <i class="fa fa-table"></i> Reference
        </a>
    </nav>

    {{-- ── Main Content ── --}}
    <div class="docs-main">

    {{-- ── 0. Quick Start ── --}}
    <div class="doc-section" id="section-quickstart">
        <div class="doc-section-title"><i class="fa fa-rocket"></i> Quick Start</div>
        <p style="font-size:13px;color:var(--text-2);margin-bottom:16px;">
            Follow these steps to accept your first OBXCoin payment in under 10 minutes.
        </p>
        <ol class="qs-steps">
            <li class="qs-step">
                <div class="qs-num">1</div>
                <div class="qs-body">
                    <h6>Create an API Key</h6>
                    <p>Go to <a href="{{ route('merchant.keys') }}" style="color:#a5b4fc;">API Keys</a> and click <strong>Generate Key</strong>. Copy the <strong>plain secret</strong> shown once — it cannot be recovered later. Store it securely (env var or secret manager).</p>
                    <p><span class="qs-tag">api_key</span> is your public identifier. <span class="qs-tag">plain_secret</span> is used only for signing — never sent over the wire.</p>
                </div>
            </li>
            <li class="qs-step">
                <div class="qs-num">2</div>
                <div class="qs-body">
                    <h6>Fetch Available Coins</h6>
                    <p>Call <code>GET /api/payment/coins</code> (no auth) to get the list. For OBXCoin payments use <code>"coin_type": "OBXCoin"</code>.</p>
                    <div class="lang-tabs-wrap" id="qs-coins-tabs">
                        <div class="lang-tabs">
                            <div class="lang-tab active" onclick="switchTab('qs-coins','json')"><span class="lang-dot dot-json"></span>JSON Response</div>
                        </div>
                        <div class="lang-pane active" data-group="qs-coins" data-lang="json">
                            <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                            <pre class="code">{
  <span class="n">"data"</span>: [
    { <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"name"</span>: <span class="s">"OBXCoin"</span>, <span class="n">"symbol"</span>: <span class="s">"OBX"</span>, <span class="n">"type"</span>: <span class="s">"OBXCoin"</span> }
  ]
}</pre>
                        </div>
                    </div>
                </div>
            </li>
            <li class="qs-step">
                <div class="qs-num">3</div>
                <div class="qs-body">
                    <h6>Create a Payment Order</h6>
                    <p>POST to <code>/api/payment/orders</code> with a signed request (see <a href="#section-auth" onclick="navTo(document.querySelector('[href=\'#section-auth\']'),'section-auth')" style="color:#a5b4fc;">Authentication</a>). You get back a <code>checkout_url</code> — redirect your customer there.</p>
                    <div class="lang-tabs-wrap" id="qs-create-tabs">
                        <div class="lang-tabs">
                            <div class="lang-tab active" onclick="switchTab('qs-create','req')"><span class="lang-dot dot-json"></span>Request JSON</div>
                            <div class="lang-tab" onclick="switchTab('qs-create','resp')"><span class="lang-dot" style="background:#3fb950;"></span>Response 201</div>
                        </div>
                        <div class="lang-pane active" data-group="qs-create" data-lang="req">
                            <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                            <pre class="code"><span class="c">// POST {{ url('/api/payment/orders') }}</span>
<span class="c">// Headers: X-Api-Key, X-Api-Timestamp, X-Api-Signature, Content-Type: application/json</span>
{
  <span class="n">"merchant_order_id"</span>: <span class="s">"ORDER-1001"</span>,
  <span class="n">"coin_type"</span>:         <span class="s">"OBXCoin"</span>,
  <span class="n">"amount"</span>:            <span class="s">"50.00"</span>,
  <span class="n">"callback_url"</span>:      <span class="s">"https://your-store.com/thanks"</span>,
  <span class="n">"metadata"</span>:          { <span class="n">"product"</span>: <span class="s">"Premium Plan"</span>, <span class="n">"user_id"</span>: <span class="num">42</span> },
  <span class="n">"expires_minutes"</span>:   <span class="num">30</span>
}</pre>
                        </div>
                        <div class="lang-pane" data-group="qs-create" data-lang="resp">
                            <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                            <pre class="code"><span class="c">// HTTP 201 Created</span>
{
  <span class="n">"data"</span>: {
    <span class="n">"uuid"</span>:            <span class="s">"550e8400-e29b-41d4-a716-446655440000"</span>,
    <span class="n">"coin_type"</span>:       <span class="s">"OBXCoin"</span>,
    <span class="n">"amount"</span>:          <span class="s">"50.00000000"</span>,
    <span class="n">"amount_received"</span>: <span class="s">"0.00000000"</span>,
    <span class="n">"pay_address"</span>:     <span class="s">"obx1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh"</span>,
    <span class="n">"status"</span>:          <span class="s">"pending"</span>,
    <span class="n">"expires_at"</span>:      <span class="s">"2026-04-11T12:30:00Z"</span>,
    <span class="n">"checkout_url"</span>:    <span class="s">"{{ url('/pay/550e8400-e29b-41d4-a716-446655440000') }}"</span>
  }
}</pre>
                        </div>
                    </div>
                </div>
            </li>
            <li class="qs-step">
                <div class="qs-num">4</div>
                <div class="qs-body">
                    <h6>Customer Pays via Checkout</h6>
                    <p>Redirect to <code>checkout_url</code>. The hosted page shows the wallet address and a QR code, and polls the status every 12 s automatically. No extra work needed on your side.</p>
                </div>
            </li>
            <li class="qs-step">
                <div class="qs-num">5</div>
                <div class="qs-body">
                    <h6>Receive a Webhook (or Poll Yourself)</h6>
                    <p>When the payment confirms, OBXCoin POSTs a signed event to your <code>webhook_url</code> with <code>"status": "completed"</code>. Verify the <code>X-OBX-Signature</code> header, then fulfil the order.</p>
                    <div class="lang-tabs-wrap" id="qs-wh-tabs">
                        <div class="lang-tabs">
                            <div class="lang-tab active" onclick="switchTab('qs-wh','json')"><span class="lang-dot dot-json"></span>Webhook JSON</div>
                        </div>
                        <div class="lang-pane active" data-group="qs-wh" data-lang="json">
                            <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                            <pre class="code"><span class="c">// POST to your webhook_url  |  X-OBX-Signature: &lt;hmac-sha256 of raw body&gt;</span>
{
  <span class="n">"event"</span>:             <span class="s">"payment.completed"</span>,
  <span class="n">"uuid"</span>:              <span class="s">"550e8400-e29b-41d4-a716-446655440000"</span>,
  <span class="n">"merchant_order_id"</span>: <span class="s">"ORDER-1001"</span>,
  <span class="n">"coin_type"</span>:         <span class="s">"OBXCoin"</span>,
  <span class="n">"amount"</span>:            <span class="s">"50.00000000"</span>,
  <span class="n">"amount_received"</span>:   <span class="s">"50.00000000"</span>,
  <span class="n">"status"</span>:            <span class="s">"completed"</span>,
  <span class="n">"confirmed_at"</span>:      <span class="s">"2026-04-11T12:18:44Z"</span>,
  <span class="n">"metadata"</span>:          { <span class="n">"product"</span>: <span class="s">"Premium Plan"</span>, <span class="n">"user_id"</span>: <span class="num">42</span> }
}</pre>
                        </div>
                    </div>
                    <p style="margin-top:8px;">Alternatively poll <code>GET /api/payment/orders/{uuid}/status</code> (no auth needed) until <code>status</code> is <code>completed</code>.</p>
                </div>
            </li>
        </ol>
    </div>

    {{-- ── 1. Authentication ── --}}
    <div class="doc-section" id="section-auth">
        <div class="doc-section-title"><i class="fa fa-lock"></i> Authentication</div>

        <p style="font-size:13px;color:var(--text-2);margin-bottom:14px;">
            Every signed request needs three HTTP headers. The signature is <strong>HMAC-SHA256</strong> computed from your API key, timestamp, and a hash of the request body.
        </p>

        <table class="ftable">
            <thead><tr><th>Header</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>X-Api-Key</td><td>Your API key — starts with <code>obx_</code></td></tr>
                <tr><td>X-Api-Timestamp</td><td>Unix timestamp (seconds) — must be within ±5 min of server time</td></tr>
                <tr><td>X-Api-Signature</td><td>HMAC-SHA256 signature (see examples)</td></tr>
            </tbody>
        </table>

        <div class="note note-w" style="margin-bottom:14px;">
            <i class="fa fa-info-circle"></i>
            <p>
                <strong>Signing formula:</strong><br>
                <code>input = api_key + "." + timestamp + "." + sha256(raw_body)</code><br>
                <code>key   = sha256(plain_secret)  // as raw bytes</code><br>
                <code>sig   = HMAC-SHA256(input, key)  // hex-encoded lowercase</code><br>
                For <strong>GET</strong> requests use <code>sha256("")</code> as the body hash.
            </p>
        </div>

        <div class="lang-tabs-wrap" id="sign-tabs">
            <div class="lang-tabs">
                <div class="lang-tab active" onclick="switchTab('sign','php')"><span class="lang-dot dot-php"></span>PHP</div>
                <div class="lang-tab" onclick="switchTab('sign','js')"><span class="lang-dot dot-js"></span>JavaScript</div>
                <div class="lang-tab" onclick="switchTab('sign','python')"><span class="lang-dot dot-python"></span>Python</div>
                <div class="lang-tab" onclick="switchTab('sign','java')"><span class="lang-dot dot-java"></span>Java</div>
                <div class="lang-tab" onclick="switchTab('sign','flutter')"><span class="lang-dot dot-flutter"></span>Flutter</div>
            </div>
            <div class="lang-pane active" data-group="sign" data-lang="php">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="k">function</span> <span class="v">obx_headers</span>(string <span class="v">$key</span>, string <span class="v">$secret</span>, string <span class="v">$body</span> = <span class="s">''</span>): array {
    <span class="v">$ts</span>  = <span class="k">time</span>();
    <span class="v">$sig</span> = <span class="k">hash_hmac</span>(<span class="s">'sha256'</span>,
        <span class="v">$key</span>.<span class="s">'.'</span>.<span class="v">$ts</span>.<span class="s">'.'</span>.<span class="k">hash</span>(<span class="s">'sha256'</span>, <span class="v">$body</span>),
        <span class="k">hex2bin</span>(<span class="k">hash</span>(<span class="s">'sha256'</span>, <span class="v">$secret</span>))
    );
    <span class="k">return</span> [
        <span class="s">'X-Api-Key: '</span>.<span class="v">$key</span>,
        <span class="s">'X-Api-Timestamp: '</span>.<span class="v">$ts</span>,
        <span class="s">'X-Api-Signature: '</span>.<span class="v">$sig</span>,
        <span class="s">'Content-Type: application/json'</span>,
    ];
}</pre>
            </div>
            <div class="lang-pane" data-group="sign" data-lang="js">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="k">import</span> crypto <span class="k">from</span> <span class="s">'crypto'</span>;

<span class="k">function</span> <span class="v">obxHeaders</span>(apiKey, secret, rawBody = <span class="s">''</span>) {
    <span class="k">const</span> ts       = String(Math.floor(Date.now() / <span class="num">1000</span>));
    <span class="k">const</span> bodyHash = crypto.createHash(<span class="s">'sha256'</span>).update(rawBody).digest(<span class="s">'hex'</span>);
    <span class="k">const</span> keyBytes = Buffer.from(
        crypto.createHash(<span class="s">'sha256'</span>).update(secret).digest(<span class="s">'hex'</span>), <span class="s">'hex'</span>);
    <span class="k">const</span> sig = crypto.createHmac(<span class="s">'sha256'</span>, keyBytes)
        .update(`${apiKey}.${ts}.${bodyHash}`).digest(<span class="s">'hex'</span>);
    <span class="k">return</span> {
        <span class="s">'X-Api-Key'</span>: apiKey, <span class="s">'X-Api-Timestamp'</span>: ts,
        <span class="s">'X-Api-Signature'</span>: sig, <span class="s">'Content-Type'</span>: <span class="s">'application/json'</span>,
    };
}</pre>
            </div>
            <div class="lang-pane" data-group="sign" data-lang="python">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="k">import</span> hashlib, hmac, time

<span class="k">def</span> <span class="v">obx_headers</span>(api_key: str, secret: str, body: bytes = b<span class="s">''</span>) -> dict:
    ts      = str(int(time.time()))
    b_hash  = hashlib.sha256(body).hexdigest()
    key_b   = bytes.fromhex(hashlib.sha256(secret.encode()).hexdigest())
    sig     = hmac.new(key_b, f<span class="s">"{api_key}.{ts}.{b_hash}"</span>.encode(), hashlib.sha256).hexdigest()
    <span class="k">return</span> {<span class="s">'X-Api-Key'</span>: api_key, <span class="s">'X-Api-Timestamp'</span>: ts,
            <span class="s">'X-Api-Signature'</span>: sig, <span class="s">'Content-Type'</span>: <span class="s">'application/json'</span>}</pre>
            </div>
            <div class="lang-pane" data-group="sign" data-lang="java">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="k">import</span> javax.crypto.Mac;
<span class="k">import</span> javax.crypto.spec.SecretKeySpec;
<span class="k">import</span> java.security.MessageDigest;
<span class="k">import</span> java.util.HexFormat;

<span class="k">static</span> Map&lt;String, String&gt; <span class="v">obxHeaders</span>(String apiKey, String secret, String body) <span class="k">throws</span> Exception {
    <span class="k">long</span>   ts    = System.currentTimeMillis() / <span class="num">1000</span>;
    String bHash = sha256Hex(body.getBytes());
    <span class="k">byte</span>[] keyB  = HexFormat.of().parseHex(sha256Hex(secret.getBytes()));
    Mac mac = Mac.getInstance(<span class="s">"HmacSHA256"</span>);
    mac.init(<span class="k">new</span> SecretKeySpec(keyB, <span class="s">"HmacSHA256"</span>));
    String sig = HexFormat.of().formatHex(mac.doFinal((apiKey+<span class="s">"."</span>+ts+<span class="s">"."</span>+bHash).getBytes()));
    <span class="k">return</span> Map.of(<span class="s">"X-Api-Key"</span>, apiKey, <span class="s">"X-Api-Timestamp"</span>, String.valueOf(ts),
                  <span class="s">"X-Api-Signature"</span>, sig, <span class="s">"Content-Type"</span>, <span class="s">"application/json"</span>);
}
<span class="k">static</span> String <span class="v">sha256Hex</span>(<span class="k">byte</span>[] d) <span class="k">throws</span> Exception {
    <span class="k">return</span> HexFormat.of().formatHex(MessageDigest.getInstance(<span class="s">"SHA-256"</span>).digest(d));
}</pre>
            </div>
            <div class="lang-pane" data-group="sign" data-lang="flutter">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="c">// pubspec: crypto: ^3.0.0</span>
<span class="k">import</span> <span class="s">'package:crypto/crypto.dart'</span>;
<span class="k">import</span> <span class="s">'dart:convert'</span>;

Map&lt;String, String&gt; <span class="v">obxHeaders</span>(String apiKey, String secret, [String body = <span class="s">''</span>]) {
  <span class="k">final</span> ts      = (DateTime.now().millisecondsSinceEpoch ~/ <span class="num">1000</span>).toString();
  <span class="k">final</span> bHash   = sha256.convert(utf8.encode(body)).toString();
  <span class="k">final</span> keyB    = sha256.convert(utf8.encode(secret)).bytes;
  <span class="k">final</span> sig     = Hmac(sha256, keyB).convert(utf8.encode(<span class="s">'$apiKey.$ts.$bHash'</span>)).toString();
  <span class="k">return</span> {<span class="s">'X-Api-Key'</span>: apiKey, <span class="s">'X-Api-Timestamp'</span>: ts,
           <span class="s">'X-Api-Signature'</span>: sig, <span class="s">'Content-Type'</span>: <span class="s">'application/json'</span>};
}</pre>
            </div>
        </div>
    </div>

    {{-- ── 2. Endpoints ── --}}
    <div class="doc-section" id="section-endpoints">
        <div class="doc-section-title"><i class="fa fa-code"></i> Endpoints</div>

        {{-- List Coins --}}
        <div class="ep-card" id="ep-coins">
            <div class="ep-head">
                <span class="mtd mtd-get">GET</span>
                <span class="ep-path">/api/payment/coins</span>
                <span class="auth-pill auth-public" style="margin-left:auto;"><i class="fa fa-globe"></i> Public</span>
            </div>
            <div class="ep-body">
                <p>Returns coins available for payment. <strong>OBXCoin</strong> is always the primary coin.</p>
                <div class="lang-tabs-wrap" id="coins-tabs">
                    <div class="lang-tabs">
                        <div class="lang-tab active" onclick="switchTab('coins','json')"><span class="lang-dot dot-json"></span>JSON</div>
                        <div class="lang-tab" onclick="switchTab('coins','php')"><span class="lang-dot dot-php"></span>PHP</div>
                        <div class="lang-tab" onclick="switchTab('coins','js')"><span class="lang-dot dot-js"></span>JavaScript</div>
                        <div class="lang-tab" onclick="switchTab('coins','python')"><span class="lang-dot dot-python"></span>Python</div>
                        <div class="lang-tab" onclick="switchTab('coins','java')"><span class="lang-dot dot-java"></span>Java</div>
                        <div class="lang-tab" onclick="switchTab('coins','flutter')"><span class="lang-dot dot-flutter"></span>Flutter</div>
                    </div>
                    <div class="lang-pane active" data-group="coins" data-lang="json">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="c">// Response 200</span>
{
  <span class="n">"data"</span>: [
    { <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"name"</span>: <span class="s">"OBXCoin"</span>, <span class="n">"symbol"</span>: <span class="s">"OBX"</span>, <span class="n">"type"</span>: <span class="s">"OBXCoin"</span> }
  ]
}</pre>
                    </div>
                    <div class="lang-pane" data-group="coins" data-lang="php">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="v">$ch</span> = <span class="k">curl_init</span>(<span class="s">'{{ url("/api/payment/coins") }}'</span>);
<span class="k">curl_setopt</span>(<span class="v">$ch</span>, CURLOPT_RETURNTRANSFER, <span class="k">true</span>);
<span class="v">$coins</span> = <span class="k">json_decode</span>(<span class="k">curl_exec</span>(<span class="v">$ch</span>), <span class="k">true</span>)[<span class="s">'data'</span>];</pre>
                    </div>
                    <div class="lang-pane" data-group="coins" data-lang="js">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">const</span> { data: coins } = <span class="k">await</span> fetch(<span class="s">'{{ url("/api/payment/coins") }}'</span>).then(r => r.json());</pre>
                    </div>
                    <div class="lang-pane" data-group="coins" data-lang="python">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">import</span> requests
coins = requests.get(<span class="s">'{{ url("/api/payment/coins") }}'</span>).json()[<span class="s">'data'</span>]</pre>
                    </div>
                    <div class="lang-pane" data-group="coins" data-lang="java">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">var</span> req  = HttpRequest.newBuilder().uri(URI.create(<span class="s">"{{ url("/api/payment/coins") }}"</span>)).GET().build();
<span class="k">var</span> resp = client.send(req, HttpResponse.BodyHandlers.ofString());</pre>
                    </div>
                    <div class="lang-pane" data-group="coins" data-lang="flutter">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">final</span> res   = <span class="k">await</span> http.get(Uri.parse(<span class="s">'{{ url("/api/payment/coins") }}'</span>));
<span class="k">final</span> coins = jsonDecode(res.body)[<span class="s">'data'</span>];</pre>
                    </div>
                </div>
            </div>
        </div>

        {{-- Create Order --}}
        <div class="ep-card" id="ep-create">
            <div class="ep-head">
                <span class="mtd mtd-post">POST</span>
                <span class="ep-path">/api/payment/orders</span>
                <span class="auth-pill auth-hmac" style="margin-left:auto;"><i class="fa fa-lock"></i> HMAC</span>
            </div>
            <div class="ep-body">
                <p>Create a payment invoice. Redirect your customer to the returned <code>checkout_url</code>.</p>
                <table class="ftable">
                    <thead><tr><th>Field</th><th>Type</th><th></th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>merchant_order_id</td><td>string</td><td><span class="req">required</span></td><td>Your internal order reference (max 100 chars)</td></tr>
                        <tr><td>coin_type</td><td>string</td><td><span class="req">required</span></td><td>Use <strong>"OBXCoin"</strong> for the primary coin</td></tr>
                        <tr><td>amount</td><td>decimal</td><td><span class="req">required</span></td><td>Amount to collect</td></tr>
                        <tr><td>callback_url</td><td>url</td><td><span class="opt">optional</span></td><td>Redirect after payment completes</td></tr>
                        <tr><td>metadata</td><td>object</td><td><span class="opt">optional</span></td><td>Custom key-value data (max 2 KB)</td></tr>
                        <tr><td>expires_minutes</td><td>integer</td><td><span class="opt">optional</span></td><td>Invoice TTL in minutes (default 60, max 1440)</td></tr>
                    </tbody>
                </table>
                <div class="lang-tabs-wrap" id="create-tabs">
                    <div class="lang-tabs">
                        <div class="lang-tab active" onclick="switchTab('create','json')"><span class="lang-dot dot-json"></span>JSON</div>
                        <div class="lang-tab" onclick="switchTab('create','php')"><span class="lang-dot dot-php"></span>PHP</div>
                        <div class="lang-tab" onclick="switchTab('create','js')"><span class="lang-dot dot-js"></span>JavaScript</div>
                        <div class="lang-tab" onclick="switchTab('create','python')"><span class="lang-dot dot-python"></span>Python</div>
                        <div class="lang-tab" onclick="switchTab('create','java')"><span class="lang-dot dot-java"></span>Java</div>
                        <div class="lang-tab" onclick="switchTab('create','flutter')"><span class="lang-dot dot-flutter"></span>Flutter</div>
                    </div>
                    <div class="lang-pane active" data-group="create" data-lang="json">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="c">// Request body</span>
{
  <span class="n">"merchant_order_id"</span>: <span class="s">"ORDER-1001"</span>,
  <span class="n">"coin_type"</span>:         <span class="s">"OBXCoin"</span>,
  <span class="n">"amount"</span>:            <span class="s">"50.00"</span>,
  <span class="n">"callback_url"</span>:      <span class="s">"https://your-store.com/thanks"</span>,
  <span class="n">"metadata"</span>:          { <span class="n">"product"</span>: <span class="s">"Premium Plan"</span> },
  <span class="n">"expires_minutes"</span>:   <span class="num">30</span>
}

<span class="c">// Response 201</span>
{
  <span class="n">"data"</span>: {
    <span class="n">"uuid"</span>:            <span class="s">"550e8400-e29b-41d4-a716-446655440000"</span>,
    <span class="n">"coin_type"</span>:       <span class="s">"OBXCoin"</span>,
    <span class="n">"amount"</span>:          <span class="s">"50.00000000"</span>,
    <span class="n">"amount_received"</span>: <span class="s">"0.00000000"</span>,
    <span class="n">"pay_address"</span>:     <span class="s">"obx1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh"</span>,
    <span class="n">"status"</span>:          <span class="s">"pending"</span>,
    <span class="n">"expires_at"</span>:      <span class="s">"2026-04-11T12:30:00Z"</span>,
    <span class="n">"checkout_url"</span>:    <span class="s">"{{ url('/pay/550e8400-e29b-41d4-a716-446655440000') }}"</span>
  }
}</pre>
                    </div>
                    <div class="lang-pane" data-group="create" data-lang="php">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="v">$body</span> = <span class="k">json_encode</span>([
    <span class="s">'merchant_order_id'</span> => <span class="s">'ORDER-1001'</span>,
    <span class="s">'coin_type'</span>         => <span class="s">'OBXCoin'</span>,
    <span class="s">'amount'</span>            => <span class="s">'50.00'</span>,
    <span class="s">'callback_url'</span>      => <span class="s">'https://your-store.com/thanks'</span>,
]);
<span class="v">$ch</span> = <span class="k">curl_init</span>(<span class="s">'{{ url("/api/payment/orders") }}'</span>);
<span class="k">curl_setopt_array</span>(<span class="v">$ch</span>, [
    CURLOPT_POST           => <span class="k">true</span>,
    CURLOPT_POSTFIELDS     => <span class="v">$body</span>,
    CURLOPT_HTTPHEADER     => obx_headers(<span class="v">$apiKey</span>, <span class="v">$secret</span>, <span class="v">$body</span>),
    CURLOPT_RETURNTRANSFER => <span class="k">true</span>,
]);
<span class="v">$order</span> = <span class="k">json_decode</span>(<span class="k">curl_exec</span>(<span class="v">$ch</span>), <span class="k">true</span>)[<span class="s">'data'</span>];
<span class="k">header</span>(<span class="s">'Location: '</span> . <span class="v">$order</span>[<span class="s">'checkout_url'</span>]);</pre>
                    </div>
                    <div class="lang-pane" data-group="create" data-lang="js">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">const</span> body = JSON.stringify({
    merchant_order_id: <span class="s">'ORDER-1001'</span>,
    coin_type: <span class="s">'OBXCoin'</span>, amount: <span class="s">'50.00'</span>,
    callback_url: <span class="s">'https://your-store.com/thanks'</span>,
});
<span class="k">const</span> { data } = <span class="k">await</span> fetch(<span class="s">'{{ url("/api/payment/orders") }}'</span>, {
    method: <span class="s">'POST'</span>, body, headers: obxHeaders(API_KEY, API_SECRET, body),
}).then(r => r.json());
window.location.href = data.checkout_url;</pre>
                    </div>
                    <div class="lang-pane" data-group="create" data-lang="python">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code">body = json.dumps({
    <span class="s">'merchant_order_id'</span>: <span class="s">'ORDER-1001'</span>, <span class="s">'coin_type'</span>: <span class="s">'OBXCoin'</span>,
    <span class="s">'amount'</span>: <span class="s">'50.00'</span>, <span class="s">'callback_url'</span>: <span class="s">'https://your-store.com/thanks'</span>,
}).encode()
resp  = requests.post(<span class="s">'{{ url("/api/payment/orders") }}'</span>,
    data=body, headers=obx_headers(API_KEY, API_SECRET, body))
order = resp.json()[<span class="s">'data'</span>]</pre>
                    </div>
                    <div class="lang-pane" data-group="create" data-lang="java">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code">String body = <span class="s">"""
    {"merchant_order_id":"ORDER-1001","coin_type":"OBXCoin","amount":"50.00",
     "callback_url":"https://your-store.com/thanks"}"""</span>;
<span class="k">var</span> hdrs = obxHeaders(API_KEY, API_SECRET, body);
<span class="k">var</span> rb   = HttpRequest.newBuilder()
    .uri(URI.create(<span class="s">"{{ url("/api/payment/orders") }}"</span>))
    .POST(HttpRequest.BodyPublishers.ofString(body));
hdrs.forEach(rb::header);
<span class="k">var</span> resp = client.send(rb.build(), HttpResponse.BodyHandlers.ofString());</pre>
                    </div>
                    <div class="lang-pane" data-group="create" data-lang="flutter">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">final</span> body = jsonEncode({
    <span class="s">'merchant_order_id'</span>: <span class="s">'ORDER-1001'</span>, <span class="s">'coin_type'</span>: <span class="s">'OBXCoin'</span>,
    <span class="s">'amount'</span>: <span class="s">'50.00'</span>, <span class="s">'callback_url'</span>: <span class="s">'https://your-store.com/thanks'</span>,
});
<span class="k">final</span> res = <span class="k">await</span> http.post(
    Uri.parse(<span class="s">'{{ url("/api/payment/orders") }}'</span>),
    headers: obxHeaders(apiKey, secret, body), body: body,
);
<span class="k">final</span> order = jsonDecode(res.body)[<span class="s">'data'</span>];</pre>
                    </div>
                </div>
            </div>
        </div>

        {{-- Get Order --}}
        <div class="ep-card" id="ep-get">
            <div class="ep-head">
                <span class="mtd mtd-get">GET</span>
                <span class="ep-path">/api/payment/orders/{uuid}</span>
                <span class="auth-pill auth-hmac" style="margin-left:auto;"><i class="fa fa-lock"></i> HMAC</span>
            </div>
            <div class="ep-body">
                <p>Fetch full order details. Only the merchant key that created the order can access it. GET requests sign an empty body: <code>sha256("")</code>.</p>
                <div class="lang-tabs-wrap" id="getorder-tabs">
                    <div class="lang-tabs">
                        <div class="lang-tab active" onclick="switchTab('getorder','json')"><span class="lang-dot dot-json"></span>JSON</div>
                        <div class="lang-tab" onclick="switchTab('getorder','php')"><span class="lang-dot dot-php"></span>PHP</div>
                        <div class="lang-tab" onclick="switchTab('getorder','js')"><span class="lang-dot dot-js"></span>JavaScript</div>
                        <div class="lang-tab" onclick="switchTab('getorder','python')"><span class="lang-dot dot-python"></span>Python</div>
                        <div class="lang-tab" onclick="switchTab('getorder','java')"><span class="lang-dot dot-java"></span>Java</div>
                        <div class="lang-tab" onclick="switchTab('getorder','flutter')"><span class="lang-dot dot-flutter"></span>Flutter</div>
                    </div>
                    <div class="lang-pane active" data-group="getorder" data-lang="json">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code">{
  <span class="n">"data"</span>: {
    <span class="n">"uuid"</span>:            <span class="s">"550e8400-e29b-41d4-a716-446655440000"</span>,
    <span class="n">"coin_type"</span>:       <span class="s">"OBXCoin"</span>,
    <span class="n">"amount"</span>:          <span class="s">"50.00000000"</span>,
    <span class="n">"amount_received"</span>: <span class="s">"50.00000000"</span>,
    <span class="n">"status"</span>:          <span class="s">"completed"</span>,
    <span class="n">"confirmed_at"</span>:    <span class="s">"2026-04-11T12:18:44Z"</span>
  }
}</pre>
                    </div>
                    <div class="lang-pane" data-group="getorder" data-lang="php">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="v">$ch</span> = <span class="k">curl_init</span>(<span class="s">'{{ url("/api/payment/orders") }}/'</span> . <span class="v">$uuid</span>);
<span class="k">curl_setopt_array</span>(<span class="v">$ch</span>, [CURLOPT_HTTPHEADER => obx_headers(<span class="v">$key</span>, <span class="v">$secret</span>), CURLOPT_RETURNTRANSFER => <span class="k">true</span>]);
<span class="v">$order</span> = <span class="k">json_decode</span>(<span class="k">curl_exec</span>(<span class="v">$ch</span>), <span class="k">true</span>)[<span class="s">'data'</span>];</pre>
                    </div>
                    <div class="lang-pane" data-group="getorder" data-lang="js">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">const</span> { data } = <span class="k">await</span> fetch(`{{ url('/api/payment/orders') }}/${uuid}`, {
    headers: obxHeaders(API_KEY, API_SECRET),
}).then(r => r.json());</pre>
                    </div>
                    <div class="lang-pane" data-group="getorder" data-lang="python">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code">order = requests.get(
    f<span class="s">'{{ url("/api/payment/orders") }}/{uuid}'</span>,
    headers=obx_headers(API_KEY, API_SECRET)
).json()[<span class="s">'data'</span>]</pre>
                    </div>
                    <div class="lang-pane" data-group="getorder" data-lang="java">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">var</span> rb = HttpRequest.newBuilder()
    .uri(URI.create(<span class="s">"{{ url("/api/payment/orders") }}/"</span> + uuid)).GET();
obxHeaders(API_KEY, API_SECRET, <span class="s">""</span>).forEach(rb::header);
<span class="k">var</span> resp = client.send(rb.build(), HttpResponse.BodyHandlers.ofString());</pre>
                    </div>
                    <div class="lang-pane" data-group="getorder" data-lang="flutter">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">final</span> res   = <span class="k">await</span> http.get(Uri.parse(<span class="s">'{{ url("/api/payment/orders") }}/$uuid'</span>),
    headers: obxHeaders(apiKey, secret));
<span class="k">final</span> order = jsonDecode(res.body)[<span class="s">'data'</span>];</pre>
                    </div>
                </div>
            </div>
        </div>

        {{-- Poll Status --}}
        <div class="ep-card" id="ep-status">
            <div class="ep-head">
                <span class="mtd mtd-get">GET</span>
                <span class="ep-path">/api/payment/orders/{uuid}/status</span>
                <span class="auth-pill auth-public" style="margin-left:auto;"><i class="fa fa-globe"></i> Public</span>
            </div>
            <div class="ep-body">
                <p>Lightweight status poll — no auth needed. The checkout page calls this every 12 s automatically.</p>
                <div class="lang-tabs-wrap" id="status-tabs">
                    <div class="lang-tabs">
                        <div class="lang-tab active" onclick="switchTab('status','json')"><span class="lang-dot dot-json"></span>JSON</div>
                        <div class="lang-tab" onclick="switchTab('status','php')"><span class="lang-dot dot-php"></span>PHP</div>
                        <div class="lang-tab" onclick="switchTab('status','js')"><span class="lang-dot dot-js"></span>JavaScript</div>
                        <div class="lang-tab" onclick="switchTab('status','python')"><span class="lang-dot dot-python"></span>Python</div>
                        <div class="lang-tab" onclick="switchTab('status','java')"><span class="lang-dot dot-java"></span>Java</div>
                        <div class="lang-tab" onclick="switchTab('status','flutter')"><span class="lang-dot dot-flutter"></span>Flutter</div>
                    </div>
                    <div class="lang-pane active" data-group="status" data-lang="json">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code">{ <span class="n">"status"</span>: <span class="s">"confirming"</span>, <span class="n">"amount_received"</span>: <span class="s">"50.00000000"</span>, <span class="n">"expires_at"</span>: <span class="s">"2026-04-11T12:30:00Z"</span> }</pre>
                    </div>
                    <div class="lang-pane" data-group="status" data-lang="php">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="v">$status</span> = <span class="k">json_decode</span>(
    <span class="k">file_get_contents</span>(<span class="s">'{{ url("/api/payment/orders") }}/'</span>.<span class="v">$uuid</span>.<span class="s">'/status'</span>), <span class="k">true</span>);</pre>
                    </div>
                    <div class="lang-pane" data-group="status" data-lang="js">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">const</span> s = <span class="k">await</span> fetch(`{{ url('/api/payment/orders') }}/${uuid}/status`).then(r => r.json());</pre>
                    </div>
                    <div class="lang-pane" data-group="status" data-lang="python">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code">status = requests.get(f<span class="s">'{{ url("/api/payment/orders") }}/{uuid}/status'</span>).json()</pre>
                    </div>
                    <div class="lang-pane" data-group="status" data-lang="java">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">var</span> req  = HttpRequest.newBuilder()
    .uri(URI.create(<span class="s">"{{ url("/api/payment/orders") }}/"</span> + uuid + <span class="s">"/status"</span>)).GET().build();
<span class="k">var</span> resp = client.send(req, HttpResponse.BodyHandlers.ofString());</pre>
                    </div>
                    <div class="lang-pane" data-group="status" data-lang="flutter">
                        <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                        <pre class="code"><span class="k">final</span> s = <span class="k">await</span> http.get(Uri.parse(<span class="s">'{{ url("/api/payment/orders") }}/$uuid/status'</span>))
                   .then((r) => jsonDecode(r.body));</pre>
                    </div>
                </div>
            </div>
        </div>

        {{-- Check Deposit --}}
        <div class="ep-card" id="ep-check">
            <div class="ep-head">
                <span class="mtd mtd-post">POST</span>
                <span class="ep-path">/api/payment/orders/{uuid}/check</span>
                <span class="auth-pill auth-public" style="margin-left:auto;"><i class="fa fa-globe"></i> Public</span>
            </div>
            <div class="ep-body">
                <p>Triggers an on-demand blockchain scan for deposits to the payment address. No request body required.</p>
            </div>
        </div>
    </div>

    {{-- ── 3. Webhooks ── --}}
    <div class="doc-section" id="section-webhooks">
        <div class="doc-section-title"><i class="fa fa-bolt"></i> Webhooks</div>
        <p style="font-size:13px;color:var(--text-2);margin-bottom:14px;">
            OBXCoin POSTs a signed payload to your webhook URL on <code>completed</code>, <code>expired</code>, or <code>underpaid</code>. Retried up to 3× (60 s → 300 s → 900 s). Return HTTP&nbsp;200 to confirm.
        </p>
        <div class="lang-tabs-wrap" id="webhook-tabs">
            <div class="lang-tabs">
                <div class="lang-tab active" onclick="switchTab('webhook','json')"><span class="lang-dot dot-json"></span>JSON</div>
                <div class="lang-tab" onclick="switchTab('webhook','php')"><span class="lang-dot dot-php"></span>PHP</div>
                <div class="lang-tab" onclick="switchTab('webhook','js')"><span class="lang-dot dot-js"></span>JavaScript</div>
                <div class="lang-tab" onclick="switchTab('webhook','python')"><span class="lang-dot dot-python"></span>Python</div>
                <div class="lang-tab" onclick="switchTab('webhook','java')"><span class="lang-dot dot-java"></span>Java</div>
                <div class="lang-tab" onclick="switchTab('webhook','flutter')"><span class="lang-dot dot-flutter"></span>Flutter</div>
            </div>
            <div class="lang-pane active" data-group="webhook" data-lang="json">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="c">// POST to your webhook_url  |  Header: X-OBX-Signature: &lt;hmac-sha256&gt;</span>
{
  <span class="n">"event"</span>:             <span class="s">"payment.completed"</span>,
  <span class="n">"uuid"</span>:              <span class="s">"550e8400-e29b-41d4-a716-446655440000"</span>,
  <span class="n">"merchant_order_id"</span>: <span class="s">"ORDER-1001"</span>,
  <span class="n">"coin_type"</span>:         <span class="s">"OBXCoin"</span>,
  <span class="n">"amount"</span>:            <span class="s">"50.00000000"</span>,
  <span class="n">"amount_received"</span>:   <span class="s">"50.00000000"</span>,
  <span class="n">"status"</span>:            <span class="s">"completed"</span>,
  <span class="n">"confirmed_at"</span>:      <span class="s">"2026-04-11T12:18:44Z"</span>,
  <span class="n">"metadata"</span>:          { <span class="n">"product"</span>: <span class="s">"Premium Plan"</span> }
}</pre>
            </div>
            <div class="lang-pane" data-group="webhook" data-lang="php">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="v">$raw</span>      = <span class="k">file_get_contents</span>(<span class="s">'php://input'</span>);
<span class="v">$received</span> = <span class="v">$_SERVER</span>[<span class="s">'HTTP_X_OBX_SIGNATURE'</span>] ?? <span class="s">''</span>;
<span class="v">$key</span>      = <span class="k">hex2bin</span>(<span class="k">hash</span>(<span class="s">'sha256'</span>, <span class="v">$webhookSecret</span>));
<span class="k">if</span> (!<span class="k">hash_equals</span>(<span class="k">hash_hmac</span>(<span class="s">'sha256'</span>, <span class="v">$raw</span>, <span class="v">$key</span>), <span class="v">$received</span>)) { <span class="k">http_response_code</span>(<span class="num">403</span>); <span class="k">exit</span>; }
<span class="v">$event</span> = <span class="k">json_decode</span>(<span class="v">$raw</span>, <span class="k">true</span>);
<span class="k">if</span> (<span class="v">$event</span>[<span class="s">'status'</span>] === <span class="s">'completed'</span>) fulfillOrder(<span class="v">$event</span>[<span class="s">'merchant_order_id'</span>]);
<span class="k">http_response_code</span>(<span class="num">200</span>);</pre>
            </div>
            <div class="lang-pane" data-group="webhook" data-lang="js">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="c">// Express — use raw body middleware</span>
app.post(<span class="s">'/webhooks/obx'</span>, express.raw({ type: <span class="s">'*/*'</span> }), (req, res) => {
    <span class="k">const</span> keyB = Buffer.from(crypto.createHash(<span class="s">'sha256'</span>).update(WEBHOOK_SECRET).digest(<span class="s">'hex'</span>), <span class="s">'hex'</span>);
    <span class="k">const</span> exp  = crypto.createHmac(<span class="s">'sha256'</span>, keyB).update(req.body).digest(<span class="s">'hex'</span>);
    <span class="k">if</span> (!crypto.timingSafeEqual(Buffer.from(exp), Buffer.from(req.headers[<span class="s">'x-obx-signature'</span>] || <span class="s">''</span>)))
        <span class="k">return</span> res.sendStatus(<span class="num">403</span>);
    <span class="k">const</span> ev = JSON.parse(req.body);
    <span class="k">if</span> (ev.status === <span class="s">'completed'</span>) fulfillOrder(ev.merchant_order_id);
    res.sendStatus(<span class="num">200</span>);
});</pre>
            </div>
            <div class="lang-pane" data-group="webhook" data-lang="python">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="c"># Flask</span>
<span class="v">@app.route</span>(<span class="s">'/webhooks/obx'</span>, methods=[<span class="s">'POST'</span>])
<span class="k">def</span> <span class="v">obx_webhook</span>():
    raw  = request.get_data()
    keyb = bytes.fromhex(hashlib.sha256(WEBHOOK_SECRET.encode()).hexdigest())
    exp  = hmac.new(keyb, raw, hashlib.sha256).hexdigest()
    <span class="k">if not</span> hmac.compare_digest(exp, request.headers.get(<span class="s">'X-OBX-Signature'</span>, <span class="s">''</span>)): abort(<span class="num">403</span>)
    ev = request.get_json()
    <span class="k">if</span> ev[<span class="s">'status'</span>] == <span class="s">'completed'</span>: fulfill_order(ev[<span class="s">'merchant_order_id'</span>])
    <span class="k">return</span> <span class="s">''</span>, <span class="num">200</span></pre>
            </div>
            <div class="lang-pane" data-group="webhook" data-lang="java">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="c">// Spring Boot</span>
<span class="k">@PostMapping</span>(<span class="s">"/webhooks/obx"</span>)
ResponseEntity&lt;Void&gt; <span class="v">webhook</span>(<span class="k">@RequestBody byte</span>[] raw,
        <span class="k">@RequestHeader</span>(<span class="s">"X-OBX-Signature"</span>) String recv) <span class="k">throws</span> Exception {
    <span class="k">byte</span>[] keyB = HexFormat.of().parseHex(sha256Hex(WEBHOOK_SECRET.getBytes()));
    Mac mac = Mac.getInstance(<span class="s">"HmacSHA256"</span>);
    mac.init(<span class="k">new</span> SecretKeySpec(keyB, <span class="s">"HmacSHA256"</span>));
    <span class="k">if</span> (!HexFormat.of().formatHex(mac.doFinal(raw)).equals(recv))
        <span class="k">return</span> ResponseEntity.status(<span class="num">403</span>).build();
    <span class="k">var</span> ev = <span class="k">new</span> JSONObject(<span class="k">new</span> String(raw));
    <span class="k">if</span> (<span class="s">"completed"</span>.equals(ev.getString(<span class="s">"status"</span>))) fulfillOrder(ev.getString(<span class="s">"merchant_order_id"</span>));
    <span class="k">return</span> ResponseEntity.ok().build();
}</pre>
            </div>
            <div class="lang-pane" data-group="webhook" data-lang="flutter">
                <div class="lang-pane-hdr"><button class="cp-btn" onclick="copyPane(this)"><i class="fa fa-copy"></i> Copy</button></div>
                <pre class="code"><span class="c">// Dart/shelf server-side</span>
<span class="k">final</span> keyB = sha256.convert(utf8.encode(webhookSecret)).bytes;
<span class="k">final</span> exp  = Hmac(sha256, keyB).convert(rawBodyBytes).toString();
<span class="k">final</span> recv = request.headers[<span class="s">'x-obx-signature'</span>] ?? <span class="s">''</span>;
<span class="k">if</span> (exp != recv) <span class="k">return</span> Response.forbidden(<span class="s">'bad sig'</span>);
<span class="k">final</span> ev = jsonDecode(utf8.decode(rawBodyBytes));
<span class="k">if</span> (ev[<span class="s">'status'</span>] == <span class="s">'completed'</span>) <span class="k">await</span> fulfillOrder(ev[<span class="s">'merchant_order_id'</span>]);</pre>
            </div>
        </div>
    </div>

    {{-- ── 4. Reference ── --}}
    <div class="doc-section" id="section-reference">
        <div class="doc-section-title"><i class="fa fa-table"></i> Reference</div>
        <div class="row">
            <div class="col-md-6" style="margin-bottom:16px;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:8px;">Order Statuses</p>
                <table class="ftable">
                    <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
                    <tbody>
                        <tr><td class="s-pending">pending</td><td>Awaiting deposit</td></tr>
                        <tr><td class="s-confirming">confirming</td><td>Deposit seen, confirming on-chain</td></tr>
                        <tr><td class="s-completed">completed</td><td>Fully paid — fulfil the order</td></tr>
                        <tr><td class="s-expired">expired</td><td>TTL elapsed, no payment received</td></tr>
                        <tr><td class="s-underpaid">underpaid</td><td>Partial payment only</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:8px;">Error Codes</p>
                <table class="ftable">
                    <thead><tr><th>HTTP</th><th>Cause</th></tr></thead>
                    <tbody>
                        <tr><td>400</td><td>Validation failed — check <code>errors</code> field</td></tr>
                        <tr><td>401</td><td>Missing / invalid / expired auth headers</td></tr>
                        <tr><td>401</td><td>Signature mismatch</td></tr>
                        <tr><td>403</td><td>IP or coin not permitted by key</td></tr>
                        <tr><td>404</td><td>Order not found or no access</td></tr>
                        <tr><td>422</td><td>Max 10 active keys reached</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div>{{-- /docs-main --}}
    </div>{{-- /docs-layout --}}

</div>{{-- /docs-wrap --}}
@endsection

@section('script')
<script>
function switchTab(group, lang) {
    var wrap = document.getElementById(group + '-tabs');
    if (!wrap) return;
    wrap.querySelectorAll('.lang-pane').forEach(function(p) {
        p.classList.toggle('active', p.dataset.lang === lang);
    });
    wrap.querySelectorAll('.lang-tab').forEach(function(t) {
        var fn = t.getAttribute('onclick') || '';
        t.classList.toggle('active', fn.indexOf("'" + lang + "'") !== -1);
    });
}

function copyPane(btn) {
    var text = btn.closest('.lang-pane').querySelector('pre.code').innerText;
    navigator.clipboard.writeText(text).then(function() {
        var o = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
        btn.style.color = 'var(--success)';
        setTimeout(function() { btn.innerHTML = o; btn.style.color = ''; }, 2000);
    });
}

function navTo(el, targetId) {
    // Update active state in sidebar
    document.querySelectorAll('.docs-nav-item').forEach(function(a) { a.classList.remove('active'); });
    if (el && el.classList) el.classList.add('active');
    // Smooth scroll
    var target = document.getElementById(targetId);
    if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
}

// Highlight sidebar item on scroll
(function() {
    var sections = ['section-quickstart','section-auth','ep-coins','ep-create','ep-get','ep-status','ep-check','section-webhooks','section-reference'];
    window.addEventListener('scroll', function() {
        var scrollY = window.scrollY + 120;
        var active = sections[0];
        sections.forEach(function(id) {
            var el = document.getElementById(id);
            if (el && el.offsetTop <= scrollY) active = id;
        });
        document.querySelectorAll('.docs-nav-item').forEach(function(a) {
            var href = (a.getAttribute('href') || '').replace('#','');
            a.classList.toggle('active', href === active);
        });
    }, { passive: true });
})();
</script>
@endsection
