<?php

namespace Tests\Feature;

use App\Exceptions\MatchNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchTest extends TestCase
{
    use RefreshDatabase;

    private const MATCH_ID = 9000000001;

    private const ACCOUNT_ID = 70388657;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.opendota.api_key' => null,
            'services.stratz.token' => null,
        ]);
        Cache::flush();
    }

    public function test_match_page_renders_the_scoreboard(): void
    {
        $this->fakeMatch();

        $response = $this->get(route('dota.match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Матч');
        $response->assertSee('#'.self::MATCH_ID);
        $response->assertSee('Победа Radiant');
        $response->assertSee('40:56'); // 2456 seconds
        $response->assertSee('Anti-Mage');
        $response->assertSee('Axe');
        $response->assertSee('Radiant');
        $response->assertSee('Dire');
        $response->assertDontSee('Вы');
        $response->assertSee('Ranked');
        $response->assertSee('Ranked All Pick');
        $response->assertSee('Safe');
        $response->assertSee('Mid');
        $response->assertSee('Hard');
        $response->assertSee('Blink Dagger');
        $response->assertSee('Black King Bar');
        $response->assertSee('id="radiantNetWorthChart"', false);
        $response->assertSee('id="direNetWorthChart"', false);
        $response->assertViewHas('match', function (array $match) {
            $radiantCarry = $match['players']['radiant'][0];
            $radiantSupport = $match['players']['radiant'][1];

            return $match['match_id'] === self::MATCH_ID
                && $match['radiant_win'] === true
                && $match['radiant_score'] === 32
                && $match['dire_score'] === 18
                && $match['lobby_type'] === 7
                && $match['is_ranked'] === true
                && $match['net_worth_chart']['has_data'] === true
                && count($match['net_worth_chart']['radiant']['datasets']) === 2
                && count($match['players']['radiant']) === 2
                && count($match['players']['dire']) === 2
                && $radiantCarry['role']['label'] === 'Safe'
                && $radiantCarry['purchases'][0]['name'] === 'Blink Dagger'
                && $radiantCarry['net_worth_history'] === [600, 1200, 2100, 3000]
                && $radiantSupport['role']['label'] === 'Mid';
        });
    }

    public function test_you_badge_is_never_shown_for_any_player(): void
    {
        $this->fakeMatch();

        $response = $this->get(route('dota.match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertDontSee('Вы');
        $response->assertSee('Dendi');
    }

    public function test_stratz_positions_override_opendota_roles_when_token_is_set(): void
    {
        $this->fakeMatch(withStratz: true);

        $response = $this->get(route('dota.match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Hard');
        $response->assertSee('IMP 7.5');
        $response->assertSee('STRATZ');
        $response->assertViewHas('match', function (array $match) {
            $player = $match['players']['radiant'][0];

            return $player['role']['label'] === 'Hard'
                && $player['role']['source'] === 'STRATZ'
                && $player['impact'] === 7.5;
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.stratz.com/graphql'));
    }

    public function test_match_details_are_cached_for_repeat_views(): void
    {
        $this->fakeMatch();

        $service = app(OpenDotaService::class);

        $service->getMatch(self::MATCH_ID);
        $service->getMatch(self::MATCH_ID);

        // One match request + hero metadata + item metadata.
        Http::assertSentCount(3);
        $this->assertTrue(Cache::has('opendota:match:v3:'.self::MATCH_ID));
    }

    public function test_unknown_match_redirects_back_with_an_error(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $response = $this
            ->from(route('home'))
            ->get(route('dota.match', ['matchId' => 999999999]));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'Матч не найден.');
    }

    public function test_empty_match_payload_is_treated_as_not_found(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response([]),
        ]);

        $response = $this
            ->from(route('home'))
            ->get(route('dota.match', ['matchId' => self::MATCH_ID]));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'Матч не найден.');
    }

    public function test_service_throws_when_match_has_no_players(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response([
                'match_id' => self::MATCH_ID,
                'players' => [],
            ]),
        ]);

        $this->expectException(MatchNotFoundException::class);

        app(OpenDotaService::class)->getMatch(self::MATCH_ID);
    }

    public function test_match_route_is_registered(): void
    {
        $this->assertSame(
            url('/dota/match/'.self::MATCH_ID),
            route('dota.match', ['matchId' => self::MATCH_ID])
        );
    }

    private function fakeMatch(bool $withStratz = false): void
    {
        if ($withStratz) {
            config(['services.stratz.token' => 'test-token']);
        }

        $fakes = [
            'api.opendota.com/api/matches/'.self::MATCH_ID.'*' => Http::response([
                'match_id' => self::MATCH_ID,
                'radiant_win' => true,
                'duration' => 2456,
                'start_time' => 1700000000,
                'lobby_type' => 7,
                'game_mode' => 22,
                'radiant_score' => 32,
                'dire_score' => 18,
                'players' => [
                    [
                        'account_id' => self::ACCOUNT_ID,
                        'player_slot' => 0,
                        'hero_id' => 1,
                        'personaname' => 'Dendi',
                        'level' => 25,
                        'lane_role' => 1,
                        'position_est' => 1,
                        'kills' => 10,
                        'deaths' => 2,
                        'assists' => 15,
                        'gold_per_min' => 620,
                        'xp_per_min' => 710,
                        'hero_damage' => 25000,
                        'networth_t' => [600, 1200, 2100, 3000],
                        'xp_t' => [0, 400, 900, 1600],
                        'purchase_log' => [
                            ['time' => -89, 'key' => 'tango'],
                            ['time' => 410, 'key' => 'blink'],
                            ['time' => 1260, 'key' => 'black_king_bar'],
                        ],
                    ],
                    [
                        'account_id' => 123456789,
                        'player_slot' => 1,
                        'hero_id' => 2,
                        'personaname' => 'Teammate',
                        'level' => 22,
                        'lane_role' => 2,
                        'kills' => 4,
                        'deaths' => 7,
                        'assists' => 19,
                        'gold_per_min' => 410,
                        'xp_per_min' => 520,
                        'hero_damage' => 12000,
                        'networth_t' => [600, 900, 1400, 1900],
                        'purchase_log' => [
                            ['time' => 620, 'key' => 'blink'],
                        ],
                    ],
                    [
                        'account_id' => 987654321,
                        'player_slot' => 128,
                        'hero_id' => 2,
                        'personaname' => 'Enemy',
                        'level' => 20,
                        'position_est' => 5,
                        'kills' => 6,
                        'deaths' => 9,
                        'assists' => 8,
                        'gold_per_min' => 380,
                        'xp_per_min' => 460,
                        'hero_damage' => 16000,
                        'networth_t' => [600, 750, 1100, 1500],
                    ],
                    [
                        'account_id' => 0,
                        'player_slot' => 129,
                        'hero_id' => 1,
                        'level' => 18,
                        'lane_role' => 3,
                        'kills' => 2,
                        'deaths' => 11,
                        'assists' => 5,
                        'gold_per_min' => 290,
                        'xp_per_min' => 390,
                        'hero_damage' => 8000,
                    ],
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/apps/dota2/images/heroes/axe_full.png'],
            ]),
            'api.opendota.com/api/constants/items*' => Http::response([
                'blink' => [
                    'dname' => 'Blink Dagger',
                    'cost' => 2250,
                    'img' => '/apps/dota2/images/dota_react/items/blink.png',
                ],
                'black_king_bar' => [
                    'dname' => 'Black King Bar',
                    'cost' => 4050,
                    'img' => '/apps/dota2/images/dota_react/items/black_king_bar.png',
                ],
                'tango' => [
                    'dname' => 'Tango',
                    'cost' => 90,
                    'img' => '/apps/dota2/images/dota_react/items/tango.png',
                ],
            ]),
        ];

        if ($withStratz) {
            $fakes['api.stratz.com/graphql*'] = Http::response([
                'data' => [
                    'match' => [
                        'players' => [
                            ['steamAccountId' => self::ACCOUNT_ID, 'position' => 5, 'imp' => 7.5],
                            ['steamAccountId' => 123456789, 'position' => 4, 'imp' => 3.25],
                            ['steamAccountId' => 987654321, 'position' => 1, 'imp' => 1.2],
                        ],
                    ],
                ],
            ]);
        }

        Http::fake($fakes);
    }
}
