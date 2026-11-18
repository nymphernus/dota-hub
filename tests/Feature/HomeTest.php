<?php

namespace Tests\Feature;

use App\Models\PlayerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_the_dota_search_form(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Stats Radar');
        $response->assertSee('Dota 2');
        $response->assertDontSee('CS2');
    }

    public function test_home_page_lists_recent_searches(): void
    {
        PlayerSearch::track('70388657', 'Dendi', 'https://example.com/dendi.jpg');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Dendi');
        $response->assertViewHas('recentSearches', fn ($searches) => $searches->count() === 1);
    }

    public function test_tracking_the_same_player_increments_the_counter(): void
    {
        PlayerSearch::track('70388657', 'Dendi');
        PlayerSearch::track('70388657', 'Dendi');

        $search = PlayerSearch::query()->sole();

        $this->assertSame(2, $search->search_count);
        $this->assertSame('Dendi', $search->nickname);
    }

    public function test_recent_searches_are_limited_to_five_players(): void
    {
        foreach ([1001, 1002, 1003, 1004, 1005, 1006] as $accountId) {
            PlayerSearch::track((string) $accountId, 'Player '.$accountId);
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas('recentSearches', fn ($searches) => $searches->count() === 5);
        $response->assertSee('Player 1006');
        $response->assertSee('Player 1002');
        $response->assertDontSee('Player 1001');
    }
}
