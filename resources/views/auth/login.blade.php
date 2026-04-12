@extends('auth.master')
@section('title', isset($title) ? $title : __('Sign In') . ' —')

@section('content')
<div class="auth-page">
    <div class="auth-card">

        {{-- Logo --}}
        <div class="auth-logo-wrap">
            <a href="{{ url('/') }}">
                <img src="{{ show_image(1,'login_logo') }}" alt="{{ settings('app_title') }}">
            </a>
        </div>

        <h1 class="auth-heading">{{ __('Welcome back') }}</h1>
        <p class="auth-sub">{{ __('Sign in to your') }} <span class="gradient-text">{{ settings('app_title') }}</span> {{ __('account') }}</p>

        {{Form::open(['route' => 'loginProcess', 'files' => true])}}

        <div class="auth-field">
            <label class="auth-label" for="login_email">{{ __('Email address') }}</label>
            <input
                type="email"
                id="login_email"
                name="email"
                value="{{ old('email') }}"
                class="auth-input"
                placeholder="{{ __('you@example.com') }}"
                autocomplete="email"
            >
            @error('email')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="auth-field">
            <label class="auth-label" for="login_password">{{ __('Password') }}</label>
            <div class="auth-input-wrap">
                <input
                    type="password"
                    id="login_password"
                    name="password"
                    class="auth-input has-eye"
                    placeholder="{{ __('••••••••') }}"
                    autocomplete="current-password"
                >
                <button type="button" class="auth-eye" id="toggleLoginPwd" aria-label="{{ __('Show password') }}">
                    <i class="fa fa-eye-slash"></i>
                </button>
            </div>
            @error('password')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="auth-forgot">
            <a href="{{ route('forgotPassword') }}">{{ __('Forgot password?') }}</a>
        </div>

        @if(isset(allsetting()['google_recapcha']) && allsetting()['google_recapcha'] == STATUS_ACTIVE)
        <div class="auth-captcha">
            {!! app('captcha')->display() !!}
            @error('g-recaptcha-response')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>
        @endif

        <button type="submit" class="auth-btn">{{ __('Sign In') }}</button>

        {{Form::close()}}

        <div class="auth-divider">
            {{ __("Don't have an account?") }} <a href="{{ route('signUp') }}">{{ __('Create one') }}</a>
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
    var btn = document.getElementById('toggleLoginPwd');
    var pwd = document.getElementById('login_password');
    if (btn && pwd) {
        btn.addEventListener('click', function(){
            var show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fa fa-eye' : 'fa fa-eye-slash';
        });
    }
})();
</script>
@endsection
