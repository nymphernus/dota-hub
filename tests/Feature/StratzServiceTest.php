<?php

namespace Tests\Feature;

use App\Services\Stratz\StratzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StratzServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stratz.token' => null]);
        Cache::flush();
    }

    public function test_player_heroes_sends_graphql_query_with_bearer_token(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response([
                'data' => [
                    'player' => [
                        'heroes' => [
                            ['heroId' => 2, 'gameCount' => 5, 'winCount' => 2],
                            ['heroId' => 1, 'gameCount' => 12, 'winCount' => 8],
                            ['heroId' => 3, 'gameCount' => 0, 'winCount' => 0],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = $this->app->make(StratzService::class);
        $heroes = $service->playerHeroes(70388657);

        $this->assertSame([
            ['hero_id' => 1, 'games' => 12, 'win' => 8],
            ['hero_id' => 2, 'games' => 5, 'win' => 2],
        ], $heroes);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token')
            && str_contains($request->body(), 'heroId')
            && str_contains($request->body(), 'gameCount'));
    }

    public function test_player_heroes_is_cached(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response([
                'data' => ['player' => ['heroes' => [['heroId' => 1, 'gameCount' => 4, 'winCount' => 2]]]],
            ]),
        ]);

        $service = $this->app->make(StratzService::class);
        $service->playerHeroes(70388657);
        $service->playerHeroes(70388657);

        Http::assertSentCount(1);
    }

    public function test_player_heroes_is_a_noop_without_a_token(): void
    {
        Http::fake([
            'api.stratz.com/graphql' => Http::response([]),
        ]);

        $service = $this->app->make(StratzService::class);

        $this->assertSame([], $service->playerHeroes(70388657));
        Http::assertNothingSent();
    }

    public function test_player_heroes_returns_empty_on_upstream_failure(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response(['errors' => [['message' => 'nope']]], 200),
        ]);

        $service = $this->app->make(StratzService::class);

        $this->assertSame([], $service->playerHeroes(70388657));
    }

    public function test_player_heroes_returns_empty_on_connection_error(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::failedConnection('timeout'),
        ]);

        $service = $this->app->make(StratzService::class);

        $this->assertSame([], $service->playerHeroes(70388657));
    }
}
