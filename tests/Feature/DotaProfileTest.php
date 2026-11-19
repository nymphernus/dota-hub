<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Services\OpenDota\OpenDotaService;
use Carbon\CarbonImmutable;
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

        config([
            'services.opendota.api_key' => null,
            'services.steam.api_key' => null,
        ]);
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
                && $player['has_public_matches'] === true
                && count($player['matches']) === 2
                && $player['matches'][0]['level'] === 27
                && $player['matches'][0]['net_worth'] === 31200
                && $player['matches'][0]['gpm'] === 640
                && $player['matches'][0]['item_0']['name'] === 'Blink Dagger'
                && count($player['matches'][0]['items']) === 6
                && $player['matches'][0]['items'][5] === null
                && $player['matches'][1]['level'] === 18
                && $player['matches'][1]['net_worth'] === 14500
                && count($player['match_history']) === 6
                && count($player['heroes']) === 2;
        });

        Http::assertSentCount(11);
        $this->assertDatabaseHas('player_profiles', ['account_id' => self::ACCOUNT_ID]);
        $response->assertSee(
            route('dota.match', ['matchId' => 9000000001, 'player_id' => self::ACCOUNT_ID]),
            false
        );
        $response->assertSee(
            route('dota.player.matches', ['accountId' => self::ACCOUNT_ID]),
            false
        );
        $response->assertSee('id="tab-matches"', false);
        $response->assertDontSee('id="tab-account"', false);
        $response->assertSee('id="tab-peers"', false);
        $response->assertSee('Союзники');
        $response->assertSee(route('dota.player.heroes', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertSee(route('dota.player.peers', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertSee('images/dota/ranks/rank_icon_8.png', false);
        $response->assertSee(
            route('dota.player.hero', ['accountId' => self::ACCOUNT_ID, 'heroId' => 1]),
            false
        );
        $response->assertSee('id="heroUsageChart"', false);
        $response->assertDontSee('id="winrateChart"', false);
        $response->assertSee('data-count=', false);
        $response->assertSee('Blink Dagger');
        $response->assertSee('Нетворс');
        $response->assertSee('90 дней назад');
        $response->assertDontSee('id="recentMatchesChart"', false);
        $response->assertDontSee('panel-matches', false);
        $response->assertDontSee('Окружение');
        $response->assertSee('SteamID64');
        $response->assertViewHas('steamProfile', fn (array $profile) => $profile['available'] === false);
        $response->assertViewHas('winrate', 60.0);
        $response->assertViewHas('activityHeatmap', function (array $days) {
            $levels = array_column($days, 'level');

            return count($days) === 91
                && array_sum(array_column($days, 'count')) === 6
                && array_sum(array_column($days, 'wins')) === 3
                && in_array('win', $levels, true)
                && in_array('loss', $levels, true)
                && in_array('mixed', $levels, true);
        });
        $response->assertViewHas('heroUsage', function (array $rows) {
            return count($rows) === 6
                && $rows[0]['name'] === 'Anti-Mage'
                && ! in_array('Другие', array_column($rows, 'name'), true)
                && array_sum(array_column($rows, 'games')) === 6;
        });
        $response->assertViewHas('rankStats', fn (array $stats) => $stats['total'] === 6
            && $stats['ranked'] === 4
            && $stats['unranked'] === 2);
        $response->assertViewHas('player', function (array $player) {
            $peers = $player['peers'] ?? [];

            return count($peers) === 2
                && $peers[0]['nickname'] === 'Teammate'
                && $peers[0]['games'] === 12
                && $peers[1]['is_private'] === true;
        });
        $response->assertSee('OtherPeer');
        $response->assertViewHas(
            'recentMatches',
            fn (array $matches) => count($matches) === 2
                && $matches[0]['win'] === true
                && $matches[1]['win'] === false
        );
    }

    public function test_profile_is_persisted_in_the_database(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        $this->assertDatabaseHas('player_profiles', [
            'account_id' => self::ACCOUNT_ID,
            'nickname' => 'Dendi',
        ]);
        $record = PlayerProfile::find(self::ACCOUNT_ID);
        $this->assertNotNull($record);
        $this->assertSame('Dendi', $record->data['nickname']);
        $this->assertSame(120, $record->data['wins']);
    }

    public function test_second_visit_is_served_from_the_database(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();
        $sentAfterFirstVisit = count(Http::recorded());

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        $this->assertSame($sentAfterFirstVisit, count(Http::recorded()));
    }

    public function test_stale_database_profile_is_shown_when_opendota_is_unavailable(): void
    {
        $this->fakeOpenDota();
        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        PlayerProfile::whereKey(self::ACCOUNT_ID)->update([
            'updated_at' => CarbonImmutable::now()->subHours(2),
        ]);
        Cache::flush();
        Http::fake([
            'api.opendota.com/api/*' => Http::failedConnection('Connection timed out'),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Dendi');
        $response->assertSee('Данные из кэша (Офлайн)');
        $response->assertViewHas('player', fn (array $player) => ($player['is_offline_cache'] ?? false) === true);
    }

    public function test_refresh_forgets_the_profile_cache_and_rebuilds_it(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();
        $sentAfterFirstVisit = count(Http::recorded());

        $this->post(route('dota.player.refresh', ['accountId' => self::ACCOUNT_ID]))
            ->assertRedirect(route('dota.player', ['accountId' => self::ACCOUNT_ID]))
            ->assertSessionHas('status');

        $this->assertFalse(Cache::has('opendota:player:v3:'.self::ACCOUNT_ID));
        $this->assertDatabaseMissing('player_profiles', ['account_id' => self::ACCOUNT_ID]);

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        // Hero/item metadata and match details stay cached; seven player endpoints are re-fetched.
        $this->assertSame($sentAfterFirstVisit + 7, count(Http::recorded()));
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

    public function test_connection_failure_returns_a_network_error_page(): void
    {
        Http::fake([
            'api.opendota.com/api/*' => Http::failedConnection('Connection timed out'),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertStatus(502);
        $response->assertViewIs('errors.api');
        $response->assertSee('Не удалось загрузить данные');
        $response->assertSee('Ошибка сети');
        $response->assertSee('Сервера OpenDota временно недоступны');
    }

    public function test_optional_endpoint_connection_failure_does_not_render_zero_stats(): void
    {
        Http::fake([
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/wl*' => Http::failedConnection('Connection timed out'),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response([
                'profile' => [
                    'account_id' => self::ACCOUNT_ID,
                    'personaname' => 'Public Player',
                    'steamid' => '76561198030654385',
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([]),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertStatus(502);
        $response->assertViewIs('errors.api');
        $response->assertSee('Сервера OpenDota временно недоступны');
    }

    public function test_profile_without_public_matches_shows_an_exposable_data_warning(): void
    {
        Http::fake([
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/wl*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/recentMatches*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/heroes*' => Http::response([
                ['hero_id' => 1, 'games' => 0, 'win' => 0],
                ['hero_id' => 2, 'games' => 0, 'win' => 0],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/totals*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response([
                'profile' => [
                    'account_id' => self::ACCOUNT_ID,
                    'personaname' => 'Public Player',
                    'steamid' => '76561198030654385',
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/apps/dota2/images/heroes/axe_full.png'],
            ]),
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Профиль игрока скрыт. Данные недоступны.');
        $response->assertDontSee('Expose Public Match Data', false);
        $response->assertSee('Матчи скрыты или отсутствуют');
        $response->assertDontSee('Anti-Mage');
        $response->assertViewHas('player', fn (array $player) => $player['games'] === 0
            && $player['heroes'] === []
            && $player['dota_data_hidden'] === true);
    }

    public function test_steam_profile_tab_shows_account_and_public_privacy_status(): void
    {
        config(['services.steam.api_key' => 'test-key']);

        $this->fakeOpenDota([
            'steamid' => '76561198030654385',
            'personaname' => 'Dendi',
            'realname' => 'Danil Ishutin',
            'avatarfull' => 'https://example.com/steam-avatar.jpg',
            'profileurl' => 'https://steamcommunity.com/id/DendiQ/',
            'communityvisibilitystate' => 3,
            'personastate' => 1,
            'timecreated' => 1173974400,
            'lastlogoff' => now()->subHours(2)->timestamp,
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Публичный');
        $response->assertSee('Публичные матчи доступны');
        $response->assertSee('Регистрация 15.03.2007');
        $response->assertDontSee('Последний вход');
        $response->assertViewHas('steamProfile', fn (array $profile) => $profile['available'] === true
            && $profile['is_public'] === true
            && $profile['visibility'] === 'Публичный'
            && $profile['last_logoff_at'] !== null);
    }

    public function test_private_steam_community_is_reported(): void
    {
        config(['services.steam.api_key' => 'test-key']);

        $this->fakeOpenDota([
            'steamid' => '76561198030654385',
            'personaname' => 'Dendi',
            'communityvisibilitystate' => 1,
            'personastate' => 0,
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Приватный');
        $response->assertViewHas('steamProfile', fn (array $profile) => $profile['available'] === true
            && $profile['is_public'] === false
            && $profile['visibility'] === 'Приватный');
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

    public function test_account_metadata_is_rendered_in_the_header_without_an_account_tab(): void
    {
        config(['services.steam.api_key' => 'test-key']);

        $this->fakeOpenDota([
            'steamid' => '76561198030654385',
            'personaname' => 'Dendi',
            'profileurl' => 'https://steamcommunity.com/profiles/76561198030654385',
            'avatarfull' => 'https://example.com/dendi.jpg',
            'communityvisibilitystate' => 3,
            'timecreated' => 946684800,
            'lastlogoff' => 1700000000,
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertDontSee('id="tab-account"', false);
        $response->assertSee('Dota ID '.self::ACCOUNT_ID);
        $response->assertSee('SteamID64 76561198030654385');
        $response->assertSee('Регистрация 01.01.2000');
        $response->assertDontSee('VAC-бан');
        $response->assertDontSee('Game Ban');
    }

    public function test_name_history_records_the_previous_nickname(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        $record = PlayerProfile::findOrFail(self::ACCOUNT_ID);
        $data = $record->data;
        $data['schema_version'] = 1;
        $record->data = $data;
        $record->nickname = 'Dendi Prime';
        $record->updated_at = CarbonImmutable::now()->subHours(2);
        $record->save();
        Cache::flush();

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Dendi Prime');
        $this->assertDatabaseHas('player_name_history', [
            'account_id' => self::ACCOUNT_ID,
            'name' => 'Dendi Prime',
        ]);
        $response->assertViewHas('player', function (array $player) {
            return count($player['name_history']) === 1
                && $player['name_history'][0]['name'] === 'Dendi Prime';
        });
    }

    public function test_mmr_is_estimated_from_rank_and_recent_ranked_games(): void
    {
        $this->fakeOpenDota(profileOverrides: [
            'rank_tier' => 52,
            'mmr_estimate' => null,
        ]);

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('3 184 MMR');
        $response->assertViewHas('player', fn (array $player) => $player['mmr_estimate'] === 3184
            && $player['mmr_is_estimated'] === true
            && $player['mmr_games_considered'] === 4);
    }

    public function test_mmr_table_uses_official_star_values(): void
    {
        $service = $this->app->make(OpenDotaService::class);
        $method = new \ReflectionMethod($service, 'baseMmr');

        $expected = [
            11 => 0,
            12 => 154,
            15 => 616,
            22 => 924,
            25 => 1386,
            33 => 1848,
            42 => 2464,
            52 => 3234,
            55 => 3696,
            61 => 3850,
            65 => 4466,
            71 => 4620,
            72 => 4820,
            75 => 5420,
            80 => 5420,
        ];

        foreach ($expected as $rankTier => $mmr) {
            $this->assertSame($mmr, $method->invoke($service, $rankTier), "Rank tier {$rankTier}");
        }
    }

    public function test_hero_stats_fall_back_to_stratz_when_opendota_returns_empty(): void
    {
        config(['services.stratz.token' => 'test-token']);

        $this->fakeOpenDota(
            heroesResponse: [],
            stratzHeroes: [
                ['heroId' => 2, 'gameCount' => 15, 'winCount' => 9],
                ['heroId' => 1, 'gameCount' => 20, 'winCount' => 11],
            ],
        );

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Anti-Mage');
        $response->assertViewHas('player', fn (array $player) => count($player['heroes']) === 2
            && $player['heroes'][0]['hero_id'] === 1
            && $player['heroes'][0]['games'] === 20);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token')
            && str_contains($request->body(), 'heroId'));
    }

    public function test_heroes_page_renders_full_hero_table(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player.heroes', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertViewIs('dota.heroes');
        $response->assertSee('Anti-Mage');
        $response->assertSee('Союзники');
    }

    public function test_peers_page_renders_teammates_table(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player.peers', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertViewIs('dota.peers');
        $response->assertSee('Teammate');
        $response->assertSee('Скрытый профиль');
    }

    private function fakeOpenDota(
        ?array $steamPlayer = null,
        ?array $steamBans = null,
        ?string $nickname = null,
        array $profileOverrides = [],
        ?array $heroesResponse = null,
        ?array $stratzHeroes = null,
    ): void {
        $profileResponse = array_merge([
            'profile' => [
                'account_id' => self::ACCOUNT_ID,
                'personaname' => $nickname ?? 'Dendi',
                'avatarfull' => 'https://example.com/dendi.jpg',
                'steamid' => '76561198030654385',
                'profileurl' => 'https://steamcommunity.com/profiles/76561198030654385',
                'loccountrycode' => 'UA',
            ],
            'rank_tier' => 80,
            'leaderboard_rank' => 120,
            'mmr_estimate' => ['estimate' => 9120],
        ], $profileOverrides);

        $fakes = [
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
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/heroes*' => Http::response(
                $heroesResponse ?? [
                    ['hero_id' => 1, 'games' => 12, 'win' => 8, 'last_played' => 1700000000],
                    ['hero_id' => 2, 'games' => 7, 'win' => 2, 'last_played' => 1699900000],
                ]
            ),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/totals*' => Http::response([
                ['field' => 'kills', 'n' => 10, 'sum' => 87],
                ['field' => 'deaths', 'n' => 10, 'sum' => 52],
                ['field' => 'assists', 'n' => 10, 'sum' => 101],
                ['field' => 'gold_per_min', 'n' => 10, 'sum' => 4980],
                ['field' => 'xp_per_min', 'n' => 10, 'sum' => 5610],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/matches*' => Http::response([
                ['match_id' => 9000000001, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => now()->subDays(1)->timestamp, 'duration' => 2400, 'kills' => 10, 'deaths' => 2, 'assists' => 15, 'gold_per_min' => 620, 'xp_per_min' => 710, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 0],
                ['match_id' => 9000000002, 'hero_id' => 2, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => now()->subDays(2)->timestamp, 'duration' => 1980, 'kills' => 3, 'deaths' => 8, 'assists' => 6, 'gold_per_min' => 340, 'xp_per_min' => 420, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 42],
                ['match_id' => 9000000003, 'hero_id' => 3, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => now()->subDays(3)->timestamp, 'duration' => 2100, 'kills' => 8, 'deaths' => 4, 'assists' => 11, 'gold_per_min' => 510, 'xp_per_min' => 590, 'lobby_type' => 1, 'game_mode' => 1, 'party_id' => 0],
                ['match_id' => 9000000004, 'hero_id' => 4, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => now()->subDays(4)->timestamp, 'duration' => 2600, 'kills' => 12, 'deaths' => 6, 'assists' => 9, 'gold_per_min' => 580, 'xp_per_min' => 640, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 42],
                ['match_id' => 9000000005, 'hero_id' => 5, 'player_slot' => 128, 'radiant_win' => false, 'start_time' => now()->subDays(4)->timestamp, 'duration' => 1750, 'kills' => 4, 'deaths' => 9, 'assists' => 7, 'gold_per_min' => 390, 'xp_per_min' => 450, 'lobby_type' => 1, 'game_mode' => 1, 'party_id' => 0],
                ['match_id' => 9000000006, 'hero_id' => 6, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => now()->subDays(6)->timestamp, 'duration' => 2300, 'kills' => 7, 'deaths' => 5, 'assists' => 13, 'gold_per_min' => 470, 'xp_per_min' => 530, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 0],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/peers*' => Http::response([
                ['account_id' => 123456789, 'personaname' => 'Teammate', 'avatar' => 'https://example.com/peer.jpg', 'games' => 12, 'win' => 8, 'last_match_time' => now()->subDay()->timestamp],
                ['account_id' => 987654321, 'personaname' => 'OtherPeer', 'games' => 5, 'win' => 2, 'profile' => null],
            ]),
            'api.opendota.com/api/constants/items*' => Http::response([
                'blink' => ['id' => 1, 'dname' => 'Blink Dagger', 'img' => '/apps/dota2/images/dota_react/items/blink.png', 'cost' => 2250],
                'black_king_bar' => ['id' => 2, 'dname' => 'Black King Bar', 'img' => '/apps/dota2/images/dota_react/items/black_king_bar.png', 'cost' => 4050],
                'power_treads' => ['id' => 3, 'dname' => 'Power Treads', 'img' => '/apps/dota2/images/dota_react/items/power_treads.png', 'cost' => 1400],
            ]),
            'api.opendota.com/api/matches/9000000001*' => Http::response([
                'match_id' => 9000000001,
                'players' => [
                    ['account_id' => 123456789, 'hero_id' => 2],
                    [
                        'account_id' => self::ACCOUNT_ID,
                        'hero_id' => 1,
                        'level' => 27,
                        'net_worth' => 31200,
                        'gold_per_min' => 640,
                        'xp_per_min' => 720,
                        'item_0' => 1,
                        'item_1' => 2,
                        'item_2' => 3,
                        'item_3' => 0,
                        'item_4' => 0,
                        'item_5' => 0,
                    ],
                ],
            ]),
            'api.opendota.com/api/matches/9000000002*' => Http::response([
                'match_id' => 9000000002,
                'players' => [
                    [
                        'account_id' => self::ACCOUNT_ID,
                        'hero_id' => 2,
                        'level' => 18,
                        'net_worth' => 14500,
                        'gold_per_min' => 350,
                        'xp_per_min' => 430,
                        'item_0' => 3,
                        'item_1' => 1,
                        'item_2' => 0,
                        'item_3' => 0,
                        'item_4' => 0,
                        'item_5' => 0,
                    ],
                ],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response($profileResponse),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/apps/dota2/images/heroes/axe_full.png'],
                ['id' => 3, 'localized_name' => 'Bane', 'img' => '/apps/dota2/images/heroes/bane_full.png'],
                ['id' => 4, 'localized_name' => 'Bloodseeker', 'img' => '/apps/dota2/images/heroes/bloodseeker_full.png'],
                ['id' => 5, 'localized_name' => 'Crystal Maiden', 'img' => '/apps/dota2/images/heroes/crystal_maiden_full.png'],
                ['id' => 6, 'localized_name' => 'Drow Ranger', 'img' => '/apps/dota2/images/heroes/drow_ranger_full.png'],
            ]),
        ];

        if ($steamPlayer !== null) {
            $fakes['api.steampowered.com/ISteamUser/GetPlayerSummaries/*'] = Http::response([
                'response' => ['players' => [$steamPlayer]],
            ]);
        }

        if ($steamBans !== null) {
            $fakes['api.steampowered.com/ISteamUser/GetPlayerBans/*'] = Http::response([
                'response' => ['players' => [$steamBans]],
            ]);
        }

        if ($stratzHeroes !== null) {
            $fakes['api.stratz.com/graphql'] = Http::response([
                'data' => ['player' => ['heroes' => $stratzHeroes]],
            ]);
        }

        Http::fake($fakes);
    }
}
