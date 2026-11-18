<?php

namespace Tests\Feature;

use App\Models\PlayerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_the_search_form(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Stats Radar');
        $response->assertSee('Dota 2');
        $response->assertSee('CS2');
    }

    public function test_home_page_lists_recent_searches(): void
    {
        PlayerSearch::track(PlayerSearch::GAME_DOTA, '70388657', 'Dendi', 'https://example.com/dendi.jpg');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Dendi');
        $response->assertViewHas('recentSearches', fn ($searches) => $searches->count() === 1);
    }

    public function test_tracking_the_same_player_increments_the_counter(): void
    {
        PlayerSearch::track(PlayerSearch::GAME_DOTA, '70388657', 'Dendi');
        PlayerSearch::track(PlayerSearch::GAME_DOTA, '70388657', 'Dendi');

        $search = PlayerSearch::query()->sole();

        $this->assertSame(2, $search->search_count);
        $this->assertSame('Dendi', $search->nickname);
    }
}
