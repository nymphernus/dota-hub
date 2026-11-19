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

        $cacheKey = "stratz:player:{$accountId}:heroes:v2";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        // STRATZ has shipped both field names. Ask for the documented
        // matchCount first and fall back to the older gameCount schema.
        $queries = [
            'matchCount' => sprintf(
                'query { player(steamAccountId: %d) { heroes(take: 20) { heroId matchCount winCount } } }',
                $accountId
            ),
            'gameCount' => sprintf(
                'query { player(steamAccountId: %d) { heroes(take: 20) { heroId gameCount winCount } } }',
                $accountId
            ),
        ];

        $payload = null;
        $errors = [];

        foreach ($queries as $countField => $query) {
            try {
                $response = $this->http
                    ->withToken((string) config('services.stratz.token'))
                    ->withHeaders(['User-Agent' => 'Stats Radar/1.0'])
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

        $rawHeroes = $payload['data']['player']['heroes'] ?? null;

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
     * @return array<int, array{position: int|null, imp: float|null}>
     */
    public function matchPositions(int $matchId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $cacheKey = "stratz:match:{$matchId}:positions";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->http
                ->withToken((string) config('services.stratz.token'))
                ->withHeaders(['User-Agent' => 'Stats Radar/1.0'])
                ->acceptJson()
                ->timeout(10)
                ->post((string) config('services.stratz.endpoint', 'https://api.stratz.com/graphql'), [
                    'query' => <<<'GRAPHQL'
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
            Log::warning('STRATZ returned an invalid response', [
                'match_id' => $matchId,
                'errors' => is_array($payload['errors'] ?? null) ? $payload['errors'] : null,
            ]);

            return [];
        }

        $players = $payload['data']['match']['players'] ?? null;

        if (! is_array($players)) {
            return [];
        }

        $positions = [];

        foreach ($players as $player) {
            if (! is_array($player) || empty($player['steamAccountId'])) {
                continue;
            }

            $accountId = (int) $player['steamAccountId'];
            $position = isset($player['position']) && is_numeric($player['position'])
                ? (int) $player['position']
                : null;
            $imp = isset($player['imp']) && is_numeric($player['imp'])
                ? round((float) $player['imp'], 2)
                : null;

            if ($position === null && $imp === null) {
                continue;
            }

            $positions[$accountId] = [
                'position' => $position,
                'imp' => $imp,
            ];
        }

        Cache::put($cacheKey, $positions, (int) config('stats.cache.dota_match', 3600));

        return $positions;
    }
}
