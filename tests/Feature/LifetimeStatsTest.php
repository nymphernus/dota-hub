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
        $response->assertSee('Рекорды');
        $response->assertSee('Самый длинный матч');
        $response->assertSee('Наибольшее число убийств');
        $response->assertSee('Максимум GPM');
        $response->assertSee('Anti-Mage');
        $response->assertSee('15.11.2026');
        $response->assertViewHas('records', function (array $records): bool {
            $keys = array_column($records, 'key');

            return in_array('DURATION', $keys, true)
                && in_array('KILLS', $keys, true)
                && in_array('GPM', $keys, true);
        });
    }

    public function test_records_fall_back_to_match_history_when_endpoint_is_empty(): void
    {
        $this->fakeOpenDota([]);

        $response = $this->get(route('dota.player.records', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertSee('Самый длинный матч');
        $response->assertSee('Наибольшее число убийств');
        $response->assertSee('Максимум GPM');
        $response->assertViewHas('records', function (array $records): bool {
            return count($records) >= 4
                && collect($records)->every(fn (array $record) => $record['played_at'] !== null
                    && $record['match_id'] !== null);
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
            ['match_id' => 9100000001, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => $start, 'duration' => 2400, 'kills' => 10, 'deaths' => 2, 'assists' => 15, 'gold_per_min' => 620, 'xp_per_min' => 710, 'lobby_type' => 7, 'game_mode' => 22],
            ['match_id' => 9100000002, 'hero_id' => 2, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => $start - 86400, 'duration' => 1980, 'kills' => 3, 'deaths' => 8, 'assists' => 6, 'gold_per_min' => 340, 'xp_per_min' => 420, 'lobby_type' => 7, 'game_mode' => 22],
            ['match_id' => 9100000003, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => false, 'start_time' => $start - 172800, 'duration' => 1500, 'kills' => 4, 'deaths' => 9, 'assists' => 7, 'gold_per_min' => 390, 'xp_per_min' => 450, 'lobby_type' => 0, 'game_mode' => 23],
            ['match_id' => 9100000004, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => $start - 259200, 'duration' => 2100, 'kills' => 8, 'deaths' => 4, 'assists' => 11, 'gold_per_min' => 510, 'xp_per_min' => 590, 'lobby_type' => 0, 'game_mode' => 23],
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
