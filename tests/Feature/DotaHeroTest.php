<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
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
        Http::preventStrayRequests();
    }

    public function test_hero_page_renders_local_summary_and_personal_records(): void
    {
        $this->seedProfile();

        $response = $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Anti-Mage');
        $response->assertSee('Dendi');
        $response->assertSee('По сохранённой истории матчей');
        $response->assertSee('Личные рекорды');
        $response->assertDontSee('Ваш KDA на Anti-Mage');
        $response->assertSee('Максимальный GPM');
        $response->assertSee('700');
        $response->assertSee('Максимальный XPM');
        $response->assertSee('800');
        $response->assertSee('Максимальный KDA');
        $response->assertSee('12.50');
        $response->assertSee('Лучшая серия побед');
        $response->assertSee('1 побед подряд');
        $response->assertDontSee('heroWinrateChart', false);
        $response->assertDontSee('card p-4 mb-4', false);
        $response->assertDontSee(__('ui.to_profile'));
        $response->assertSee('min-h-[150px]', false);
        $response->assertSee('absolute inset-0 w-full h-full bg-cover bg-center', false);
        $response->assertSee("style=\"background-image: url('/images/heroes/antimage.png');\"", false);
        $response->assertSee('Нетворс');
        $response->assertSee('DMG');
        $response->assertSee('28 000');
        $response->assertSee('21 500');
        $response->assertSee(route('player', ['accountId' => self::ACCOUNT_ID]), false);
        $response->assertSee("onclick=\"window.location='".route('match', ['matchId' => 9100000001]).'?player_id='.self::ACCOUNT_ID."'\"", false);
        $response->assertSee("onkeydown=\"if (event.key === 'Enter') { window.location='".route('match', ['matchId' => 9100000001]).'?player_id='.self::ACCOUNT_ID."' }\"", false);
        $response->assertDontSee('data-href=', false);
        $response->assertSee('tabindex="0"', false);
        $response->assertSee('class="cursor-pointer hover:bg-ink-700/50 transition"', false);
        $response->assertSee('mt-4 mb-8 flex justify-center', false);
        $response->assertDontSee('Бенчмарки');
        $response->assertDontSee('Top 1%');

        $response->assertViewHas('accountId', self::ACCOUNT_ID);
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
            && $summary['xpm'] === 667);

        $response->assertViewHas('personalStats', fn (array $stats) => $stats['max_gpm'] === 700
            && $stats['max_xpm'] === 800
            && $stats['max_kda'] === 12.5
            && $stats['max_kills'] === 10
            && $stats['best_streak'] === 1
            && ! array_key_exists('kda_comparison', $stats));

        $response->assertViewMissing('winrateChart');
        $response->assertViewMissing('matchups');
        $response->assertDontSee(__('ui.easy_matchups'));
        $response->assertDontSee(__('ui.hard_matchups'));

        $response->assertViewHas('allMatches', fn (array $matches) => count($matches) === 3);
        $response->assertViewHas('matches', fn (LengthAwarePaginator $matches) => $matches->total() === 3
            && $matches->perPage() === 20
            && $matches->count() === 3);

        Http::assertSentCount(0);
    }

    public function test_hero_page_does_not_call_external_apis(): void
    {
        $this->seedProfile();

        $url = route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        Http::assertSentCount(0);
    }

    public function test_unknown_player_on_hero_page_returns_404(): void
    {
        $response = $this->get(route('player.hero', [
            'accountId' => 999999999,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertNotFound();
        $response->assertSee('Игрок не найден');
        Http::assertSentCount(0);
    }

    public function test_hero_page_has_no_benchmark_block(): void
    {
        $this->seedProfile();

        $response = $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertDontSee('Бенчмарки');
        $response->assertDontSee('Топ 10%');
        $response->assertDontSee('style="left:');
        $response->assertSee('Личные рекорды');
    }

    public function test_hero_page_uses_local_stats_without_open_dota(): void
    {
        $this->seedProfile();

        $response = $this->get(route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]));

        $response->assertOk();
        $response->assertSee('66.7%');
        $response->assertSee('4.64');
        $response->assertViewHas('summary', fn (array $summary) => $summary['games'] === 3
            && $summary['wins'] === 2
            && $summary['gpm'] === 583
            && $summary['xpm'] === 667);
        Http::assertSentCount(0);
    }

    public function test_hero_match_history_is_paginated(): void
    {
        $this->seedProfileWithMatches(25);

        $url = route('player.hero', [
            'accountId' => self::ACCOUNT_ID,
            'heroId' => self::HERO_ID,
        ]);

        $first = $this->get($url);

        $first->assertOk();
        $first->assertViewHas('allMatches', fn (array $matches) => count($matches) === 25);
        $first->assertViewHas('matches', fn (LengthAwarePaginator $matches) => $matches->total() === 25
            && $matches->perPage() === 20
            && $matches->currentPage() === 1
            && $matches->count() === 20);
        $first->assertSee(route('match', ['matchId' => 9200000024, 'player_id' => self::ACCOUNT_ID]), false);
        $first->assertSee("onclick=\"window.location='".route('match', ['matchId' => 9200000024]).'?player_id='.self::ACCOUNT_ID."'\"", false);
        $first->assertSee('mt-4 mb-8 flex justify-center', false);
        $first->assertSee('?page=2', false);

        $second = $this->get($url.'?page=2');

        $second->assertOk();
        $second->assertViewHas('matches', fn (LengthAwarePaginator $matches) => $matches->total() === 25
            && $matches->currentPage() === 2
            && $matches->count() === 5);
        $second->assertSee(route('match', ['matchId' => 9200000004, 'player_id' => self::ACCOUNT_ID]), false);
        $second->assertDontSee(route('match', ['matchId' => 9200000024, 'player_id' => self::ACCOUNT_ID]), false);
        Http::assertSentCount(0);
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
                'gpm' => 700,
                'xpm' => 800,
                'net_worth' => 28000,
                'hero_damage' => 21500,
                'duration' => 2700,
                'duration_label' => '45:00',
                'start_time' => now()->subDays(3)->timestamp,
            ],
            [
                'match_id' => 9100000004,
                'hero_id' => 2,
                'hero_name' => 'Axe',
                'win' => true,
                'kills' => 3,
                'deaths' => 5,
                'assists' => 4,
                'gpm' => 500,
                'xpm' => 550,
                'duration' => 1200,
                'duration_label' => '20:00',
                'start_time' => now()->timestamp,
            ],
        ];

        $recent = array_slice($history, 0, 3);

        $this->storeProfile($history, $recent);
    }

    private function seedProfileWithMatches(int $count): void
    {
        $history = [];

        for ($index = 0; $index < $count; $index++) {
            $history[] = [
                'match_id' => 9200000000 + $index,
                'hero_id' => self::HERO_ID,
                'hero_name' => 'Anti-Mage',
                'hero_image' => '/images/heroes/antimage.png',
                'hero_icon' => '/images/heroes/icons/antimage.png',
                'win' => $index % 2 === 0,
                'kills' => $index % 10,
                'deaths' => 3,
                'assists' => $index % 5,
                'gpm' => 400 + $index,
                'xpm' => 500,
                'duration' => 1800 + $index,
                'duration_label' => '30:00',
                'start_time' => now()->subDays($count - $index)->timestamp,
            ];
        }

        $this->storeProfile($history, array_slice($history, 0, 5));
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $recent
     */
    private function storeProfile(array $history, array $recent): void
    {
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
