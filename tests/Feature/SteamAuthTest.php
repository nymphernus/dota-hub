<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SteamAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.steam.client_secret' => 'test-key',
            'services.steam.redirect' => '/auth/steam/callback',
        ]);
    }

    public function test_guest_header_shows_steam_login_button(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Войти через Steam');
        $response->assertSee(route('auth.steam'), false);
    }

    public function test_login_redirects_to_steam_openid(): void
    {
        $response = $this->get(route('auth.steam'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('https://steamcommunity.com/openid/login', $location);
        $this->assertStringContainsString('openid.mode=checkid_setup', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        // parse_str() replaces dots in parameter names with underscores.
        $this->assertSame(url('/auth/steam/callback'), $params['openid_return_to'] ?? null);

        $returnTo = (string) ($params['openid_return_to'] ?? '');

        // Steam rejects a bare host realm with "Access Denied"; the callback
        // URL itself is a realm Valve accepts.
        $this->assertSame($returnTo, $params['openid_realm'] ?? null);
    }

    public function test_steam_allowed_hosts_include_the_application_host(): void
    {
        $allowedHosts = config('services.steam.allowed_hosts');
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        $this->assertIsArray($allowedHosts);
        $this->assertContains($appHost, $allowedHosts);
        $this->assertNotEmpty(config('services.steam.realm'));

        foreach ($allowedHosts as $host) {
            $this->assertStringNotContainsString('://', (string) $host);
        }
    }

    public function test_full_redirect_uri_is_used_verbatim(): void
    {
        config([
            'services.steam.redirect' => 'https://example.test/auth/steam/callback',
            'services.steam.realm' => 'example.test',
            'services.steam.realm_is_default' => false,
            'services.steam.force_https' => true,
        ]);

        $response = $this->get(route('auth.steam'));

        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);

        $this->assertSame('https://example.test/auth/steam/callback', $params['openid_return_to'] ?? null);
        $this->assertSame('https://example.test', $params['openid_realm'] ?? null);
    }

    public function test_callback_stores_steam_id_in_the_session(): void
    {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = '76561198030654385';
        $socialiteUser->nickname = 'Dendi';
        $socialiteUser->avatar = 'https://example.com/steam.jpg';

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('steam')->once()->andReturn($provider);

        Socialite::swap($factory);

        $this->get(route('auth.steam.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('steam_id', '76561198030654385')
            ->assertSessionHas('steam_nickname', 'Dendi')
            ->assertSessionHas('steam_avatar', 'https://example.com/steam.jpg');
    }

    public function test_callback_failure_redirects_home_without_a_session(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new \RuntimeException('bad openid'));

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('steam')->once()->andReturn($provider);

        Socialite::swap($factory);

        $this->get(route('auth.steam.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error')
            ->assertSessionMissing('steam_id');
    }

    public function test_logout_forgets_the_steam_session(): void
    {
        $this->withSession([
            'steam_id' => '76561198030654385',
            'steam_nickname' => 'Dendi',
            'steam_avatar' => 'https://example.com/steam.jpg',
        ])
            ->post(route('auth.steam.logout'))
            ->assertRedirect(route('home'))
            ->assertSessionMissing('steam_id')
            ->assertSessionMissing('steam_nickname')
            ->assertSessionMissing('steam_avatar');
    }

    public function test_authenticated_header_shows_avatar_and_logout(): void
    {
        $response = $this->withSession([
            'steam_id' => '76561198030654385',
            'steam_nickname' => 'Dendi',
            'steam_avatar' => 'https://example.com/steam.jpg',
        ])->get(route('home'));

        $response->assertOk();
        $response->assertSee('Dendi');
        $response->assertSee('Выйти');
        $response->assertDontSee('Войти через Steam');
        $response->assertSee(route('auth.steam.logout'), false);
    }
}
