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

    public function test_recent_searches_are_limited_and_deduplicated_by_account(): void
    {
        // Same account in both games must occupy a single slot.
        PlayerSearch::track(PlayerSearch::GAME_DOTA, '70388657', 'Dendi');
        PlayerSearch::track(PlayerSearch::GAME_CS2, '70388657', 'Dendi on Steam');

        foreach ([1001, 1002, 1003, 1004, 1005, 1006] as $accountId) {
            PlayerSearch::track(PlayerSearch::GAME_DOTA, (string) $accountId, 'Player '.$accountId);
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas('recentSearches', fn ($searches) => $searches->count() === 5);
        $response->assertSee('Player 1006');
        $response->assertSee('Player 1002');
        $response->assertDontSee('Player 1001');
        $response->assertDontSee('Dendi on Steam');
    }
}
