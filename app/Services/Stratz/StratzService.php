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
