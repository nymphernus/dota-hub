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

        $response = $this->get(route('match', [
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
        $response->assertSee(__('ui.mode_22'));
        $response->assertSee('odd:bg-[#1c1e26] even:bg-[#111318]', false);
        $response->assertSee('!bg-[#2d313e] border-l-4 border-yellow-500', false);
        $response->assertDontSee('P1');
        $response->assertDontSee('Party #', false);
        $response->assertSee(__('ui.role_1'));
        $response->assertSee(__('ui.role_2'));
        $response->assertSee(__('ui.role_3'));
        $response->assertSee(__('ui.role_5'));
        $response->assertSee('Blink Dagger');
        $response->assertSee('Black King Bar');
        $response->assertSee(__('ui.net_worth_by_minute'));
        $response->assertSee('id="netWorthChart"', false);
        $response->assertSee('id="chart-legend"', false);
        $response->assertSee('LH / DN');
        $response->assertSee('O: 5');
        $response->assertSee('S: 3');
        $response->assertDontSee(__('ui.lane_efficiency'));
        $response->assertSee('minute_label', false);
        $response->assertSee('/images/items/ultimate_scepter.png', false);
        $response->assertSee('/images/items/aghanims_blessing.png', false);
        $response->assertSee('/images/items/aghanims_shard.png', false);
        $response->assertSee('/images/items/serrated_shiv.png', false);
        $response->assertSee('>Buff<', false);
        $response->assertSee(__('ui.players_in_match'));
        $response->assertDontSee('id="radiantNetWorthChart"', false);
        $response->assertDontSee('id="direNetWorthChart"', false);
        $response->assertDontSee('bg-blue-600', false);
        $response->assertDontSee('>P1<', false);
        $response->assertSee('K / D / A');
        $response->assertDontSee('>Роль</th>', false);
        $response->assertSee('text-lg font-bold text-mist-200', false);
        $response->assertSee('text-gray-400 text-sm', false);
        $response->assertSee('Команда Dire', false);
        $response->assertDontSee('Пики и баны');
        $response->assertDontSee('Баны');
        $response->assertSee('Башни');
        $response->assertDontSee('HEAL');
        $response->assertDontSee('Данные OpenDota');
        $response->assertDontSee('31 200');
        $response->assertSee('3 400');
        $response->assertDontSee('1 250');
        $response->assertSee(route('player', ['accountId' => 123456789]), false);
        $response->assertSee(route('player', ['accountId' => 987654321]), false);
        $response->assertDontSee('Данные о нетворсе появятся после парсинга матча');
        $response->assertDontSee('Вернуться к профилю');
        $response->assertDontSee('На главную');
        $response->assertSee('>IMP<', false);
        $response->assertDontSee('aria-label="Хлебные крошки"', false);
        $response->assertDontSee('Профиль:', false);
        $response->assertViewHas('profilePlayer', fn ($profilePlayer): bool => is_array($profilePlayer)
            && (int) ($profilePlayer['account_id'] ?? 0) === self::ACCOUNT_ID
            && ($profilePlayer['nickname'] ?? null) === 'Dendi');
        $response->assertSee('<span class="min-w-0 truncate text-sm font-semibold text-white">Dendi</span>', false);
        $response->assertSee(route('player', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertDontSee('>Dota 2</span>', false);
        $response->assertDontSee('card relative overflow-hidden', false);
        $response->assertDontSee('mt-4 card p-4', false);

        $content = (string) $response->getContent();
        $this->assertSame(1, substr_count($content, '<table'));
        $this->assertSame(24, substr_count($content, 'class="grid h-8 w-10 shrink-0 place-items-center border border-ink-700 bg-ink-900/60"'));

        $nicknamePosition = strpos($content, 'Dendi');
        $heroPosition = strpos($content, '<span>Anti-Mage</span>');
        $this->assertNotFalse($nicknamePosition);
        $this->assertNotFalse($heroPosition);
        $this->assertLessThan($heroPosition, $nicknamePosition);

        $response->assertViewHas('match', function (array $match) {
            $radiantCarry = $match['players']['radiant'][0];
            $radiantSupport = $match['players']['radiant'][1];
            $direPlayer = $match['players']['dire'][0];

            return $match['match_id'] === self::MATCH_ID
                && $match['radiant_win'] === true
                && $match['radiant_score'] === 32
                && $match['dire_score'] === 18
                && $match['lobby_type'] === 7
                && $match['is_ranked'] === true
                && $match['net_worth_chart']['has_data'] === true
                && $match['net_worth_chart']['radiant']['datasets'][0]['hero_key'] === 'antimage'
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
                && $radiantCarry['last_hits'] === 180
                && $radiantCarry['denies'] === 12
                && $radiantCarry['neutral_item']['id'] === 1605
                && $radiantCarry['neutral_item']['level'] === 3
                && count($radiantCarry['inventory']) === 6
                && $radiantCarry['inventory'][0]['id'] === 108
                && $radiantCarry['aghanims_scepter_buff'] === false
                && $radiantCarry['aghanims_shard']['id'] === 673
                && $radiantCarry['aghanims_scepter']['id'] === 108
                && $radiantCarry['obs_placed'] === 5
                && $radiantCarry['sen_placed'] === 3
                && $radiantCarry['lane_efficiency'] === 67.5
                && $radiantSupport['role']['label'] === 'Mid'
                && $direPlayer['aghanims_scepter_buff'] === true;
        });
    }

    public function test_purchase_column_is_hidden_when_item_data_is_not_parsed(): void
    {
        $this->fakeMatch(parsed: false);

        $response = $this->get(route('match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertDontSee('Закуп');
        $response->assertDontSee('Blink Dagger');
        $response->assertSee(__('ui.role_1'));
    }

    public function test_you_badge_is_never_shown_for_any_player(): void
    {
        $this->fakeMatch();

        $response = $this->get(route('match', [
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

        $response = $this->get(route('match', ['matchId' => self::MATCH_ID]));

        $response->assertOk();
        $response->assertSee('Команда Radiant', false);
        $response->assertSee('Команда Dire', false);
        $response->assertDontSee('Party #', false);
        $response->assertDontSee('>P7<', false);
        $response->assertDontSee('bg-blue-600', false);
        $response->assertSee(route('player', ['accountId' => 123456789]), false);
        $response->assertSee(route('player', ['accountId' => 987654321]), false);
    }

    public function test_match_page_renders_net_worth_chart_when_data_is_parsed(): void
    {
        $this->fakeMatch();

        $response = $this->get(route('match', ['matchId' => self::MATCH_ID]));

        $response->assertOk();
        $response->assertSee(__('ui.net_worth_by_minute'));
        $response->assertSee('id="netWorthChart"', false);
        $response->assertSee('x-data="netWorthChart(', false);
        $response->assertDontSee('id="radiantNetWorthChart"', false);
        $response->assertDontSee('id="direNetWorthChart"', false);
    }

    public function test_match_page_hides_net_worth_chart_without_parsed_history(): void
    {
        $this->fakeMatch(parsed: false);

        $response = $this->get(route('match', ['matchId' => self::MATCH_ID]));

        $response->assertOk();
        $response->assertSee(__('ui.no_net_worth_data'));
        $response->assertDontSee('id="netWorthChart"', false);
        $response->assertDontSee('x-data="netWorthChart(', false);
        $response->assertDontSee('id="radiantNetWorthChart"', false);
        $response->assertDontSee('id="direNetWorthChart"', false);
    }

    public function test_stratz_positions_override_opendota_roles_when_token_is_set(): void
    {
        $this->fakeMatch(withStratz: true);

        $response = $this->get(route('match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertSee(__('ui.role_5'));
        $response->assertSee('7.5');
        $response->assertSee('STRATZ');
        $response->assertSee('Immortal');
        $response->assertViewHas('match', function (array $match) {
            $player = $match['players']['radiant'][0];

            return $player['role']['label'] === 'Hard'
                && $player['role']['source'] === 'STRATZ'
                && $player['imp'] === 7.5
                && $player['impact'] === 7.5
                && $player['rank_tier'] === 80
                && $player['rank_label'] === 'Immortal';
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.stratz.com/graphql'));
    }

    public function test_net_worth_chart_falls_back_to_stratz_history(): void
    {
        config(['services.stratz.token' => 'test-token']);

        $history = [600, 1200, 2100, 3000];
        $this->fakeMatch(
            parsed: false,
            withStratz: true,
            stratzNetWorth: [self::ACCOUNT_ID => $history],
            stratzLanes: [1 => [6, 4], 2 => [5, 5], 3 => [3, 7]]
        );

        $response = $this->get(route('match', ['matchId' => self::MATCH_ID]));

        $response->assertOk();
        $response->assertSee('id="netWorthChart"', false);
        $response->assertDontSee(__('ui.lane_efficiency'));
        $response->assertViewHas('match', function (array $match) use ($history): bool {
            return $match['players']['radiant'][0]['net_worth_history'] === $history
                && $match['net_worth_chart']['has_data'] === true
                && $match['net_worth_chart']['minute_label'] === __('ui.minute')
                && $match['players']['radiant'][0]['hero_icon'] === '/images/heroes/icons/antimage.png';
        });
    }

    public function test_match_details_are_cached_for_repeat_views(): void
    {
        $this->fakeMatch();

        $service = app(OpenDotaService::class);

        $service->getMatch(self::MATCH_ID);
        $service->getMatch(self::MATCH_ID);

        // One match request + hero metadata + item metadata.
        Http::assertSentCount(3);
        $this->assertTrue(Cache::has('opendota:match:v11:'.self::MATCH_ID));
    }

    public function test_unknown_match_redirects_back_with_an_error(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $response = $this
            ->from(route('home'))
            ->get(route('match', ['matchId' => 999999999]));

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
            ->get(route('match', ['matchId' => self::MATCH_ID]));

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
            url('/match/'.self::MATCH_ID),
            route('match', ['matchId' => self::MATCH_ID])
        );
    }

    private function fakeMatch(bool $withStratz = false, bool $parsed = true, bool $withParties = true, array $stratzNetWorth = [], array $stratzLanes = []): void
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
                        'last_hits' => 180,
                        'denies' => 12,
                        'item_neutral' => 1605,
                        'aghanims_shard' => true,
                        'item_0' => 108,
                        'obs_placed' => 5,
                        'sen_placed' => 3,
                        'lane_efficiency_pct' => 67.5,
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
                        'ultimate_scepter' => 1,
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
                [
                    'id' => 1,
                    'name' => 'npc_dota_hero_antimage',
                    'localized_name' => 'Anti-Mage',
                    'img' => '/apps/dota2/images/heroes/antimage_full.png',
                    'icon' => '/apps/dota2/images/dota_react/heroes/icons/antimage.png',
                ],
                [
                    'id' => 2,
                    'name' => 'npc_dota_hero_axe',
                    'localized_name' => 'Axe',
                    'img' => '/apps/dota2/images/heroes/axe_full.png',
                    'icon' => '/apps/dota2/images/dota_react/heroes/icons/axe.png',
                ],
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
                'serrated_shiv' => [
                    'id' => 1605,
                    'dname' => 'Serrated Shiv',
                    'cost' => 0,
                    'img' => '/apps/dota2/images/dota_react/items/serrated_shiv.png',
                ],
                'aghanims_shard' => [
                    'id' => 673,
                    'dname' => "Aghanim's Shard",
                    'cost' => 1400,
                    'img' => '/apps/dota2/images/dota_react/items/aghanims_shard.png',
                ],
                'ultimate_scepter' => [
                    'id' => 108,
                    'dname' => "Aghanim's Scepter",
                    'cost' => 4200,
                    'img' => '/apps/dota2/images/dota_react/items/ultimate_scepter.png',
                ],
            ]),
        ];

        $matchKey = 'api.opendota.com/api/matches/'.self::MATCH_ID.'*';

        if (! $parsed || ! $withParties) {
            $payload = json_decode((string) $fakes[$matchKey]->wait()->getBody(), true);

            foreach ($payload['players'] as &$player) {
                if (! $parsed) {
                    unset(
                        $player['networth_t'],
                        $player['xp_t'],
                        $player['purchase_log'],
                        $player['last_hits'],
                        $player['denies'],
                        $player['item_neutral'],
                        $player['aghanims_shard'],
                        $player['aghanims_scepter'],
                        $player['ultimate_scepter'],
                        $player['item_0'],
                        $player['obs_placed'],
                        $player['sen_placed'],
                        $player['lane_efficiency_pct']
                    );
                }

                if (! $withParties) {
                    unset($player['party_id'], $player['party_size']);
                }
            }
            unset($player);

            $fakes[$matchKey] = Http::response($payload);
        }

        if ($withStratz) {
            $stratzPlayers = [
                [
                    'steamAccountId' => self::ACCOUNT_ID,
                    'playerSlot' => 0,
                    'position' => 5,
                    'imp' => 7.5,
                    'steamAccount' => ['seasonRank' => 80],
                ],
                ['steamAccountId' => 123456789, 'playerSlot' => 1, 'position' => 4, 'imp' => 3.25],
                ['steamAccountId' => 987654321, 'playerSlot' => 128, 'position' => 1, 'imp' => 1.2],
            ];

            foreach ($stratzPlayers as &$stratzPlayer) {
                $history = $stratzNetWorth[$stratzPlayer['steamAccountId']] ?? null;

                if ($history === null) {
                    continue;
                }

                $events = [];

                foreach ($history as $minute => $worth) {
                    $events[] = ['time' => ((int) $minute) * 60, 'networth' => $worth];
                }

                $stratzPlayer['playbackData'] = ['playerUpdateGoldEvents' => $events];
            }
            unset($stratzPlayer);

            $laneReport = ['radiant' => [], 'dire' => []];
            $laneKeys = [1 => 'safeLane', 2 => 'midLane', 3 => 'offLane'];

            foreach ($laneKeys as $lane => $key) {
                $counts = $stratzLanes[$lane] ?? null;

                if ($counts === null) {
                    continue;
                }

                $laneReport['radiant'][] = [$key => ['meleeCount' => (int) $counts[0], 'rangeCount' => 0, 'siegeCount' => 0, 'denyCount' => 0]];
                $laneReport['dire'][] = [$key => ['meleeCount' => (int) $counts[1], 'rangeCount' => 0, 'siegeCount' => 0, 'denyCount' => 0]];
            }

            $fakes['api.stratz.com/graphql*'] = Http::response([
                'data' => [
                    'match' => [
                        'laneReport' => $laneReport,
                        'players' => $stratzPlayers,
                    ],
                ],
            ]);
        }

        Http::fake($fakes);
    }
}
