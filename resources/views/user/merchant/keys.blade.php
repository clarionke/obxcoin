@extends('user.master',['menu'=>'merchant', 'sub_menu'=>'merchant_keys'])
@section('title', isset($title) ? $title : 'API Keys')

@section('style')
<style>
/* ── Key Management Page ─────────────────────────── */
.page-hdr{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.page-hdr-left h4{font-size:17px;font-weight:700;color:var(--text);margin:0 0 3px;}
.page-hdr-left p{font-size:12px;color:var(--muted);margin:0;}
.key-table{width:100%;border-collapse:collapse;}
.key-table thead th{
    font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;
    color:var(--muted);background:var(--dark3);border-bottom:1px solid var(--border);
    padding:9px 14px;white-space:nowrap;
}
.key-table tbody td{
    padding:11px 14px;color:var(--text-2);border-bottom:1px solid var(--border-light);
    vertical-align:middle;font-size:13px;
}
.key-table tbody tr:hover{background:rgba(255,255,255,.015);}
.key-badge{
    display:inline-flex;align-items:center;gap:5px;padding:3px 10px;
    border-radius:20px;font-size:11px;font-weight:600;
}
.key-badge.active{background:rgba(63,185,80,.12);color:var(--success);border:1px solid rgba(63,185,80,.25);}
.key-badge.revoked{background:rgba(248,81,73,.12);color:var(--danger);border:1px solid rgba(248,81,73,.25);}
.key-badge .dot{width:6px;height:6px;border-radius:50%;background:currentColor;}
.key-id{
    font-family:'Courier New',monospace;font-size:12px;
    background:var(--dark3);color:#a5b4fc;
    padding:4px 10px;border-radius:5px;
    border:1px solid var(--border);letter-spacing:.5px;
    white-space:nowrap;
}
.copy-btn{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;background:var(--dark4);
    border:1px solid var(--border);border-radius:5px;
    font-size:11px;color:var(--muted);cursor:pointer;
    transition:all .15s;white-space:nowrap;
}
.copy-btn:hover{color:var(--text);border-color:rgba(255,255,255,.15);}
.secret-reveal-card{
    background:rgba(99,102,241,.08);
    border:1px solid rgba(99,102,241,.3);
    border-radius:var(--r);padding:18px 20px;margin-bottom:22px;
}
.secret-reveal-card h5{font-size:13px;font-weight:700;color:#a5b4fc;margin:0 0 4px;}
.secret-reveal-card p{font-size:12px;color:var(--muted);margin:0 0 12px;}
.secret-box{
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    background:var(--dark3);border:1px solid var(--border);
    border-radius:var(--r-sm);padding:10px 14px;
}
.secret-box code{
    font-family:'Courier New',monospace;font-size:13px;
    color:#f9e2af;word-break:break-all;flex:1;
}
.warning-icon{color:var(--warning);font-size:16px;}
.empty-state{
    text-align:center;padding:50px 20px;
}
.empty-state i{font-size:38px;color:var(--muted);opacity:.4;margin-bottom:12px;}
.empty-state p{color:var(--muted);font-size:13px;margin:0;}
.btn-accent{
    display:inline-flex;align-items:center;gap:7px;
    background:var(--accent);border:none;color:#fff;
    padding:9px 18px;border-radius:var(--r-sm);
    font-size:13px;font-weight:500;cursor:pointer;
    transition:background .15s;text-decoration:none;
}
.btn-accent:hover{background:var(--accent-h);color:#fff;text-decoration:none;}
.btn-revoke{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 12px;background:rgba(248,81,73,.1);
    border:1px solid rgba(248,81,73,.25);border-radius:5px;
    font-size:11.5px;color:var(--danger);cursor:pointer;
    transition:all .15s;white-space:nowrap;
}
.btn-revoke:hover{background:rgba(248,81,73,.2);}
.info-note{
    display:flex;gap:10px;align-items:flex-start;
    background:rgba(210,153,34,.08);border:1px solid rgba(210,153,34,.2);
    border-radius:var(--r-sm);padding:12px 14px;margin-bottom:18px;font-size:12.5px;
    color:var(--text-2);
}
.info-note i{color:var(--warning);margin-top:2px;flex-shrink:0;}
</style>
@endsection

@section('content')
<div class="page-hdr">
    <div class="page-hdr-left">
        <h4><i class="fa fa-key" style="color:#a5b4fc;margin-right:8px;"></i>{{__('API Key Management')}}</h4>
        <p>{{__('Generate API keys to integrate OBXCoin payments into your platform.')}}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn-accent" data-toggle="modal" data-target="#createKeyModal">
            <i class="fa fa-plus"></i> {{__('Generate New Key')}}
        </button>
        <a href="{{route('merchant.apiDocs')}}" class="btn-accent" style="background:var(--dark4);border:1px solid var(--border);color:var(--text-2);">
            <i class="fa fa-book"></i> {{__('API Docs')}}
        </a>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible" role="alert" style="background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.25);color:var(--success);border-radius:var(--r-sm);font-size:13px;padding:12px 16px;">
    <i class="fa fa-check-circle"></i> {{session('success')}}
    <button type="button" class="close" data-dismiss="alert" style="top:-2px;"><span>&times;</span></button>
</div>
@endif
@if(session('dismiss'))
<div class="alert alert-danger alert-dismissible" role="alert" style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.25);color:var(--danger);border-radius:var(--r-sm);font-size:13px;padding:12px 16px;">
    <i class="fa fa-exclamation-circle"></i> {{session('dismiss')}}
    <button type="button" class="close" data-dismiss="alert" style="top:-2px;"><span>&times;</span></button>
</div>
@endif

{{-- Show-once secret after creation --}}
@if(isset($new_secret) && $new_secret)
<div class="secret-reveal-card">
    <h5><i class="warning-icon fa fa-exclamation-triangle" style="margin-right:6px;"></i>{{__('Save Your Secret Key Now!')}}</h5>
    <p>{{__('This is the only time your API secret will be shown. Copy it and store it securely — you cannot retrieve it later.')}}</p>
    <div class="secret-box" id="secretBox">
        <code id="secretCode">{{$new_secret}}</code>
        <button class="copy-btn" onclick="copyToClipboard('secretCode', this)">
            <i class="fa fa-copy"></i> {{__('Copy')}}
        </button>
    </div>
</div>
@endif

<div class="info-note">
    <i class="fa fa-info-circle"></i>
    <span>{{__('Keep your API secret private. Use the HMAC-SHA256 signing scheme for all API requests. See the')}} <a href="{{route('merchant.apiDocs')}}" style="color:#a5b4fc;">{{__('API Documentation')}}</a> {{__('for full details.')}}</span>
</div>

{{-- Keys table --}}
<div class="card cp-user-custom-card">
    <div class="card-body" style="padding:0!important;">
        @if($keys->isEmpty())
            <div class="empty-state">
                <i class="fa fa-key"></i>
                <p>{{__('No API keys yet. Generate your first key to start accepting OBXCoin payments.')}}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="key-table">
                    <thead>
                        <tr>
                            <th>{{__('Name')}}</th>
                            <th>{{__('API Key')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Webhook URL')}}</th>
                            <th>{{__('Last Used')}}</th>
                            <th>{{__('Created')}}</th>
                            <th>{{__('Actions')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($keys as $key)
                        <tr>
                            <td>
                                <span style="font-weight:500;color:var(--text);">{{$key->name}}</span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="key-id" id="key-{{$key->id}}">{{$key->api_key}}</span>
                                    <button class="copy-btn" onclick="copyToClipboard('key-{{$key->id}}', this)">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <span class="key-badge {{$key->is_active ? 'active' : 'revoked'}}">
                                    <span class="dot"></span>
                                    {{$key->is_active ? __('Active') : __('Revoked')}}
                                </span>
                            </td>
                            <td>
                                @if($key->webhook_url)
                                    <span style="font-size:11.5px;color:var(--muted);max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{$key->webhook_url}}">
                                        {{$key->webhook_url}}
                                    </span>
                                @else
                                    <span style="color:var(--muted);font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:12px;color:var(--muted);">
                                    {{$key->last_used_at ? $key->last_used_at->diffForHumans() : __('Never')}}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:12px;color:var(--muted);">{{$key->created_at->format('M d, Y')}}</span>
                            </td>
                            <td>
                                @if($key->is_active)
                                <button class="btn-revoke"
                                    data-keyname="{{$key->name}}"
                                    data-keyid="{{$key->id}}"
                                    onclick="confirmRevoke(this)">
                                    <i class="fa fa-ban"></i> {{__('Revoke')}}
                                </button>
                                @else
                                    <span style="font-size:11.5px;color:var(--muted);">{{__('Revoked')}}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Create Key Modal --}}
<div class="modal fade" id="createKeyModal" tabindex="-1" role="dialog" aria-labelledby="createKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content dark-modal">
            <div class="modal-header align-items-center">
                <h5 class="modal-title" id="createKeyModalLabel">
                    <i class="fa fa-plus-circle" style="color:#a5b4fc;margin-right:6px;"></i>
                    {{__('Generate New API Key')}}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{route('merchant.storeKey')}}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{__('Key Name')}} <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="name" class="form-control"
                            placeholder="{{__('e.g. My Shop Production Key')}}"
                            required maxlength="80">
                        <small style="color:var(--muted);font-size:11px;">{{__('A label to identify this key.')}}</small>
                    </div>
                    <div class="form-group">
                        <label>{{__('Webhook URL')}} <span style="color:var(--muted);">{{__('(optional)')}}</span></label>
                        <input type="url" name="webhook_url" class="form-control"
                            placeholder="https://your-store.com/webhooks/obxcoin"
                            maxlength="255">
                        <small style="color:var(--muted);font-size:11px;">{{__('We\'ll POST payment status updates to this URL.')}}</small>
                    </div>
                    <div class="form-group mb-0">
                        <label>{{__('IP Whitelist')}} <span style="color:var(--muted);">{{__('(optional)')}}</span></label>
                        <input type="text" name="allowed_ips" class="form-control"
                            placeholder="192.168.1.1, 10.0.0.2"
                            maxlength="500">
                        <small style="color:var(--muted);font-size:11px;">{{__('Comma-separated IPs allowed to use this key. Leave blank to allow all.')}}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Cancel')}}</button>
                    <button type="submit" class="btn-accent">
                        <i class="fa fa-key"></i> {{__('Generate Key')}}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Revoke Confirm Modal --}}
<div class="modal fade" id="revokeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content dark-modal">
            <div class="modal-header align-items-center">
                <h5 class="modal-title" style="color:var(--danger);font-size:14px;">
                    <i class="fa fa-ban"></i> {{__('Revoke API Key')}}
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="font-size:13px;color:var(--text-2);">
                {{__('Are you sure you want to revoke')}} <strong id="revokeKeyName"></strong>?
                {{__('Any integrations using this key will stop working immediately.')}}
            </div>
            <form id="revokeForm" method="POST" action="{{route('merchant.revokeKey', ['id' => 0])}}" style="display:inline;">
                @csrf
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Cancel')}}</button>
                    <button type="submit" class="btn" style="background:rgba(248,81,73,.15);border:1px solid rgba(248,81,73,.3);color:var(--danger);padding:8px 16px;border-radius:var(--r-sm);font-size:13px;">
                        <i class="fa fa-ban"></i> {{__('Revoke')}}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
function copyToClipboard(elemId, btn) {
    var text = document.getElementById(elemId).textContent.trim();
    navigator.clipboard.writeText(text).then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
        btn.style.color = 'var(--success)';
        setTimeout(function(){ btn.innerHTML = orig; btn.style.color = ''; }, 2000);
    });
}

function confirmRevoke(btn) {
    var name = btn.getAttribute('data-keyname');
    var id   = btn.getAttribute('data-keyid');
    document.getElementById('revokeKeyName').textContent = '"' + name + '"';
    // Replace the {id} in the action with the actual key ID
    var form = document.getElementById('revokeForm');
    var baseAction = form.action.replace('/0', '/' + id);
    form.action = baseAction;
    $('#revokeModal').modal('show');
}
</script>
@endsection
