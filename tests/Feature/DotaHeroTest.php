<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
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
        $this->seedProfile();
        $this->fakeOpenDota();

        $response = $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Anti-Mage');
        $response->assertSee('Dendi');
        $response->assertSee('По сохранённой истории матчей');
        $response->assertSee('Бенчмарки');
        $response->assertSee('GPM');
        $response->assertSee('XPM');
        $response->assertSee('Нетворс');
        $response->assertSee('DMG');
        $response->assertSee('28 000');
        $response->assertSee('21 500');
        $response->assertSee('KDA');
        $response->assertSee('Топ 10%');
        $response->assertSee(route('player', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertSee(route('match', ['matchId' => 9100000001, 'player_id' => self::ACCOUNT_ID]), false);

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
            && $matches[0]['gpm'] === 650
            && $matches[2]['net_worth'] === 28000
            && $matches[2]['hero_damage'] === 21500);

        $response->assertViewHas('benchmarksUnavailable', false);

        $this->assertTrue(Cache::has('opendota:hero-benchmarks:v1:'.self::HERO_ID));
        $this->assertFalse(Cache::has('opendota:player-header:v1:'.self::ACCOUNT_ID));
        $this->assertFalse(Cache::has('opendota:player-hero:v2:'.self::ACCOUNT_ID.':'.self::HERO_ID));
        Http::assertSentCount(1);
    }

    public function test_second_visit_to_hero_page_is_served_from_cache(): void
    {
        $this->seedProfile();
        $this->fakeOpenDota();

        $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]))->assertOk();

        $sentAfterFirstVisit = count(Http::recorded());

        $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]))->assertOk();

        $this->assertSame($sentAfterFirstVisit, count(Http::recorded()));
    }

    public function test_unknown_player_on_hero_page_returns_404(): void
    {
        $response = $this->get(route('player.hero', [
            'accountId' => 999999999,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertNotFound();
        $response->assertSee('Игрок не найден');
    }

    public function test_hero_page_degrades_when_benchmarks_are_unavailable(): void
    {
        $this->seedProfile();
        $this->fakeOpenDota([
            'api.opendota.com/api/benchmarks*' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Бенчмарки временно недоступны');
        $response->assertViewHas('benchmarksUnavailable', true);
        $response->assertViewHas('benchmarks', fn (array $rows) => collect($rows)->every(
            fn (array $row) => $row['available'] === false
        ));
        $response->assertDontSee('style="left:');
    }

    public function test_hero_page_uses_local_stats_when_all_api_calls_time_out(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 504)]);
        $this->seedProfile();

        $response = $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Anti-Mage');
        $response->assertSee('66.7%');
        $response->assertSee('4.64');
        $response->assertSee('Бенчмарки временно недоступны');
        $response->assertViewHas('benchmarksUnavailable', true);
        $response->assertViewHas('summary', fn (array $summary) => $summary['games'] === 3
            && $summary['wins'] === 2
            && $summary['gpm'] === 583
            && $summary['xpm'] === 667);
        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeOpenDota(array $overrides = []): void
    {
        Http::fake($overrides + [
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
        ]);
    }

    private function seedProfile(): void
    {
        $heroMeta = [
            'hero_id' => self::HERO_ID,
            'hero_name' => 'Anti-Mage',
            'hero_image' => '/images/heroes/antimage.png',
            'hero_icon' => '/images/heroes/icons/antimage.png',
        ];

        $history = [
            $heroMeta + [
                'match_id' => 9100000001,
                'win' => true,
                'kills' => 10,
                'deaths' => 2,
                'assists' => 15,
                'gpm' => 650,
                'xpm' => 700,
                'net_worth' => 31200,
                'hero_damage' => 24500,
                'duration' => 2400,
                'duration_label' => '40:00',
                'start_time' => now()->subDays(1)->timestamp,
            ],
            $heroMeta + [
                'match_id' => 9100000002,
                'win' => false,
                'kills' => 3,
                'deaths' => 7,
                'assists' => 5,
                'gpm' => 400,
                'xpm' => 500,
                'net_worth' => 19800,
                'hero_damage' => 15000,
                'duration' => 2100,
                'duration_label' => '35:00',
                'start_time' => now()->subDays(2)->timestamp,
            ],
            $heroMeta + [
                'match_id' => 9100000003,
                'win' => true,
                'kills' => 8,
                'deaths' => 2,
                'assists' => 10,
                'gpm' => 0,
                'xpm' => 0,
                'net_worth' => 0,
                'hero_damage' => 0,
                'duration' => 2700,
                'duration_label' => '45:00',
                'start_time' => now()->subDays(3)->timestamp,
            ],
            [
                'match_id' => 9100000004,
                'hero_id' => 2,
                'hero_name' => 'Axe',
                'win' => true,
                'kills' => 99,
                'deaths' => 0,
                'assists' => 99,
                'gpm' => 9999,
                'xpm' => 9999,
                'duration' => 1200,
                'duration_label' => '20:00',
                'start_time' => now()->timestamp,
            ],
        ];

        $recent = [
            $heroMeta + [
                'match_id' => 9100000001,
                'win' => true,
                'kills' => 10,
                'deaths' => 2,
                'assists' => 15,
                'gpm' => 650,
                'xpm' => 700,
                'duration' => 2400,
                'duration_label' => '40:00',
            ],
            $heroMeta + [
                'match_id' => 9100000002,
                'win' => false,
                'kills' => 3,
                'deaths' => 7,
                'assists' => 5,
                'gpm' => 400,
                'xpm' => 500,
                'duration' => 2100,
                'duration_label' => '35:00',
            ],
            $heroMeta + [
                'match_id' => 9100000003,
                'win' => true,
                'kills' => 8,
                'deaths' => 2,
                'assists' => 10,
                'gpm' => 700,
                'xpm' => 800,
                'net_worth' => 28000,
                'hero_damage' => 21500,
                'duration' => 2700,
                'duration_label' => '45:00',
            ],
        ];

        PlayerProfile::create([
            'account_id' => self::ACCOUNT_ID,
            'nickname' => 'Dendi',
            'avatar' => 'https://example.com/dendi.jpg',
            'data' => [
                'account_id' => self::ACCOUNT_ID,
                'nickname' => 'Dendi',
                'avatar' => 'https://example.com/dendi.jpg',
                'cached_at' => now()->toIso8601String(),
                'matches' => $recent,
                'match_history' => $history,
            ],
            'updated_at' => now()->subMinutes(5),
        ]);
    }
}
