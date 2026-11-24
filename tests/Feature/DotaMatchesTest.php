<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DotaMatchesTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_matches_page_renders_paginated_rows_with_match_details(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('player.matches', ['accountId' => self::ACCOUNT_ID]));

        $response->assertOk();
        $response->assertDontSee('Все матчи');
        $response->assertSee('id="tab-overview"', false);
        $response->assertSee('id="tab-matches"', false);
        $response->assertSee('id="tab-heroes"', false);
        $response->assertSee('id="tab-records"', false);
        $response->assertSee('data-favorite-star', false);
        $response->assertSee('aria-current="page"', false);
        $response->assertSee('Anti-Mage');
        $response->assertSee('Blink Dagger');
        $response->assertSee('bg-yellow-500/20 text-yellow-500', false);
        $response->assertSee('All Pick');
        $response->assertSee('Ranked');
        $response->assertSee('Normal');
        $response->assertSee('Выбрать героя');
        $response->assertSee('id="filter-hero"', false);
        $response->assertSee('id="filter-hero-grid"', false);
        $response->assertSee('heroPickerHeroes', false);
        $response->assertSee('hero.icon || hero.image', false);
        $response->assertSee('Победа');
        $response->assertSee('Режим игры');
        $response->assertSee('Роль');
        $response->assertSee('Safe');
        $response->assertSee('Mid');
        $response->assertSee('Jungle');
        $response->assertDontSee('Нетворс');
        $response->assertSee('select-control', false);
        $response->assertSee('id="filter-date"', false);
        $response->assertSee('id="filter-calendar"', false);
        $response->assertSee('bg-[#1c1e26] text-white border-[#2d313e] rounded px-3 py-2', false);
        $response->assertDontSee('type="date"', false);
        $response->assertSee('Дата');
        $response->assertDontSee('id="filter-time"', false);
        $response->assertDontSee('Любое время');
        $response->assertDontSee('Сегодня');
        $response->assertDontSee('Неделя');
        $response->assertDontSee('Месяц');
        $response->assertDontSee('bg-blue-600', false);
        $response->assertDontSee('>P1<', false);
        $response->assertViewHas('matches', function (LengthAwarePaginator $matches) {
            $first = $matches->first();

            return $matches->total() === 6
                && $matches->perPage() === 25
                && $matches->count() === 6
                && $first['match_id'] === 9000000001
                && isset($first['start_time'])
                && $first['gpm'] === 640
                && $first['xpm'] === 720
                && $first['net_worth'] === 31200
                && $first['items'][0]['name'] === 'Blink Dagger'
                && $first['is_ranked'] === true
                && $first['party_label'] === 'Solo'
                && $matches->getCollection()[1]['party_label'] === 'Party'
                && $matches->getCollection()[1]['role_key'] === 'mid';
        });
        $response->assertViewHas('filters', [
            'hero_id' => 0,
            'result' => '',
            'mode' => '',
            'role' => '',
            'date' => '',
        ]);
    }

    public function test_matches_page_filters_by_hero_result_and_mode(): void
    {
        $this->fakeOpenDota();

        $heroResponse = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'hero_id' => 1,
        ]));
        $heroResponse->assertOk();
        $heroResponse->assertViewHas('matches', fn (LengthAwarePaginator $matches) => $matches->total() === 1
            && $matches->first()['hero_id'] === 1);

        $lossResponse = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'result' => 'loss',
        ]));
        $lossResponse->assertOk();
        $lossResponse->assertViewHas('matches', function (LengthAwarePaginator $matches) {
            return $matches->total() === 3
                && $matches->every(fn (array $match) => $match['win'] === false);
        });

        $rankedResponse = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'mode' => 'ranked',
        ]));
        $rankedResponse->assertOk();
        $rankedResponse->assertViewHas('matches', fn (LengthAwarePaginator $matches) => $matches->total() === 4);

        // A normal All Pick match must survive even when OpenDota reports a
        // ranked lobby_type. Mode filtering is driven by game_mode only.
        $normalResponse = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'mode' => 'normal',
        ]));
        $normalResponse->assertOk();
        $normalResponse->assertViewHas('matches', function (LengthAwarePaginator $matches) {
            return $matches->total() === 2
                && $matches->getCollection()->contains(fn (array $match) => $match['match_id'] === 9000000003
                    && $match['game_mode'] === 1);
        });

        $roleResponse = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'role' => 'mid',
        ]));
        $roleResponse->assertOk();
        $roleResponse->assertViewHas('matches', function (LengthAwarePaginator $matches) {
            return $matches->total() === 2
                && $matches->every(fn (array $match) => $match['role_key'] === 'mid');
        });
    }

    public function test_matches_page_filters_by_exact_utc_date(): void
    {
        $this->fakeOpenDota();

        $date = CarbonImmutable::now('UTC')->subDays(4)->toDateString();
        $response = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertViewHas('filters', fn (array $filters): bool => $filters['date'] === $date);
        $response->assertViewHas('matches', fn (LengthAwarePaginator $matches): bool => $matches->total() === 2);

        $invalid = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'date' => '2026-99-99',
        ]));

        $invalid->assertOk();
        $invalid->assertViewHas('filters', fn (array $filters): bool => $filters['date'] === '');
        $invalid->assertViewHas('matches', fn (LengthAwarePaginator $matches): bool => $matches->total() === 6);
    }

    public function test_matches_page_paginates_the_history(): void
    {
        $this->fakeOpenDota();

        $response = $this->get(route('player.matches', [
            'accountId' => self::ACCOUNT_ID,
            'per_page' => 5,
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertViewHas('matches', function (LengthAwarePaginator $matches) {
            return $matches->total() === 6
                && $matches->perPage() === 5
                && $matches->currentPage() === 2
                && $matches->count() === 1;
        });
        $response->assertSee('6–6 из 6');
    }

    private function fakeOpenDota(): void
    {
        Http::fake([
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/wl*' => Http::response(['win' => 120, 'lose' => 80]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/recentMatches*' => Http::response([
                ['match_id' => 9000000001, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'kills' => 10, 'deaths' => 2, 'assists' => 15, 'gold_per_min' => 620, 'xp_per_min' => 710, 'duration' => 2400, 'start_time' => 1700000000],
                ['match_id' => 9000000002, 'hero_id' => 2, 'player_slot' => 128, 'radiant_win' => true, 'kills' => 3, 'deaths' => 8, 'assists' => 6, 'gold_per_min' => 340, 'xp_per_min' => 420, 'duration' => 1980, 'start_time' => 1699900000],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/heroes*' => Http::response([
                ['hero_id' => 1, 'games' => 12, 'win' => 8, 'last_played' => 1700000000],
                ['hero_id' => 2, 'games' => 7, 'win' => 2, 'last_played' => 1699900000],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/totals*' => Http::response([]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/matches*' => Http::response([
                ['match_id' => 9000000001, 'hero_id' => 1, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => now()->subDays(1)->timestamp, 'duration' => 2400, 'kills' => 10, 'deaths' => 2, 'assists' => 15, 'gold_per_min' => 620, 'xp_per_min' => 710, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 0, 'party_size' => 1, 'lane_role' => 1],
                ['match_id' => 9000000002, 'hero_id' => 2, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => now()->subDays(2)->timestamp, 'duration' => 1980, 'kills' => 3, 'deaths' => 8, 'assists' => 6, 'gold_per_min' => 340, 'xp_per_min' => 420, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 42, 'party_size' => 2, 'lane_role' => 2],
                ['match_id' => 9000000003, 'hero_id' => 3, 'player_slot' => 0, 'radiant_win' => true, 'start_time' => now()->subDays(3)->timestamp, 'duration' => 2100, 'kills' => 8, 'deaths' => 4, 'assists' => 11, 'gold_per_min' => 510, 'xp_per_min' => 590, 'lobby_type' => 7, 'game_mode' => 1, 'party_id' => 0, 'party_size' => 1, 'lane_role' => 1],
                ['match_id' => 9000000004, 'hero_id' => 4, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => now()->subDays(4)->timestamp, 'duration' => 2600, 'kills' => 12, 'deaths' => 6, 'assists' => 9, 'gold_per_min' => 580, 'xp_per_min' => 640, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 42, 'party_size' => 2, 'lane_role' => 3],
                ['match_id' => 9000000005, 'hero_id' => 5, 'player_slot' => 128, 'radiant_win' => false, 'start_time' => now()->subDays(4)->timestamp, 'duration' => 1750, 'kills' => 4, 'deaths' => 9, 'assists' => 7, 'gold_per_min' => 390, 'xp_per_min' => 450, 'lobby_type' => 0, 'game_mode' => 1, 'party_id' => 0, 'party_size' => 1, 'lane_role' => 4],
                ['match_id' => 9000000006, 'hero_id' => 6, 'player_slot' => 128, 'radiant_win' => true, 'start_time' => now()->subDays(6)->timestamp, 'duration' => 2300, 'kills' => 7, 'deaths' => 5, 'assists' => 13, 'gold_per_min' => 470, 'xp_per_min' => 530, 'lobby_type' => 7, 'game_mode' => 22, 'party_id' => 0, 'party_size' => 1, 'lane_role' => 2],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'/peers*' => Http::response([]),
            'api.opendota.com/api/constants/items*' => Http::response([
                'blink' => ['id' => 1, 'dname' => 'Blink Dagger', 'img' => '/items/blink.png'],
                'black_king_bar' => ['id' => 2, 'dname' => 'Black King Bar', 'img' => '/items/bkb.png'],
                'power_treads' => ['id' => 3, 'dname' => 'Power Treads', 'img' => '/items/treads.png'],
            ]),
            'api.opendota.com/api/matches/9000000001*' => Http::response([
                'match_id' => 9000000001,
                'players' => [
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
                    ],
                ],
            ]),
            'api.opendota.com/api/players/'.self::ACCOUNT_ID.'*' => Http::response([
                'profile' => [
                    'account_id' => self::ACCOUNT_ID,
                    'personaname' => 'Dendi',
                    'avatarfull' => 'https://example.com/dendi.jpg',
                    'steamid' => '76561198030654385',
                ],
                'rank_tier' => 80,
                'mmr_estimate' => ['estimate' => 9120],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/heroes/antimage.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/heroes/axe.png'],
                ['id' => 3, 'localized_name' => 'Bane', 'img' => '/heroes/bane.png'],
                ['id' => 4, 'localized_name' => 'Bloodseeker', 'img' => '/heroes/bloodseeker.png'],
                ['id' => 5, 'localized_name' => 'Crystal Maiden', 'img' => '/heroes/crystal_maiden.png'],
                ['id' => 6, 'localized_name' => 'Drow Ranger', 'img' => '/heroes/drow_ranger.png'],
            ]),
        ]);
    }
}
