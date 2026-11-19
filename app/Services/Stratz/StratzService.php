<?php

namespace App\Services\Stratz;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Optional STRATZ GraphQL enrichment.
 *
 * OpenDota provides an estimated position and lane role, but STRATZ exposes
 * the actual 1-5 position and an impact score. When no token is configured the
 * service is a no-op, so match pages work exactly as before.
 */
class StratzService
{
    public function __construct(private readonly Factory $http) {}

    public function enabled(): bool
    {
        return filled(config('services.stratz.token'));
    }

    /**
     * Aggregated hero statistics for a player, used when OpenDota's heroes
     * endpoint no longer returns data.
     *
     * @return array<int, array{hero_id: int, games: int, win: int}>
     */
    public function playerHeroes(int $accountId): array
    {
        if (! $this->enabled() || $accountId <= 0) {
            return [];
        }

        $cacheKey = "stratz:player:{$accountId}:heroes:v3";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        // STRATZ moved hero aggregates to heroesPerformance. Keep the older
        // heroes field as a fallback for cached gateways.
        $queries = [
            'heroesPerformance' => sprintf(
                'query { player(steamAccountId: %d) { heroesPerformance(take: 20) { heroId matchCount winCount } } }',
                $accountId
            ),
            'legacyHeroes' => sprintf(
                'query { player(steamAccountId: %d) { heroes(take: 20) { heroId matchCount winCount } } }',
                $accountId
            ),
        ];

        $payload = null;
        $errors = [];

        foreach ($queries as $countField => $query) {
            try {
                $response = $this->http
                    ->withToken((string) config('services.stratz.token'))
                    ->withHeaders(['User-Agent' => 'DotaHub/1.0'])
                    ->acceptJson()
                    ->timeout(10)
                    ->post((string) config('services.stratz.endpoint', 'https://api.stratz.com/graphql'), [
                        'query' => $query,
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('STRATZ is unreachable', [
                    'account_id' => $accountId,
                    'message' => $exception->getMessage(),
                ]);

                return [];
            }

            if ($response->failed()) {
                $errors[] = [
                    'field' => $countField,
                    'status' => $response->status(),
                ];

                // A Cloudflare/rate-limit response will not improve on retry.
                if ($response->status() !== 400) {
                    break;
                }

                continue;
            }

            $candidate = $response->json();

            if (! is_array($candidate)) {
                break;
            }

            if (isset($candidate['errors'])) {
                $errors[] = [
                    'field' => $countField,
                    'errors' => $candidate['errors'],
                ];

                continue;
            }

            $payload = $candidate;

            break;
        }

        if ($payload === null) {
            Log::warning('STRATZ hero query was rejected', [
                'account_id' => $accountId,
                'attempts' => $errors,
            ]);

            return [];
        }

        $rawHeroes = $payload['data']['player']['heroesPerformance']
            ?? ($payload['data']['player']['heroes'] ?? null);

        if (! is_array($rawHeroes)) {
            return [];
        }

        $heroes = [];

        foreach ($rawHeroes as $hero) {
            if (! is_array($hero) || empty($hero['heroId'])) {
                continue;
            }

            $games = (int) ($hero['matchCount'] ?? $hero['gameCount'] ?? 0);

            if ($games <= 0) {
                continue;
            }

            $heroes[] = [
                'hero_id' => (int) $hero['heroId'],
                'games' => $games,
                'win' => (int) ($hero['winCount'] ?? 0),
            ];
        }

        usort($heroes, fn (array $left, array $right) => [$right['games'], $right['win']] <=> [$left['games'], $left['win']]);

        Cache::put(
            $cacheKey,
            $heroes,
            (int) config('stats.cache.dota_player_hero', 1800)
        );

        return $heroes;
    }

    /**
     * @return array<int, array{position: int|null, imp: float|null, rank_tier: int|null}>
     */
    public function matchPositions(int $matchId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $cacheKey = "stratz:match:{$matchId}:positions:v3";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $queries = [
            <<<'GRAPHQL'
                query MatchPlayers($matchId: Long!) {
                    match(id: $matchId) {
                        players {
                            steamAccountId
                            position
                            imp
                            steamAccount {
                                seasonRank
                            }
                        }
                    }
                }
            GRAPHQL,
            <<<'GRAPHQL'
                query MatchPlayers($matchId: Long!) {
                    match(id: $matchId) {
                        players {
                            steamAccountId
                            position
                            imp
                        }
                    }
                }
            GRAPHQL,
        ];

        $players = null;

        foreach ($queries as $query) {
            try {
                $response = $this->http
                    ->withToken((string) config('services.stratz.token'))
                    ->withHeaders(['User-Agent' => 'DotaHub/1.0'])
                    ->acceptJson()
                    ->timeout(10)
                    ->post((string) config('services.stratz.endpoint', 'https://api.stratz.com/graphql'), [
                        'query' => $query,
                        'variables' => ['matchId' => $matchId],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('STRATZ is unreachable', [
                    'match_id' => $matchId,
                    'message' => $exception->getMessage(),
                ]);

                return [];
            }

            if ($response->failed()) {
                Log::warning('STRATZ request failed', [
                    'match_id' => $matchId,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $payload = $response->json();

            if (! is_array($payload) || isset($payload['errors'])) {
                Log::info('STRATZ match query attempt failed', [
                    'match_id' => $matchId,
                    'errors' => is_array($payload['errors'] ?? null) ? $payload['errors'] : null,
                ]);

                continue;
            }

            $candidate = $payload['data']['match']['players'] ?? null;

            if (is_array($candidate)) {
                $players = $candidate;

                break;
            }
        }

        if (! is_array($players)) {
            return [];
        }

        $positions = [];

        foreach ($players as $player) {
            if (! is_array($player) || empty($player['steamAccountId'])) {
                continue;
            }

            $accountId = (int) $player['steamAccountId'];
            $positionValue = $player['position'] ?? null;
            $position = null;

            if (is_numeric($positionValue)) {
                $position = (int) $positionValue;
            } elseif (is_string($positionValue) && preg_match('/POSITION_([1-5])/', $positionValue, $matches) === 1) {
                $position = (int) $matches[1];
            }

            $imp = isset($player['imp']) && is_numeric($player['imp'])
                ? round((float) $player['imp'], 2)
                : null;
            $rankTier = null;

            $steamAccount = $player['steamAccount'] ?? null;
            if (is_array($steamAccount) && isset($steamAccount['seasonRank']) && is_numeric($steamAccount['seasonRank'])) {
                $rankTier = (int) $steamAccount['seasonRank'];
            } elseif (isset($player['rank']) && is_numeric($player['rank'])) {
                $rankTier = (int) $player['rank'];
            }

            if ($position === null && $imp === null && $rankTier === null) {
                continue;
            }

            $positions[$accountId] = [
                'position' => $position,
                'imp' => $imp,
                'rank_tier' => $rankTier,
            ];
        }

        Cache::put($cacheKey, $positions, (int) config('stats.cache.dota_match', 3600));

        return $positions;
    }

    /**
     * One-off GraphQL probe. The raw response is written to laravel.log so the
     * token, Cloudflare status and field names can be inspected from tinker.
     *
     * @return array{account_id: int, status: int, json: array<string, mixed>|null, raw_response: string}|null
     */
    public function debugRequest(int $accountId): ?array
    {
        if (! $this->enabled()) {
            Log::warning('STRATZ debugRequest skipped: no token configured', [
                'account_id' => $accountId,
            ]);

            return null;
        }

        $query = <<<'GRAPHQL'
            query DebugPlayer($accountId: Long!) {
                player(steamAccountId: $accountId) {
                    steamAccountId
                    heroesPerformance(take: 1) {
                        heroId
                        matchCount
                        winCount
                        imp
                    }
                }
            }
            GRAPHQL;

        try {
            $response = $this->http
                ->withToken((string) config('services.stratz.token'))
                ->withHeaders(['User-Agent' => 'DotaHub/1.0'])
                ->acceptJson()
                ->timeout(10)
                ->post((string) config('services.stratz.endpoint', 'https://api.stratz.com/graphql'), [
                    'query' => $query,
                    'variables' => ['accountId' => $accountId],
                ]);
        } catch (ConnectionException $exception) {
            $payload = [
                'account_id' => $accountId,
                'status' => 0,
                'json' => null,
                'raw_response' => $exception->getMessage(),
            ];

            Log::error('STRATZ debugRequest connection error', $payload);

            return $payload;
        }

        $json = $response->json();
        $payload = [
            'account_id' => $accountId,
            'status' => $response->status(),
            'json' => is_array($json) ? $json : null,
            'raw_response' => $response->body(),
        ];

        Log::info('STRATZ debugRequest raw response', $payload);

        return $payload;
    }
}
