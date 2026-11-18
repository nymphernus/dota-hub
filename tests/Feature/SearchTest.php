<?php

namespace Tests\Feature;

use App\Jobs\WarmDotaProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_dota_account_id_redirects_to_the_profile_and_warms_the_cache(): void
    {
        Queue::fake();

        $response = $this->post('/search', ['query' => '70388657']);

        $response->assertRedirect(route('dota.player', ['accountId' => 70388657]));
        Queue::assertPushed(WarmDotaProfile::class, fn (WarmDotaProfile $job) => $job->accountId === 70388657);
    }

    public function test_dota_steam_profile_url_is_converted_to_an_account_id(): void
    {
        Queue::fake();

        $response = $this->post('/search', [
            'query' => 'https://steamcommunity.com/profiles/76561198030654385',
        ]);

        $response->assertRedirect(route('dota.player', ['accountId' => 70388657]));
    }

    public function test_dota_nickname_is_resolved_through_opendota_search(): void
    {
        Queue::fake();

        Http::fake([
            'api.opendota.com/api/search*' => Http::response([
                ['account_id' => 70388657, 'personaname' => 'Dendi', 'avatarfull' => 'https://example.com/a.jpg'],
            ]),
        ]);

        $response = $this->post('/search', ['query' => 'Dendi']);

        $response->assertRedirect(route('dota.player', ['accountId' => 70388657]));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/search')
            && $request['q'] === 'Dendi');
    }

    public function test_unknown_dota_nickname_returns_an_error(): void
    {
        Queue::fake();

        Http::fake(['api.opendota.com/api/search*' => Http::response([])]);

        $response = $this->from('/')->post('/search', ['query' => 'definitely-not-a-player']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
        Queue::assertNothingPushed();
    }

    public function test_dota_vanity_url_is_resolved_through_the_steam_api(): void
    {
        Queue::fake();
        config(['services.steam.api_key' => 'test-key']);

        Http::fake([
            'api.steampowered.com/ISteamUser/ResolveVanityURL/*' => Http::response([
                'response' => ['success' => 1, 'steamid' => '76561198140015472'],
            ]),
            'api.opendota.com/api/players/179749744*' => Http::response([
                'profile' => [
                    'account_id' => 179749744,
                    'personaname' => 'Nymphernus',
                    'steamid' => '76561198140015472',
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([]),
            'api.opendota.com/api/*' => Http::response([], 404),
        ]);

        $response = $this->post('/search', [
            'query' => 'https://steamcommunity.com/id/nymphernus',
        ]);

        $response->assertRedirect(route('dota.player', ['accountId' => 179749744]));
        Queue::assertPushed(WarmDotaProfile::class, fn (WarmDotaProfile $job) => $job->accountId === 179749744);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'ResolveVanityURL')
            && $request['vanityurl'] === 'nymphernus'
            && $request['key'] === 'test-key');

        // Vanity URL -> SteamID64 -> Dota account_id -> profile page.
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Nymphernus');
    }

    public function test_dota_vanity_url_without_a_steam_key_returns_a_clear_error(): void
    {
        Queue::fake();
        config(['services.steam.api_key' => null]);

        $response = $this->from('/')->post('/search', [
            'query' => 'https://steamcommunity.com/id/nymphernus',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
        $this->assertStringContainsString('STEAM_API_KEY', session('errors')->first('query'));
        Queue::assertNothingPushed();
    }

    public function test_unknown_steam_vanity_url_returns_an_error(): void
    {
        Queue::fake();
        config(['services.steam.api_key' => 'test-key']);

        Http::fake([
            'api.steampowered.com/ISteamUser/ResolveVanityURL/*' => Http::response([
                'response' => ['success' => 42, 'message' => 'No match'],
            ]),
        ]);

        $response = $this->from('/')->post('/search', [
            'query' => 'https://steamcommunity.com/id/does-not-exist',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
    }

    public function test_search_by_steam_id_renders_the_dota_profile_with_faked_opendota(): void
    {
        Queue::fake();

        Http::fake([
            'api.opendota.com/api/players/70388657*' => Http::response([
                'profile' => [
                    'account_id' => 70388657,
                    'personaname' => 'Dendi',
                    'steamid' => '76561198030654385',
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
            ]),
            'api.opendota.com/api/*' => Http::response([], 404),
        ]);

        $response = $this->post('/search', ['query' => '76561198030654385']);

        $response->assertRedirect(route('dota.player', ['accountId' => 70388657]));

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Dendi');
    }

    public function test_search_requires_a_query(): void
    {
        $response = $this->from('/')->post('/search', ['query' => '']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['query']);
    }

    public function test_query_shorter_than_two_characters_is_rejected(): void
    {
        $response = $this->from('/')->post('/search', ['query' => 'a']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
    }
}
