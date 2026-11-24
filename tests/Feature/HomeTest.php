<?php

namespace Tests\Feature;

use App\Exceptions\ApiUnavailableException;
use App\Models\PlayerSearch;
use App\Services\OpenDota\OpenDotaService;
use App\Support\SteamId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_renders_the_search_form(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('DOTAHUB');
        $response->assertSee(__('ui.home_subtitle'));
        $response->assertDontSee('Например:');
        $response->assertDontSee('70388657');
        $response->assertDontSee('Последние поиски');
        $response->assertDontSee('Дашборд');
        $response->assertViewHas('dashboard', null);
    }

    public function test_header_has_a_compact_search_that_posts_to_the_search_route(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('role="search"', false);
        $response->assertSee('id="header-search-query"', false);
        $response->assertSee('action="'.route('search').'"', false);
        $response->assertSee('name="query"', false);
        $response->assertSee(__('ui.search_player'), false);
        $response->assertSee(__('ui.search_placeholder'), false);
    }

    public function test_guest_home_does_not_show_recent_searches_even_if_they_exist(): void
    {
        PlayerSearch::track('70388657', 'Dendi', 'https://example.com/dendi.jpg');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Последние поиски');
        $response->assertDontSee('История');
        $response->assertSee('DOTAHUB');
    }

    public function test_tracking_the_same_player_increments_the_counter(): void
    {
        PlayerSearch::track('70388657', 'Dendi');
        PlayerSearch::track('70388657', 'Dendi');

        $search = PlayerSearch::query()->sole();

        $this->assertSame(2, $search->search_count);
        $this->assertSame('Dendi', $search->nickname);
    }

    public function test_authenticated_home_shows_the_dashboard_with_mmr_and_recent_matches(): void
    {
        $steamId = '76561198030654385';
        $accountId = SteamId::accountIdFromSteamId64($steamId);

        $this->mock(OpenDotaService::class, function ($mock) use ($accountId): void {
            $mock->shouldReceive('player')
                ->once()
                ->with($accountId)
                ->andReturn([
                    'nickname' => 'Dendi',
                    'avatar' => 'https://example.com/dendi.jpg',
                    'mmr_estimate' => 5290,
                    'mmr_is_estimated' => true,
                    'rank_label' => 'Immortal',
                    'matches' => [
                        [
                            'match_id' => 9000000001,
                            'hero_name' => 'Anti-Mage',
                            'hero_image' => 'https://example.com/am.png',
                            'win' => true,
                            'kills' => 10,
                            'deaths' => 2,
                            'assists' => 15,
                            'duration_label' => '40:00',
                        ],
                        [
                            'match_id' => 9000000002,
                            'hero_name' => 'Axe',
                            'hero_image' => 'https://example.com/axe.png',
                            'win' => false,
                            'kills' => 3,
                            'deaths' => 8,
                            'assists' => 6,
                            'duration_label' => '33:00',
                        ],
                        [
                            'match_id' => 9000000003,
                            'hero_name' => 'Bane',
                            'hero_image' => 'https://example.com/bane.png',
                            'win' => true,
                            'kills' => 8,
                            'deaths' => 4,
                            'assists' => 11,
                            'duration_label' => '35:00',
                        ],
                        [
                            'match_id' => 9000000004,
                            'hero_name' => 'Pudge',
                            'hero_image' => 'https://example.com/pudge.png',
                            'win' => false,
                            'kills' => 1,
                            'deaths' => 9,
                            'assists' => 2,
                            'duration_label' => '29:00',
                        ],
                    ],
                ]);
        });

        $response = $this->withSession([
            'steam_id' => $steamId,
            'steam_nickname' => 'Dendi Session',
            'steam_avatar' => 'https://example.com/session.jpg',
        ])->get(route('home'));

        $response->assertOk();
        $response->assertSee('С возвращением, Dendi');
        $response->assertSee('5 290');
        $response->assertSee('Последние матчи');
        $response->assertSee('Anti-Mage');
        $response->assertSee('Axe');
        $response->assertSee('Bane');
        $response->assertDontSee('Pudge');
        $response->assertSee(route('player', ['accountId' => $accountId]), false);
        $response->assertViewHas('dashboard', fn (?array $dashboard) => $dashboard !== null
            && $dashboard['available'] === true
            && count($dashboard['recent_matches']) === 3);
    }

    public function test_authenticated_home_falls_back_when_profile_data_is_unavailable(): void
    {
        $steamId = '76561198030654385';
        $accountId = SteamId::accountIdFromSteamId64($steamId);

        $this->mock(OpenDotaService::class, function ($mock) use ($accountId): void {
            $mock->shouldReceive('player')
                ->once()
                ->with($accountId)
                ->andThrow(new ApiUnavailableException('offline'));
        });

        $response = $this->withSession([
            'steam_id' => $steamId,
            'steam_nickname' => 'Dendi Session',
            'steam_avatar' => 'https://example.com/session.jpg',
        ])->get(route('home'));

        $response->assertOk();
        $response->assertSee('С возвращением, Dendi Session');
        $response->assertSee('Не удалось загрузить профиль сейчас', false);
        $response->assertSee('name="query"', false);
        $response->assertViewHas('dashboard', fn (?array $dashboard) => $dashboard !== null
            && $dashboard['available'] === false
            && $dashboard['recent_matches'] === []);
    }
}
