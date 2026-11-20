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
        $response->assertSee('All Pick');
        $response->assertSee('bg-ink-700/60', false);
        $response->assertDontSee('P1');
        $response->assertDontSee('Party #', false);
        $response->assertSee('Safe');
        $response->assertSee('Mid');
        $response->assertSee('Hard');
        $response->assertSee('Blink Dagger');
        $response->assertSee('Black King Bar');
        $response->assertDontSee('Нетворс по минутам');
        $response->assertDontSee('id="radiantNetWorthChart"', false);
        $response->assertDontSee('id="direNetWorthChart"', false);
        $response->assertDontSee('>Нетворс<', false);
        $response->assertDontSee('bg-blue-600', false);
        $response->assertDontSee('>P1<', false);
        $response->assertSee('Игрок / герой');
        $response->assertDontSee('>Роль</th>', false);
        $response->assertSee('text-lg font-bold text-mist-200', false);
        $response->assertSee('text-gray-400 text-sm', false);
        $response->assertSee('Команда Dire', false);
        $response->assertSee('Пики и баны');
        $response->assertSee('Башни');
        $response->assertSee('Лечение');
        $response->assertDontSee('31 200');
        $response->assertSee('3 400');
        $response->assertSee('1 250');
        $response->assertSee(route('dota.player', ['accountId' => 123456789]), false);
        $response->assertSee(route('dota.player', ['accountId' => 987654321]), false);
        $response->assertDontSee('Данные о нетворсе появятся после парсинга матча');
        $response->assertSee('class="mb-3 flex items-center gap-2 text-sm text-gray-500" aria-label="Хлебные крошки"', false);
        $response->assertSee('>Главная</a>', false);
        $response->assertSee('>Dendi</a>', false);
        $response->assertSee('>Матч #'.self::MATCH_ID.'</span>', false);
        $response->assertDontSee('Профиль игрока');
        $response->assertDontSee('card relative overflow-hidden', false);
        $response->assertDontSee('mt-4 card p-4', false);

        $content = (string) $response->getContent();
        $this->assertSame(1, substr_count($content, '<table'));

        $nicknamePosition = strpos($content, 'Dendi');
        $heroPosition = strpos($content, '<span>Anti-Mage</span>');
        $this->assertNotFalse($nicknamePosition);
        $this->assertNotFalse($heroPosition);
        $this->assertLessThan($heroPosition, $nicknamePosition);

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
                && count($match['picks_bans'] ?? []) === 2
                && $radiantCarry['role']['label'] === 'Safe'
                && $radiantCarry['purchases'][0]['name'] === 'Blink Dagger'
                && $radiantCarry['net_worth_history'] === [600, 1200, 2100, 3000]
                && $radiantCarry['net_worth'] === 31200
                && $radiantCarry['tower_damage'] === 3400
                && $radiantCarry['hero_healing'] === 1250
                && $radiantSupport['role']['label'] === 'Mid';
        });
    }

    public function test_purchase_column_is_hidden_when_item_data_is_not_parsed(): void
    {
        $this->fakeMatch(parsed: false);

        $response = $this->get(route('dota.match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertDontSee('Закуп');
        $response->assertDontSee('Blink Dagger');
        $response->assertSee('Safe');
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

    public function test_players_without_party_ids_have_no_party_badges(): void
    {
        $this->fakeMatch(withParties: false);

        $response = $this->get(route('dota.match', ['matchId' => self::MATCH_ID]));

        $response->assertOk();
        $response->assertSee('Команда Radiant', false);
        $response->assertSee('Команда Dire', false);
        $response->assertDontSee('Party #', false);
        $response->assertDontSee('P7');
        $response->assertDontSee('bg-blue-600', false);
        $response->assertSee(route('dota.player', ['accountId' => 123456789]), false);
        $response->assertSee(route('dota.player', ['accountId' => 987654321]), false);
    }

    public function test_match_page_never_renders_a_net_worth_chart(): void
    {
        foreach ([true, false] as $parsed) {
            Cache::flush();
            $this->fakeMatch(parsed: $parsed);

            $response = $this->get(route('dota.match', ['matchId' => self::MATCH_ID]));

            $response->assertOk();
            $response->assertDontSee('Нетворс по минутам');
            $response->assertDontSee('Данные о нетворсе появятся после парсинга матча');
            $response->assertDontSee('id="radiantNetWorthChart"', false);
            $response->assertDontSee('id="direNetWorthChart"', false);
            $response->assertDontSee('stats-radar-match-chart-data', false);
        }
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
        $response->assertSee('Immortal');
        $response->assertViewHas('match', function (array $match) {
            $player = $match['players']['radiant'][0];

            return $player['role']['label'] === 'Hard'
                && $player['role']['source'] === 'STRATZ'
                && $player['impact'] === 7.5
                && $player['rank_tier'] === 80
                && $player['rank_label'] === 'Immortal';
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
        $this->assertTrue(Cache::has('opendota:match:v4:'.self::MATCH_ID));
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

    private function fakeMatch(bool $withStratz = false, bool $parsed = true, bool $withParties = true): void
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
                'picks_bans' => [
                    ['is_pick' => false, 'hero_id' => 1, 'team' => 1, 'order' => 0],
                    ['is_pick' => true, 'hero_id' => 2, 'team' => 0, 'order' => 1],
                ],
                'players' => [
                    [
                        'account_id' => self::ACCOUNT_ID,
                        'player_slot' => 0,
                        'hero_id' => 1,
                        'personaname' => 'Dendi',
                        'party_id' => 7,
                        'party_size' => 2,
                        'level' => 25,
                        'lane_role' => 1,
                        'position_est' => 1,
                        'kills' => 10,
                        'deaths' => 2,
                        'assists' => 15,
                        'gold_per_min' => 620,
                        'xp_per_min' => 710,
                        'net_worth' => 31200,
                        'hero_damage' => 25000,
                        'tower_damage' => 3400,
                        'hero_healing' => 1250,
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
                        'party_id' => 7,
                        'party_size' => 2,
                        'level' => 22,
                        'lane_role' => 2,
                        'kills' => 4,
                        'deaths' => 7,
                        'assists' => 19,
                        'gold_per_min' => 410,
                        'xp_per_min' => 520,
                        'net_worth' => 18400,
                        'hero_damage' => 12000,
                        'tower_damage' => 900,
                        'hero_healing' => 400,
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
                        'net_worth' => 14100,
                        'hero_damage' => 16000,
                        'tower_damage' => 500,
                        'hero_healing' => 300,
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
                        'net_worth' => 9800,
                        'hero_damage' => 8000,
                        'tower_damage' => 200,
                        'hero_healing' => 0,
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

        $matchKey = 'api.opendota.com/api/matches/'.self::MATCH_ID.'*';

        if (! $parsed || ! $withParties) {
            $payload = json_decode((string) $fakes[$matchKey]->wait()->getBody(), true);

            foreach ($payload['players'] as &$player) {
                if (! $parsed) {
                    unset($player['networth_t'], $player['xp_t'], $player['purchase_log']);
                }

                if (! $withParties) {
                    unset($player['party_id'], $player['party_size']);
                }
            }
            unset($player);

            $fakes[$matchKey] = Http::response($payload);
        }

        if ($withStratz) {
            $fakes['api.stratz.com/graphql*'] = Http::response([
                'data' => [
                    'match' => [
                        'players' => [
                            [
                                'steamAccountId' => self::ACCOUNT_ID,
                                'position' => 5,
                                'imp' => 7.5,
                                'steamAccount' => ['seasonRank' => 80],
                            ],
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
