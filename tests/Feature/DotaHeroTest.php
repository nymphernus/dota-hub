<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DotaHeroTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_ID = 70388657;

    private const HERO_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.opendota.api_key' => null,
            'services.steam.api_key' => null,
        ]);
        Cache::flush();
    }

    public function test_hero_page_renders_summary_benchmarks_and_recent_matches(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Anti-Mage');
        $response->assertSee('Dendi');
        $response->assertSee('Бенчмарки');
        $response->assertSee('GPM');
        $response->assertSee('XPM');
        $response->assertSee('KDA');
        $response->assertSee('Топ 10%');
        $response->assertSee(route('dota.player', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertSee(route('dota.match', ['matchId' => 9100000001, 'player_id' => self::ACCOUNT_ID]), false);

        $response->assertViewHas('player', fn (array $player) => $player['account_id'] === self::ACCOUNT_ID
            && $player['nickname'] === 'Dendi');

        $response->assertViewHas('hero', fn (array $hero) => $hero['id'] === self::HERO_ID
            && $hero['name'] === 'Anti-Mage');

        $response->assertViewHas('summary', fn (array $summary) => $summary['games'] === 3
            && $summary['wins'] === 2
            && $summary['losses'] === 1
            && $summary['winrate'] === 66.7
            && $summary['kills'] === 7.0
            && $summary['deaths'] === 3.7
            && $summary['assists'] === 10.0
            && $summary['kda'] === 4.64
            && $summary['gpm'] === 583
            && $summary['xpm'] === 667
            && $summary['gpm_games'] === 3
            && $summary['xpm_games'] === 3);

        $response->assertViewHas('benchmarks', function (array $rows) {
            $benchmarks = collect($rows)->keyBy('key');

            return count($rows) === 3
                && $benchmarks['gpm']['available'] === true
                && $benchmarks['gpm']['percentile'] > 0.54
                && $benchmarks['gpm']['percentile'] < 0.55
                && $benchmarks['gpm']['average_label'] === '550'
                && $benchmarks['gpm']['elite_label'] === '850'
                && $benchmarks['xpm']['available'] === true
                && $benchmarks['kda']['available'] === true
                && $benchmarks['kda']['value'] === 4.64;
        });

        $response->assertViewHas('matches', fn (array $matches) => count($matches) === 3
            && $matches[0]['win'] === true
            && $matches[1]['win'] === false
            && $matches[0]['gpm'] === 650);

        $this->assertTrue(Cache::has('opendota:player-header:v1:'.self::ACCOUNT_ID));
        $this->assertTrue(Cache::has('opendota:player-hero:v2:'.self::ACCOUNT_ID.':'.self::HERO_ID));
        $this->assertTrue(Cache::has('opendota:hero-benchmarks:v1:'.self::HERO_ID));
        Http::assertSentCount(5);
    }

    public function test_second_visit_to_hero_page_is_served_from_cache(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]))->assertOk();

        $sentAfterFirstVisit = count(Http::recorded());

        $this->get(route('dota.player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]))->assertOk();

        $this->assertSame($sentAfterFirstVisit, count(Http::recorded()));
    }

    public function test_unknown_player_on_hero_page_returns_404(): void
    {
        Http::fake([
            'api.opendota.com/api/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $response = $this->get(route('dota.player.hero', [
            'accountId' => 999999999,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertNotFound();
        $response->assertSee('Игрок не найден');
    }

    public function test_hero_page_degrades_when_benchmarks_are_unavailable(): void
    {
        $this->fakeOpenDota([
            'api.opendota.com/api/benchmarks*' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->get(route('dota.player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Бенчмарки для этого героя пока недоступны');
        $response->assertViewHas('benchmarks', fn (array $rows) => collect($rows)->every(
            fn (array $row) => $row['available'] === false
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeOpenDota(array $overrides = []): void
    {
        Http::fake($overrides + [
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/matches*' => Http::response([
                [
                    'match_id' => 9100000001,
                    'hero_id' => self::HERO_ID,
                    'player_slot' => 0,
                    'radiant_win' => true,
                    'kills' => 10,
                    'deaths' => 2,
                    'assists' => 15,
                    'gold_per_min' => 650,
                    'xp_per_min' => 700,
                    'duration' => 2400,
                    'start_time' => now()->subDays(1)->timestamp,
                ],
                [
                    'match_id' => 9100000002,
                    'hero_id' => self::HERO_ID,
                    'player_slot' => 128,
                    'radiant_win' => true,
                    'kills' => 3,
                    'deaths' => 7,
                    'assists' => 5,
                    'gold_per_min' => 400,
                    'xp_per_min' => 500,
                    'duration' => 2100,
                    'start_time' => now()->subDays(2)->timestamp,
                ],
                [
                    'match_id' => 9100000003,
                    'hero_id' => self::HERO_ID,
                    'player_slot' => 0,
                    'radiant_win' => true,
                    'kills' => 8,
                    'deaths' => 2,
                    'assists' => 10,
                    'duration' => 2700,
                    'start_time' => now()->subDays(3)->timestamp,
                ],
            ]),
            'api.opendota.com/api/matches/9100000003*' => Http::response([
                'match_id' => 9100000003,
                'players' => [
                    [
                        'account_id' => self::ACCOUNT_ID,
                        'hero_id' => self::HERO_ID,
                        'level' => 25,
                        'net_worth' => 28000,
                        'gold_per_min' => 700,
                        'xp_per_min' => 800,
                    ],
                ],
            ]),
            'api.opendota.com/api/benchmarks*' => Http::response([
                'hero_id' => self::HERO_ID,
                'result' => [
                    'gold_per_min' => [
                        ['percentile' => 0.1, 'value' => 400],
                        ['percentile' => 0.5, 'value' => 550],
                        ['percentile' => 0.9, 'value' => 850],
                        ['percentile' => 0.99, 'value' => 1000],
                    ],
                    'xp_per_min' => [
                        ['percentile' => 0.1, 'value' => 500],
                        ['percentile' => 0.5, 'value' => 650],
                        ['percentile' => 0.9, 'value' => 850],
                        ['percentile' => 0.99, 'value' => 1000],
                    ],
                    'kills_per_min' => [
                        ['percentile' => 0.1, 'value' => 0.15],
                        ['percentile' => 0.5, 'value' => 0.30],
                        ['percentile' => 0.9, 'value' => 0.45],
                        ['percentile' => 0.99, 'value' => 0.65],
                    ],
                    'deaths_per_min' => [
                        ['percentile' => 0.1, 'value' => 0.08],
                        ['percentile' => 0.5, 'value' => 0.12],
                        ['percentile' => 0.9, 'value' => 0.18],
                        ['percentile' => 0.99, 'value' => 0.24],
                    ],
                    'assists_per_min' => [
                        ['percentile' => 0.1, 'value' => 0.15],
                        ['percentile' => 0.5, 'value' => 0.22],
                        ['percentile' => 0.9, 'value' => 0.35],
                        ['percentile' => 0.99, 'value' => 0.50],
                    ],
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => self::HERO_ID, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response([
                'profile' => [
                    'account_id' => self::ACCOUNT_ID,
                    'personaname' => 'Dendi',
                    'avatarfull' => 'https://example.com/dendi.jpg',
                    'steamid' => '76561198030654385',
                    'profileurl' => 'https://steamcommunity.com/profiles/76561198030654385',
                ],
                'rank_tier' => 80,
            ]),
        ]);
    }
}
