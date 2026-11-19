<?php

namespace Tests\Feature;

use App\Services\OpenDota\OpenDotaService;
use App\Services\Steam\SteamService;
use App\Support\SteamId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guest_is_redirected_to_steam_login(): void
    {
        $this->get(route('favorites.index'))
            ->assertRedirectContains('/auth/steam');

        $this->post(route('favorites.store'), ['account_id' => 70388657])
            ->assertRedirectContains('/auth/steam');

        $this->delete(route('favorites.destroy', ['accountId' => 70388657]))
            ->assertRedirectContains('/auth/steam');
    }

    public function test_authenticated_user_can_add_and_remove_favorites(): void
    {
        $steamId = '76561198030654385';

        $this->withSession(['steam_id' => $steamId])
            ->post(route('favorites.store'), [
                'account_id' => 70388657,
                'nickname' => 'Dendi',
                'avatar' => 'https://example.com/dendi.jpg',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $index = $this->withSession(['steam_id' => $steamId])->get(route('favorites.index'));
        $index->assertOk();
        $index->assertSee('Dendi');
        $index->assertSee(route('dota.player', ['accountId' => 70388657]), false);
        $index->assertSee('Удалить');

        $this->withSession(['steam_id' => $steamId])
            ->delete(route('favorites.destroy', ['accountId' => 70388657]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->withSession(['steam_id' => $steamId])
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertDontSee('Dendi')
            ->assertSee('Вы ещё не добавили игроков в избранное', false);
    }

    public function test_player_page_shows_favorite_star_for_authenticated_user(): void
    {
        $accountId = 70388657;
        $steamId = '76561198030654385';

        $this->mock(OpenDotaService::class, function ($mock) use ($accountId): void {
            $mock->shouldReceive('player')
                ->twice()
                ->with($accountId)
                ->andReturn($this->player($accountId));
            $mock->shouldReceive('lifetimeStats')
                ->twice()
                ->with($accountId, [])
                ->andReturn(['total' => 0, 'groups' => []]);
        });

        $this->mock(SteamService::class, function ($mock): void {
            $mock->shouldReceive('summary')
                ->twice()
                ->andReturn(['available' => false]);
        });

        $response = $this->withSession(['steam_id' => $steamId])
            ->get(route('dota.player', ['accountId' => $accountId]));

        $response->assertOk();
        $response->assertSee('data-favorite-star', false);
        $response->assertSee('favoriteStar({', false);
        $response->assertSee('active: false', false);
        $response->assertDontSee('method="POST" action="'.route('favorites.store').'"', false);

        $this->withSession(['steam_id' => $steamId])
            ->post(route('favorites.store'), [
                'account_id' => $accountId,
                'nickname' => 'Dendi',
                'avatar' => 'https://example.com/dendi.jpg',
            ]);

        $response = $this->withSession(['steam_id' => $steamId])
            ->get(route('dota.player', ['accountId' => $accountId]));

        $response->assertOk();
        $response->assertSee('active: true', false);
    }

    public function test_favorite_toggle_returns_json_for_ajax_requests(): void
    {
        $steamId = '76561198030654385';

        $this->withSession(['steam_id' => $steamId])
            ->postJson(route('favorites.store'), [
                'account_id' => 70388657,
                'nickname' => 'Dendi',
                'avatar' => 'https://example.com/dendi.jpg',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'favorite' => true, 'account_id' => 70388657]);

        $this->withSession(['steam_id' => $steamId])
            ->deleteJson(route('favorites.destroy', ['accountId' => 70388657]))
            ->assertOk()
            ->assertJson(['ok' => true, 'favorite' => false, 'account_id' => 70388657]);
    }

    public function test_guest_ajax_toggle_returns_login_url_without_redirect(): void
    {
        $response = $this->postJson(route('favorites.store'), ['account_id' => 70388657])
            ->assertUnauthorized()
            ->assertJsonPath('ok', false);

        $this->assertStringContainsString('/auth/steam', (string) $response->json('login_url'));
    }

    /**
     * @return array<string, mixed>
     */
    private function player(int $accountId): array
    {
        return [
            'account_id' => $accountId,
            'steam_id' => SteamId::steamId64FromAccountId($accountId),
            'nickname' => 'Dendi',
            'avatar' => 'https://example.com/dendi.jpg',
            'profile_url' => 'https://steamcommunity.com/profiles/'.SteamId::steamId64FromAccountId($accountId),
            'country_code' => 'UA',
            'last_login_at' => null,
            'is_private' => false,
            'rank_tier' => 80,
            'rank_label' => 'Immortal',
            'rank_medal' => 'Immortal',
            'rank_stars' => null,
            'leaderboard_rank' => null,
            'mmr_estimate' => 5290,
            'mmr_is_estimated' => true,
            'mmr_source' => 'rank+recent',
            'mmr_games_considered' => 20,
            'games' => 200,
            'wins' => 120,
            'losses' => 80,
            'winrate' => 60.0,
            'averages' => ['kills' => 8.7, 'deaths' => 5.2, 'assists' => 10.1, 'gpm' => 498, 'xpm' => 561, 'kda' => 3.62],
            'heroes' => [],
            'top_heroes' => [],
            'matches' => [],
            'match_history' => [],
            'peers' => [],
            'has_public_matches' => false,
            'cached_at' => now()->toIso8601String(),
            'cache_ttl' => 3600,
            'is_offline_cache' => false,
            'name_history' => [],
        ];
    }
}
