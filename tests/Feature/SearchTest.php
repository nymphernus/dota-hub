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

        $response = $this->post('/search', ['query' => '70388657', 'game' => 'dota']);

        $response->assertRedirect(route('dota.player', ['accountId' => 70388657]));
        Queue::assertPushed(WarmDotaProfile::class, fn (WarmDotaProfile $job) => $job->accountId === 70388657);
    }

    public function test_dota_steam_profile_url_is_converted_to_an_account_id(): void
    {
        Queue::fake();

        $response = $this->post('/search', [
            'query' => 'https://steamcommunity.com/profiles/76561198030654385',
            'game' => 'dota',
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

        $response = $this->post('/search', ['query' => 'Dendi', 'game' => 'dota']);

        $response->assertRedirect(route('dota.player', ['accountId' => 70388657]));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/search')
            && $request['q'] === 'Dendi');
    }

    public function test_unknown_dota_nickname_returns_an_error(): void
    {
        Queue::fake();

        Http::fake(['api.opendota.com/api/search*' => Http::response([])]);

        $response = $this->from('/')->post('/search', ['query' => 'definitely-not-a-player', 'game' => 'dota']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
        Queue::assertNothingPushed();
    }

    public function test_cs2_steam_id_redirects_to_the_cs2_profile(): void
    {
        $response = $this->post('/search', ['query' => '76561198030654385', 'game' => 'cs2']);

        $response->assertRedirect(route('cs2.player', ['steamId' => '76561198030654385']));
    }

    public function test_cs2_nickname_search_is_not_supported(): void
    {
        $response = $this->from('/')->post('/search', ['query' => 'Dendi', 'game' => 'cs2']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
    }

    public function test_search_requires_a_query_and_a_supported_game(): void
    {
        $response = $this->from('/')->post('/search', ['query' => '', 'game' => 'lol']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['query', 'game']);
    }

    public function test_query_shorter_than_two_characters_is_rejected(): void
    {
        $response = $this->from('/')->post('/search', ['query' => 'a', 'game' => 'dota']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('query');
    }
}
