<?php

namespace Tests\Feature;

use App\Services\Stratz\StratzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function test_player_matches_pages_through_every_batch_without_a_cap(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        $batch = static fn (int $firstId, int $count): array => array_map(
            static fn (int $id): array => [
                'id' => $id,
                'startDateTime' => 1700000000 + $id,
                'gameMode' => 'ALL_PICK',
                'lobbyType' => 'RANKED',
                'players' => [
                    ['heroId' => 1, 'isRadiant' => true, 'isVictory' => true, 'position' => 2],
                ],
            ],
            range($firstId, $firstId + $count - 1)
        );

        Http::fake([
            'api.stratz.com/graphql' => Http::sequence()
                ->push(['data' => ['player' => ['matches' => $batch(1, 100)]]])
                ->push(['data' => ['player' => ['matches' => $batch(101, 25)]]]),
        ]);

        $rows = $this->app->make(StratzService::class)->playerMatches(70388657);

        $this->assertCount(125, $rows);
        $this->assertSame(1, $rows[0]['match_id']);
        $this->assertSame(125, $rows[124]['match_id']);
        Http::assertSentCount(2);
    }

    public function test_player_heroes_sends_graphql_query_with_bearer_token(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response([
                'data' => [
                    'player' => [
                        'heroesPerformance' => [
                            ['heroId' => 2, 'matchCount' => 5, 'winCount' => 2],
                            ['heroId' => 1, 'matchCount' => 12, 'winCount' => 8],
                            ['heroId' => 3, 'matchCount' => 0, 'winCount' => 0],
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
            && str_contains($request->body(), 'heroesPerformance(take: 20)')
            && str_contains($request->body(), 'matchCount')
            && str_contains($request->body(), '70388657'));
    }

    public function test_player_heroes_accepts_partial_data_with_errors_and_logs_the_raw_response(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response([
                'errors' => [['message' => "Cannot query field 'names' on type 'PlayerType'."]],
                'data' => ['player' => ['heroesPerformance' => [
                    ['heroId' => 128, 'matchCount' => 2, 'winCount' => 1],
                ]]],
            ]),
        ]);

        Log::shouldReceive('info')->twice()->withArgs(function (string $message, array $context): bool {
            if ($message === 'STRATZ hero response') {
                return $context['account_id'] === 70388657
                    && $context['status'] === 200
                    && str_contains($context['raw_response'], 'heroesPerformance')
                    && isset($context['json']['data']['player']['heroesPerformance']);
            }

            return $message === 'STRATZ hero rows parsed'
                && $context['rows'] === 1
                && $context['hero_ids'] === [128];
        });

        $heroes = $this->app->make(StratzService::class)->playerHeroes(70388657);

        $this->assertSame([
            ['hero_id' => 128, 'games' => 2, 'win' => 1],
        ], $heroes);
        Http::assertSentCount(1);
    }

    public function test_player_heroes_falls_back_to_the_legacy_heroes_field(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::sequence()
                ->push(['errors' => [['message' => 'Cannot query field heroesPerformance']]])
                ->push([
                    'data' => ['player' => ['heroes' => [
                        ['heroId' => 1, 'matchCount' => 9, 'winCount' => 6],
                    ]]],
                ]),
        ]);

        $service = $this->app->make(StratzService::class);

        $this->assertSame([
            ['hero_id' => 1, 'games' => 9, 'win' => 6],
        ], $service->playerHeroes(70388657));

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->body(), 'heroes(take: 20)'));
    }

    public function test_player_heroes_stops_after_a_cloudflare_response(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response('<html>Just a moment...</html>', 403),
        ]);

        $service = $this->app->make(StratzService::class);

        $this->assertSame([], $service->playerHeroes(70388657));
        Http::assertSentCount(1);
    }

    public function test_player_heroes_is_cached(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response([
                'data' => ['player' => ['heroes' => [['heroId' => 1, 'matchCount' => 4, 'winCount' => 2]]]],
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

    public function test_match_positions_parses_enum_position_and_impact(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        Http::fake([
            'api.stratz.com/graphql' => Http::response([
                'data' => [
                    'match' => [
                        'players' => [
                            [
                                'steamAccountId' => 70388657,
                                'position' => 'POSITION_2',
                                'imp' => -10,
                                'steamAccount' => ['seasonRank' => 80],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = $this->app->make(StratzService::class);

        $this->assertSame([
            70388657 => ['position' => 2, 'imp' => -10.0, 'rank_tier' => 80],
        ], $service->matchPositions(8847785956));

        Http::assertSent(fn ($request) => str_contains($request->body(), 'position')
            && str_contains($request->body(), 'imp')
            && str_contains($request->body(), '8847785956'));
    }

    public function test_debug_request_logs_the_raw_graphql_response(): void
    {
        config(['services.stratz.token' => 'test-token']);
        Cache::flush();

        $raw = '{"data":{"player":{"steamAccountId":70388657,"heroesPerformance":[{"heroId":128,"matchCount":2,"winCount":1,"imp":16}]}}}';

        Http::fake([
            'api.stratz.com/graphql' => Http::response(json_decode($raw, true), 200),
        ]);

        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) use ($raw): bool {
            return $message === 'STRATZ debugRequest raw response'
                && $context['account_id'] === 70388657
                && $context['status'] === 200
                && $context['raw_response'] === $raw
                && isset($context['json']['data']['player']['heroesPerformance']);
        });

        $result = $this->app->make(StratzService::class)->debugRequest(70388657);

        $this->assertSame(200, $result['status']);
        $this->assertSame($raw, $result['raw_response']);
        $this->assertSame(128, $result['json']['data']['player']['heroesPerformance'][0]['heroId']);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'DebugPlayer')
            && str_contains($request->body(), 'heroesPerformance')
            && str_contains($request->body(), '70388657'));
    }
}
