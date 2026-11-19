<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\Config as SocialiteConfig;
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
            $config['redirect'] = URL::to($config['redirect'] ?? '/auth/steam/callback');

            if (! empty($config['realm_is_default'])) {
                $parts = parse_url($config['redirect']);
                $port = $parts['port'] ?? null;
                $config['realm'] = ($parts['host'] ?? 'localhost')
                    .($port && ! in_array((int) $port, [80, 443], true) ? ':'.$port : '')
                    .rtrim((string) ($parts['path'] ?? ''), '/');
            }

            // setConfig() is what exposes realm/allowed_hosts to the Steam
            // provider. Constructing it directly would silently ignore them.
            return (new SteamProvider($this->app['request'], '', '', ''))
                ->setConfig(new SocialiteConfig(
                    $config['client_id'] ?? '',
                    $config['client_secret'] ?? '',
                    $config['redirect'],
                    $config,
                ));
        });
    }
}
