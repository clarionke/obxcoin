@extends('auth.master')
@section('title', isset($title) ? $title : __('Create Account') . ' —')

@section('style')
<style>
    /* ── Country / phone extras ─────────────────────────────── */
    .auth-select { width:100%; background:var(--bg3); border:1px solid var(--border); border-radius:10px; color:var(--text); font-size:.95rem; padding:11px 16px; outline:none; transition:border-color .2s,box-shadow .2s; font-family:inherit; appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; padding-right:36px; cursor:pointer; }
    .auth-select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(108,99,255,.18); }
    .auth-select option { background:var(--card); color:var(--text); }
    .phone-row { display:flex; gap:0; }
    .phone-dial { width:90px; flex-shrink:0; background:var(--bg3); border:1px solid var(--border); border-radius:10px 0 0 10px; color:var(--text); font-size:.88rem; font-weight:600; padding:11px 8px; text-align:center; pointer-events:none; }
    .phone-input { flex:1; border-radius:0 10px 10px 0; border-left:none !important; }
</style>
@endsection

@section('content')
<div class="auth-page">
    <div class="auth-card">

        {{-- Logo --}}
        <div class="auth-logo-wrap">
            <a href="{{ url('/') }}">
                <img src="{{ show_image(1,'login_logo') }}" alt="{{ settings('app_title') }}">
            </a>
        </div>

        <h1 class="auth-heading">{{ __('Create your account') }}</h1>
        <p class="auth-sub">{{ __('Join the') }} <span class="gradient-text">{{ settings('app_title') }}</span> {{ __('ecosystem today') }}</p>

        @if(session()->has('dismiss'))
        <div class="auth-alert danger">{{ session('dismiss') }}</div>
        @endif
        @if(session()->has('success'))
        <div class="auth-alert success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('signUpProcess') }}" enctype="multipart/form-data">
        @csrf

        {{-- Name row --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
            <div class="auth-field">
                <label class="auth-label" for="reg_first_name">{{ __('First name') }} <span class="req">*</span></label>
                <input type="text" id="reg_first_name" name="first_name" value="{{ old('first_name') }}" class="auth-input" placeholder="{{ __('John') }}" autocomplete="given-name">
                @if($errors->has('first_name'))
                    <span class="auth-error">{{ $errors->first('first_name') }}</span>
                @endif
            </div>
            <div class="auth-field">
                <label class="auth-label" for="reg_last_name">{{ __('Last name') }} <span class="req">*</span></label>
                <input type="text" id="reg_last_name" name="last_name" value="{{ old('last_name') }}" class="auth-input" placeholder="{{ __('Doe') }}" autocomplete="family-name">
                @if($errors->has('last_name'))
                    <span class="auth-error">{{ $errors->first('last_name') }}</span>
                @endif
            </div>
        </div>

        {{-- Email --}}
        <div class="auth-field">
            <label class="auth-label" for="reg_email">{{ __('Email address') }} <span class="req">*</span></label>
            <input type="email" id="reg_email" name="email" value="{{ old('email') }}" class="auth-input" placeholder="{{ __('you@example.com') }}" autocomplete="email">
            @if($errors->has('email'))
                <span class="auth-error">{{ $errors->first('email') }}</span>
            @endif
        </div>

        {{-- Country --}}
        <div class="auth-field">
            <label class="auth-label" for="reg_country_display">{{ __('Country') }} <span class="req">*</span></label>
            @php $oldCountry = old('country'); @endphp
            <input type="text" id="reg_country_display" class="auth-input" value="{{ $oldCountry }}" placeholder="{{ __('Detecting your country...') }}" readonly>
            <input type="hidden" id="reg_country" name="country" value="{{ $oldCountry }}">
            <small style="color:#9aa4b2;display:block;margin-top:6px;">{{ __('Country is auto-detected and cannot be changed during registration.') }}</small>
            <span class="auth-error" id="country_auto_error" style="display:none;"></span>
            @if($errors->has('country'))
                <span class="auth-error">{{ $errors->first('country') }}</span>
            @endif
        </div>

        {{-- Phone --}}
        <div class="auth-field">
            <label class="auth-label" for="reg_phone">{{ __('Phone number') }} <span class="req">*</span></label>
            <div class="phone-row">
                <div class="phone-dial" id="phoneDial">+1</div>
                <input type="tel" id="reg_phone" name="phone" value="{{ old('phone') }}" class="auth-input phone-input" placeholder="{{ __('Phone number') }}" autocomplete="tel-national" inputmode="numeric">
            </div>
            @if($errors->has('phone'))
                <span class="auth-error">{{ $errors->first('phone') }}</span>
            @endif
        </div>

        {{-- Passwords --}}
        <div class="auth-field">
            <label class="auth-label" for="reg_password">{{ __('Password') }} <span class="req">*</span></label>
            <div class="auth-input-wrap">
                <input type="password" id="reg_password" name="password" class="auth-input has-eye" placeholder="{{ __('Min. 8 characters') }}" autocomplete="new-password">
                <button type="button" class="auth-eye" id="toggleRegPwd" aria-label="{{ __('Show password') }}">
                    <i class="fa fa-eye-slash"></i>
                </button>
            </div>
            @if($errors->has('password'))
                <span class="auth-error">{{ $errors->first('password') }}</span>
            @endif
        </div>

        <div class="auth-field">
            <label class="auth-label" for="reg_password_confirm">{{ __('Confirm password') }} <span class="req">*</span></label>
            <div class="auth-input-wrap">
                <input type="password" id="reg_password_confirm" name="password_confirmation" class="auth-input has-eye" placeholder="{{ __('Repeat password') }}" autocomplete="new-password">
                <button type="button" class="auth-eye" id="toggleRegPwdConfirm" aria-label="{{ __('Show confirm password') }}">
                    <i class="fa fa-eye-slash"></i>
                </button>
            </div>
            @if($errors->has('password_confirmation'))
                <span class="auth-error">{{ $errors->first('password_confirmation') }}</span>
            @endif
        </div>

        @if(isset(allsetting()['google_recapcha']) && allsetting()['google_recapcha'] == STATUS_ACTIVE)
        <div class="auth-captcha">
            {!! app('captcha')->display() !!}
            @error('g-recaptcha-response')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>
        @endif

        @if(app('request')->input('ref_code'))
            <input type="hidden" name="ref_code" value="{{ app('request')->input('ref_code') }}">
        @endif

        <button type="submit" class="auth-btn">{{ __('Create Account') }}</button>

        </form>

        <div class="auth-divider">
            {{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
        </div>
    </div>

    <div class="auth-back">
        <a href="{{ url('/') }}">
            <i class="fa fa-arrow-left"></i> {{ __('Back to home') }}
        </a>
    </div>
</div>
@endsection

@section('script')
<script>
(function(){
    /* ── Password toggles ──────────────────────────────────── */
    function togglePwd(btnId, inputId) {
        var btn = document.getElementById(btnId);
        var pwd = document.getElementById(inputId);
        if (!btn || !pwd) return;
        btn.addEventListener('click', function(){
            var show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fa fa-eye' : 'fa fa-eye-slash';
        });
    }
    togglePwd('toggleRegPwd', 'reg_password');
    togglePwd('toggleRegPwdConfirm', 'reg_password_confirm');

    /* ── Country → dial code map ───────────────────────────── */
    var dialCodes = {
        'Afghanistan':'+93','Albania':'+355','Algeria':'+213','Andorra':'+376','Angola':'+244',
        'Antigua and Barbuda':'+1','Argentina':'+54','Armenia':'+374','Australia':'+61','Austria':'+43',
        'Azerbaijan':'+994','Bahamas':'+1','Bahrain':'+973','Bangladesh':'+880','Barbados':'+1',
        'Belarus':'+375','Belgium':'+32','Belize':'+501','Benin':'+229','Bhutan':'+975',
        'Bolivia':'+591','Bosnia and Herzegovina':'+387','Botswana':'+267','Brazil':'+55',
        'Brunei':'+673','Bulgaria':'+359','Burkina Faso':'+226','Burundi':'+257','Cabo Verde':'+238',
        'Cambodia':'+855','Cameroon':'+237','Canada':'+1','Central African Republic':'+236','Chad':'+235',
        'Chile':'+56','China':'+86','Colombia':'+57','Comoros':'+269','Congo (Brazzaville)':'+242',
        'Congo (Kinshasa)':'+243','Costa Rica':'+506','Croatia':'+385','Cuba':'+53','Cyprus':'+357',
        'Czech Republic':'+420','Denmark':'+45','Djibouti':'+253','Dominica':'+1','Dominican Republic':'+1',
        'Ecuador':'+593','Egypt':'+20','El Salvador':'+503','Equatorial Guinea':'+240','Eritrea':'+291',
        'Estonia':'+372','Eswatini':'+268','Ethiopia':'+251','Fiji':'+679','Finland':'+358',
        'France':'+33','Gabon':'+241','Gambia':'+220','Georgia':'+995','Germany':'+49','Ghana':'+233',
        'Greece':'+30','Grenada':'+1','Guatemala':'+502','Guinea':'+224','Guinea-Bissau':'+245',
        'Guyana':'+592','Haiti':'+509','Honduras':'+504','Hungary':'+36','Iceland':'+354',
        'India':'+91','Indonesia':'+62','Iran':'+98','Iraq':'+964','Ireland':'+353','Israel':'+972',
        'Italy':'+39','Jamaica':'+1','Japan':'+81','Jordan':'+962','Kazakhstan':'+7','Kenya':'+254',
        'Kiribati':'+686','Kosovo':'+383','Kuwait':'+965','Kyrgyzstan':'+996','Laos':'+856',
        'Latvia':'+371','Lebanon':'+961','Lesotho':'+266','Liberia':'+231','Libya':'+218',
        'Liechtenstein':'+423','Lithuania':'+370','Luxembourg':'+352','Madagascar':'+261',
        'Malawi':'+265','Malaysia':'+60','Maldives':'+960','Mali':'+223','Malta':'+356',
        'Marshall Islands':'+692','Mauritania':'+222','Mauritius':'+230','Mexico':'+52',
        'Micronesia':'+691','Moldova':'+373','Monaco':'+377','Mongolia':'+976','Montenegro':'+382',
        'Morocco':'+212','Mozambique':'+258','Myanmar':'+95','Namibia':'+264','Nauru':'+674',
        'Nepal':'+977','Netherlands':'+31','New Zealand':'+64','Nicaragua':'+505','Niger':'+227',
        'Nigeria':'+234','North Korea':'+850','North Macedonia':'+389','Norway':'+47','Oman':'+968',
        'Pakistan':'+92','Palau':'+680','Palestine':'+970','Panama':'+507','Papua New Guinea':'+675',
        'Paraguay':'+595','Peru':'+51','Philippines':'+63','Poland':'+48','Portugal':'+351',
        'Qatar':'+974','Romania':'+40','Russia':'+7','Rwanda':'+250','Saint Kitts and Nevis':'+1',
        'Saint Lucia':'+1','Saint Vincent and the Grenadines':'+1','Samoa':'+685','San Marino':'+378',
        'Sao Tome and Principe':'+239','Saudi Arabia':'+966','Senegal':'+221','Serbia':'+381',
        'Seychelles':'+248','Sierra Leone':'+232','Singapore':'+65','Slovakia':'+421',
        'Slovenia':'+386','Solomon Islands':'+677','Somalia':'+252','South Africa':'+27',
        'South Korea':'+82','South Sudan':'+211','Spain':'+34','Sri Lanka':'+94','Sudan':'+249',
        'Suriname':'+597','Sweden':'+46','Switzerland':'+41','Syria':'+963','Taiwan':'+886',
        'Tajikistan':'+992','Tanzania':'+255','Thailand':'+66','Timor-Leste':'+670','Togo':'+228',
        'Tonga':'+676','Trinidad and Tobago':'+1','Tunisia':'+216','Turkey':'+90',
        'Turkmenistan':'+993','Tuvalu':'+688','Uganda':'+256','Ukraine':'+380',
        'United Arab Emirates':'+971','United Kingdom':'+44','United States':'+1',
        'Uruguay':'+598','Uzbekistan':'+998','Vanuatu':'+678','Vatican City':'+379',
        'Venezuela':'+58','Vietnam':'+84','Yemen':'+967','Zambia':'+260','Zimbabwe':'+263',
    };

    var countryHidden  = document.getElementById('reg_country');
    var countryDisplay = document.getElementById('reg_country_display');
    var countryError   = document.getElementById('country_auto_error');
    var dialEl         = document.getElementById('phoneDial');
    var signupForm     = document.querySelector('form[action*="sign-up-process"]');

    function setDial(country) {
        dialEl.textContent = dialCodes[country] || '+?';
    }

    // init dial from old() value if present
    if (countryHidden.value) setDial(countryHidden.value);

    /* ── Auto-detect country via ipapi.co ──────────────────── */

    function detectCountry() {
        fetch('https://ipapi.co/json/')
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(d){
                if (d && d.country_name) {
                    var name = d.country_name;
                    countryHidden.value = name;
                    countryDisplay.value = name;
                    setDial(name);
                    if (countryError) {
                        countryError.style.display = 'none';
                        countryError.textContent = '';
                    }
                }
            })
            .catch(function(){});
    }

    // Auto-detect on page load only if no old() value
    if (!countryHidden.value) {
        detectCountry();
    }

    if (signupForm) {
        signupForm.addEventListener('submit', function(e){
            if (!countryHidden.value) {
                e.preventDefault();
                if (countryError) {
                    countryError.textContent = '{{ __('Country detection failed. Please refresh to continue.') }}';
                    countryError.style.display = 'block';
                }
            }
        });
    }
})();
</script>
@endsection
