<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Validator::extend('strong_pass', function ($attribute, $value, $parameters, $validator) {
            return is_string($value);
        });

        if (Schema::hasTable('admin_settings')) {
            $adm_setting = allsetting();

            $capcha_site_key = isset($adm_setting['NOCAPTCHA_SITEKEY']) ? $adm_setting['NOCAPTCHA_SITEKEY'] : env('NOCAPTCHA_SITEKEY');
            $capcha_secret_key = isset($adm_setting['NOCAPTCHA_SECRET']) ? $adm_setting['NOCAPTCHA_SECRET'] : env('NOCAPTCHA_SECRET');

            config(['captcha.sitekey' => $capcha_site_key]);
            config(['captcha.secret' => $capcha_secret_key]);
        }
    }
}
