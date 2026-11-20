<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LifetimeStatsTest extends TestCase
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

    public function test_overview_renders_lifetime_stats_grouped_by_lobby_mode_and_faction(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Lifetime Stats');
        $response->assertSee('По типу игры');
        $response->assertSee('По режиму');
        $response->assertSee('По фракции');
        $response->assertSee('Ranked');
        $response->assertSee('Turbo');
        $response->assertSee('Radiant');
        $response->assertSee('Dire');
        $response->assertViewHas('lifetimeStats', function (array $stats): bool {
            return $stats['total'] === 4
                && $stats['wins'] === 2
                && $stats['losses'] === 2
                && count($stats['groups']) === 3
                && collect($stats['groups'][2]['rows'])->pluck('label')->all() === ['Radiant', 'Dire'];
        });
    }

    public function test_lifetime_stats_do_not_trigger_extra_api_calls(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();
        $sentAfterFirstVisit = count(Http::recorded());

        $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        $this->assertSame($sentAfterFirstVisit, count(Http::recorded()));
    }

    public function test_records_page_renders_tiles_with_hero_and_date(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertViewIs('dota.records');
        $response->assertSee('id="tab-overview"', false);
        $response->assertSee('id="tab-records"', false);
        $response->assertSee('Рекорды');
        $response->assertSee('Все матчи');
        $response->assertSee('Рейтинг');
        $response->assertDontSee('Только рейтинг');
        $response->assertSee('x-data="{ tab: \'all\' }"', false);
        $response->assertSee('x-show="tab === \'all\'"', false);
        $response->assertSee('x-show="tab === \'ranked\'"', false);
        $response->assertViewHas('rankedRecords');
        $response->assertDontSee('lobby_type 7');
        $response->assertDontSee('Учитываются только рейтинговые матчи');
        $response->assertSee('Самый длинный матч');
        $response->assertSee('Самое большое количество убийств');
        $response->assertSee('Самое большое количество ассистов');
        $response->assertSee('Максимум GPM');
        $response->assertSee('Максимум XPM');
        $response->assertSee('Максимум урона по героям');
        $response->assertSee('Максимум урона по зданиям');
        $response->assertSee('Anti-Mage');
        $response->assertSee('15.11.2026');
        $response->assertViewHas('records', function (array $records): bool {
            $keys = array_column($records, 'key');

            return count($records) >= 16
                && in_array('DURATION', $keys, true)
                && in_array('KILLS', $keys, true)
                && in_array('ASSISTS', $keys, true)
                && in_array('GPM', $keys, true)
                && in_array('XPM', $keys, true)
                && in_array('HERO_DAMAGE', $keys, true)
                && in_array('TOWER_DAMAGE', $keys, true);
        });
    }

    public function test_records_fall_back_to_match_history_when_endpoint_is_empty(): void
    {
        $this->fakeOpenDota([]);

        $response = $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Самый длинный матч');
        $response->assertSee('Самый короткий матч');
        $response->assertSee('Самое большое количество убийств');
        $response->assertSee('Максимум GPM');
        $response->assertSee('Максимум урона по героям');
        $response->assertSee('Самая длинная серия поражений');
        $response->assertViewHas('records', function (array $records): bool {
            return count($records) >= 12
                && collect($records)->every(fn (array $record) => $record['played_at'] !== null
                    && $record['match_id'] !== null);
        });
    }

    public function test_records_derive_damage_tiles_and_streaks_from_the_cached_history(): void
    {
        $this->fakeOpenDota([]);

        $response = $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Максимум лечения');
        $response->assertSee('Максимум урона по зданиям');
        $response->assertSee('Самая длинная серия побед');
        $response->assertViewHas('records', function (array $records): bool {
            $byKey = collect($records)->keyBy('key');

            return $byKey['SHORTEST_DURATION']['value'] === 1500
                && $byKey['SHORTEST_DURATION']['value_label'] === '25:00'
                && $byKey['HERO_DAMAGE']['value'] === 24500
                && $byKey['TOWER_DAMAGE']['value'] === 1200
                && $byKey['HERO_HEALING']['value'] === 800
                && $byKey['LONGEST_WIN_STREAK']['value'] === 1
                && $byKey['LONGEST_LOSS_STREAK']['value'] === 2
                && $byKey['LONGEST_LOSS_STREAK']['value_label'] === '2 поражения';
        });
    }

    public function test_records_page_loads_both_tab_payloads_without_a_second_request(): void
    {
        $this->fakeOpenDota([]);

        $response = $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Все матчи');
        $response->assertSee('Рейтинг');
        $response->assertDontSee('Только рейтинг');
        $response->assertDontSee('lobby_type 7');
        $response->assertDontSee('Учитываются только рейтинговые матчи');

        // Both panels are rendered in the initial HTML; Alpine only toggles
        // visibility, so clicking a tab never reopens the records endpoint.
        $this->assertSame(2, substr_count((string) $response->getContent(), 'Самый длинный матч'));

        $response->assertViewHas('records', function (array $records): bool {
            $byKey = collect($records)->keyBy('key');

            return $byKey['DURATION']['value'] === 2400
                && $byKey['SHORTEST_DURATION']['value'] === 1500;
        });
        $response->assertViewHas('rankedRecords', function (array $records): bool {
            $byKey = collect($records)->keyBy('key');

            return $byKey['DURATION']['value'] === 2400
                && $byKey['SHORTEST_DURATION']['value'] === 1980
                && $byKey['KILLS']['value'] === 10
                && $byKey['LONGEST_LOSS_STREAK']['value'] === 1;
        });
    }

    public function test_records_tab_links_to_the_records_page(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('dota.player', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertSee('Рекорды');
    }

    public function test_records_are_cached_without_a_second_upstream_request(): void
    {
        $this->fakeOpenDota();

        $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]))->assertOk();

        Http::fake();
        $response = $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertViewHas('records', fn (array $records) => $records !== []);
        Http::assertNothingSent();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $records
     */
    private function fakeOpenDota(?array $records = null): void
    {
        $accountId = self::ACCOUNT_ID;
        $start = CarbonImmutable::parse('2026-11-15 12:00:00')->timestamp;

        $history = [
            ['match_id' => 9100000001, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => $start, 'duration' => 2400, 'kills' => 10, 'deaths' => 2, 'assists' => 15, 'gold_per_min' => 620, 'xp_per_min' => 710, 'last_hits' => 210, 'denies' => 12, 'gold' => 19800, 'hero_damage' => 24500, 'tower_damage' => 1200, 'hero_healing' => 800, 'level' => 27, 'lobby_type' => 7, 'game_mode' => 22],
            ['match_id' => 9100000002, 'hero_id' => 2, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => $start - 86400, 'duration' => 1980, 'kills' => 3, 'deaths' => 8, 'assists' => 6, 'gold_per_min' => 340, 'xp_per_min' => 420, 'last_hits' => 95, 'denies' => 8, 'gold' => 7600, 'hero_damage' => 9800, 'tower_damage' => 600, 'hero_healing' => 300, 'level' => 18, 'lobby_type' => 7, 'game_mode' => 22],
            ['match_id' => 9100000003, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => false, 'start_time' => $start - 172800, 'duration' => 1500, 'kills' => 4, 'deaths' => 9, 'assists' => 7, 'gold_per_min' => 390, 'xp_per_min' => 450, 'last_hits' => 110, 'denies' => 5, 'gold' => 8500, 'hero_damage' => 11000, 'tower_damage' => 350, 'hero_healing' => 150, 'level' => 20, 'lobby_type' => 0, 'game_mode' => 23],
            ['match_id' => 9100000004, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => $start - 259200, 'duration' => 2100, 'kills' => 8, 'deaths' => 4, 'assists' => 11, 'gold_per_min' => 510, 'xp_per_min' => 590, 'last_hits' => 175, 'denies' => 10, 'gold' => 15200, 'hero_damage' => 18200, 'tower_damage' => 900, 'hero_healing' => 500, 'level' => 24, 'lobby_type' => 0, 'game_mode' => 23],
        ];

        $records = $records ?? [
            ['name' => 'DURATION', 'value' => 3120, 'hero_id' => 1, 'start_time' => $start, 'match_id' => 9100000001],
            ['name' => 'KILLS', 'value' => 30, 'hero_id' => 1, 'start_time' => $start, 'match_id' => 9100000001],
            ['name' => 'GPM', 'value' => 812, 'hero_id' => 2, 'start_time' => $start - 86400, 'match_id' => 9100000002],
        ];

        Http::fake([
            'api.opendota.com/api/players/'.$accountId.'/wl*' => Http::response(['win' => 3, 'lose' => 1]),
            'api.opendota.com/api/players/'.$accountId.'/recentMatches*' => Http::response(array_slice($history, 0, 2)),
            'api.opendota.com/api/players/'.$accountId.'/heroes*' => Http::response([
                ['hero_id' => 1, 'games' => 3, 'win' => 2, 'last_played' => $start],
                ['hero_id' => 2, 'games' => 1, 'win' => 1, 'last_played' => $start - 86400],
            ]),
            'api.opendota.com/api/players/'.$accountId.'/totals*' => Http::response([]),
            'api.opendota.com/api/players/'.$accountId.'/matches*' => Http::response($history),
            'api.opendota.com/api/players/'.$accountId.'/peers*' => Http::response([]),
            'api.opendota.com/api/players/'.$accountId.'/records*' => Http::response($records),
            'api.opendota.com/api/constants/items*' => Http::response([]),
            'api.opendota.com/api/matches/*' => Http::response([]),
            'api.opendota.com/api/players/'.$accountId.'*' => Http::response([
                'profile' => [
                    'account_id' => $accountId,
                    'personaname' => 'Dendi',
                    'steamid' => '76561198030654385',
                    'avatarfull' => 'https://example.com/dendi.jpg',
                ],
                'rank_tier' => 80,
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png', 'icon' => '/apps/dota2/images/heroes/antimage_icon.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/apps/dota2/images/heroes/axe_full.png', 'icon' => '/apps/dota2/images/heroes/axe_icon.png'],
            ]),
        ]);
    }
}
