<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Steam\Provider as SteamProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Steam OpenID needs no client id, but Socialite's provider factory still
        // requires the driver to be registered before the first request.
        Socialite::extend('steam', function () {
            $config = (array) $this->app['config']->get('services.steam', []);

            return new SteamProvider(
                $this->app['request'],
                $config['client_id'] ?? '',
                $config['client_secret'] ?? '',
                URL::to($config['redirect'] ?? '/auth/steam/callback'),
            );
        });
    }
}
