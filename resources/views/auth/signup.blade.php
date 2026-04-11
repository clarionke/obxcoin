@extends('auth.master')
@section('title', isset($title) ? $title : __('Create Account') . ' —')

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

        {{ Form::open(['route' => 'signUpProcess', 'files' => true]) }}

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

        <div class="auth-field">
            <label class="auth-label" for="reg_email">{{ __('Email address') }} <span class="req">*</span></label>
            <input type="email" id="reg_email" name="email" value="{{ old('email') }}" class="auth-input" placeholder="{{ __('you@example.com') }}" autocomplete="email">
            @if($errors->has('email'))
                <span class="auth-error">{{ $errors->first('email') }}</span>
            @endif
        </div>

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
            {{ Form::hidden('ref_code', app('request')->input('ref_code')) }}
        @endif

        <button type="submit" class="auth-btn">{{ __('Create Account') }}</button>

        {{ Form::close() }}

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
})();
</script>
@endsection
