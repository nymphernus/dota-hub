<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DotaProfileTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_ID = 70388657;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.opendota.api_key' => null]);
        Cache::flush();
    }

    public function test_profile_page_renders_aggregated_opendota_data(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Dendi');
        $response->assertSee('Immortal');
        $response->assertSee('60'); // 120 wins / 200 games
        $response->assertViewHas('player', function (array $player) {
            return $player['account_id'] === self::ACCOUNT_ID
                && $player['wins'] === 120
                && $player['losses'] === 80
                && $player['winrate'] === 60.0
                && $player['is_private'] === false
                && count($player['matches']) === 2
                && count($player['heroes']) === 2;
        });

        Http::assertSentCount(6);
        $this->assertTrue(Cache::has('opendota:player:'.self::ACCOUNT_ID));
        $response->assertSee(
            route('dota.match', ['matchId' => 9000000001, 'player_id' => self::ACCOUNT_ID]),
            false
        );
    }

    public function test_second_visit_is_served_from_cache(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();
        $sentAfterFirstVisit = count(Http::recorded());

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        $this->assertSame($sentAfterFirstVisit, count(Http::recorded()));
    }

    public function test_refresh_forgets_the_profile_cache_and_rebuilds_it(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();
        $sentAfterFirstVisit = count(Http::recorded());

        $this->post(route('dota.player.refresh', ['accountId' => self::ACCOUNT_ID]))
            ->assertRedirect(route('dota.player', ['accountId' => self::ACCOUNT_ID]))
            ->assertSessionHas('status');

        $this->assertFalse(Cache::has('opendota:player:'.self::ACCOUNT_ID));

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        // Hero metadata stays cached, only the five player endpoints are re-fetched.
        $this->assertSame($sentAfterFirstVisit + 5, count(Http::recorded()));
    }

    public function test_unknown_player_returns_a_404_page(): void
    {
        Http::fake([
            'api.opendota.com/api/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => 999999999]));

        $response->assertNotFound();
        $response->assertSee('Игрок не найден');
    }

    public function test_upstream_failure_returns_a_502_page(): void
    {
        Http::fake([
            'api.opendota.com/api/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertStatus(502);
        $response->assertSee('OpenDota недоступна');
    }

    public function test_private_profile_is_flagged(): void
    {
        Http::fake([
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/wl*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/recentMatches*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/heroes*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/totals*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response([
                'profile' => [
                    'account_id' => self::ACCOUNT_ID,
                    'personaname' => null,
                    'steamid' => '76561198030654385',
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([]),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Анонимный игрок');
        $response->assertViewHas('player', fn (array $player) => $player['is_private'] === true);
    }

    private function fakeOpenDota(): void
    {
        Http::fake([
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/wl*' => Http::response(['win' => 120, 'lose' => 80]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/recentMatches*' => Http::response([
                [
                    'match_id' => 9000000001,
                    'hero_id' => 1,
                    'player_slot' => 0,
                    'radiant_win' => true,
                    'kills' => 10,
                    'deaths' => 2,
                    'assists' => 15,
                    'gold_per_min' => 620,
                    'xp_per_min' => 710,
                    'duration' => 2400,
                    'start_time' => 1700000000,
                ],
                [
                    'match_id' => 9000000002,
                    'hero_id' => 2,
                    'player_slot' => 128,
                    'radiant_win' => true,
                    'kills' => 3,
                    'deaths' => 8,
                    'assists' => 6,
                    'gold_per_min' => 340,
                    'xp_per_min' => 420,
                    'duration' => 1980,
                    'start_time' => 1699900000,
                ],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/heroes*' => Http::response([
                ['hero_id' => 1, 'games' => 12, 'win' => 8, 'last_played' => 1700000000],
                ['hero_id' => 2, 'games' => 7, 'win' => 2, 'last_played' => 1699900000],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/totals*' => Http::response([
                ['field' => 'kills', 'n' => 10, 'sum' => 87],
                ['field' => 'deaths', 'n' => 10, 'sum' => 52],
                ['field' => 'assists', 'n' => 10, 'sum' => 101],
                ['field' => 'gold_per_min', 'n' => 10, 'sum' => 4980],
                ['field' => 'xp_per_min', 'n' => 10, 'sum' => 5610],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response([
                'profile' => [
                    'account_id' => self::ACCOUNT_ID,
                    'personaname' => 'Dendi',
                    'avatarfull' => 'https://example.com/dendi.jpg',
                    'steamid' => '76561198030654385',
                    'profileurl' => 'https://steamcommunity.com/profiles/76561198030654385',
                    'loccountrycode' => 'UA',
                ],
                'rank_tier' => 80,
                'leaderboard_rank' => 120,
                'mmr_estimate' => ['estimate' => 9120],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/apps/dota2/images/heroes/axe_full.png'],
            ]),
        ]);
    }
}
