<?php

namespace App\Services\Stratz;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        $cacheKey = "stratz:player:{$accountId}:heroes:v4";
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

        $attempts = [];

        foreach ($queries as $field => $query) {
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

            $payload = $response->json();

            // Log the unmodified payload so an empty hero pool can be traced to
            // the exact GraphQL response (including Cloudflare HTML on 403).
            Log::info('STRATZ hero response', [
                'account_id' => $accountId,
                'field' => $field,
                'status' => $response->status(),
                'json' => is_array($payload) ? $payload : null,
                'raw_response' => $response->body(),
            ]);

            if ($response->failed()) {
                $attempts[] = ['field' => $field, 'status' => $response->status()];

                // A Cloudflare/rate-limit response will not improve on retry.
                if ($response->status() !== 400) {
                    break;
                }

                continue;
            }

            if (! is_array($payload)) {
                $attempts[] = ['field' => $field, 'reason' => 'invalid_json'];

                continue;
            }

            // GraphQL returns partial data together with `errors` for optional
            // fields such as `names`. Do not discard a usable hero list because
            // of an unrelated error — that was the empty-h heroes bug.
            $rawHeroes = $payload['data']['player']['heroesPerformance']
                ?? ($payload['data']['player']['heroes'] ?? null);

            $heroes = $this->parseHeroRows($rawHeroes);

            Log::info('STRATZ hero rows parsed', [
                'account_id' => $accountId,
                'field' => $field,
                'rows' => count($heroes),
                'hero_ids' => array_column($heroes, 'hero_id'),
            ]);

            if ($heroes !== []) {
                Cache::put(
                    $cacheKey,
                    $heroes,
                    (int) config('stats.cache.dota_player_hero', 1800)
                );

                return $heroes;
            }

            $attempts[] = [
                'field' => $field,
                'errors' => $payload['errors'] ?? null,
            ];
        }

        Log::warning('STRATZ hero query returned no usable data', [
            'account_id' => $accountId,
            'attempts' => $attempts,
        ]);

        return [];
    }

    /**
     * @param  mixed  $rows
     * @return array<int, array{hero_id: int, games: int, win: int}>
     */
    private function parseHeroRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $heroes = [];

        foreach ($rows as $hero) {
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
            $position = $this->normalizePosition($player['position'] ?? null);
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

    private function normalizePosition(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/POSITION_([1-5])/', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Full match history from STRATZ (bypasses OpenDota daily limits).
     * Pages in batches of 100 until every match is loaded; maxMatches = 0 means
     * no cap. Returns rows normalized to OpenDota /players/{id}/matches shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function playerMatches(int $accountId, int $take = 100, int $maxMatches = 0, bool $fresh = false): array
    {
        if (! $this->enabled() || $accountId <= 0) {
            return [];
        }

        $cacheKey = "stratz:player:{$accountId}:matches:v2";
        $cached = $fresh ? null : Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $query = <<<'GRAPHQL'
        query GetMatches($steamAccountId: Long!, $take: Int, $skip: Int) {
          player(steamAccountId: $steamAccountId) {
            matches(request: { take: $take, skip: $skip }) {
              id
              startDateTime
              durationSeconds
              gameMode
              lobbyType
              players(steamAccountId: $steamAccountId) {
                heroId
                isRadiant
                isVictory
                kills
                deaths
                assists
                goldPerMinute
                experiencePerMinute
                networth
                level
                position
                imp
              }
            }
          }
        }
        GRAPHQL;

        $all = [];
        $seen = [];
        $skip = 0;
        $take = max(1, min(100, $take));

        while (true) {
            try {
                $response = $this->http
                    ->withToken((string) config('services.stratz.token'))
                    ->withHeaders(['User-Agent' => 'DotaHub/1.0'])
                    ->acceptJson()
                    ->timeout(20)
                    ->post((string) config('services.stratz.endpoint', 'https://api.stratz.com/graphql'), [
                        'query' => $query,
                        'variables' => [
                            'steamAccountId' => $accountId,
                            'take' => $take,
                            'skip' => $skip,
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('STRATZ match history unreachable', ['account_id' => $accountId, 'message' => $exception->getMessage()]);
                return $all;
            }

            if ($response->failed()) {
                Log::warning('STRATZ match history request failed', ['account_id' => $accountId, 'status' => $response->status()]);
                return $all;
            }

            $payload = $response->json();
            $matches = $payload['data']['player']['matches'] ?? null;
            if (! is_array($matches) || $matches === []) {
                if ($all === []) {
                    Log::info('STRATZ match history returned no data', ['account_id' => $accountId, 'errors' => $payload['errors'] ?? null]);
                }
                break;
            }

            $newInBatch = 0;

            foreach ($matches as $match) {
                $row = $this->normalizeStratzMatch($match);
                if ($row === null) {
                    continue;
                }

                $matchId = (int) ($row['match_id'] ?? 0);

                if ($matchId > 0 && isset($seen[$matchId])) {
                    continue;
                }

                if ($matchId > 0) {
                    $seen[$matchId] = true;
                }

                $all[] = $row;
                $newInBatch++;
            }

            $count = count($matches);
            $skip += $count;
            Log::info('STRATZ matches loaded', ['account_id' => $accountId, 'batch' => $count, 'total' => count($all)]);

            if ($newInBatch === 0 || $count < $take) {
                break;
            }
            if ($maxMatches > 0 && count($all) >= $maxMatches) {
                break;
            }
        }

        if ($all !== [] && ! $fresh) {
            Cache::put($cacheKey, $all, (int) config('stats.cache.dota_player', 3600));
        }

        return $all;
    }

    /**
     * @param  mixed  $match
     * @return array<string, mixed>|null
     */
    private function normalizeStratzMatch(mixed $match): ?array
    {
        if (! is_array($match) || empty($match['id'])) {
            return null;
        }

        $players = $match['players'] ?? [];
        $p = is_array($players) && isset($players[0]) && is_array($players[0]) ? $players[0] : null;
        if ($p === null) {
            return null;
        }

        $isRadiant = (bool) ($p['isRadiant'] ?? false);
        $isVictory = (bool) ($p['isVictory'] ?? false);
        $radiantWin = ($isRadiant && $isVictory) || (! $isRadiant && ! $isVictory);

        return [
            'match_id' => (int) $match['id'],
            'player_slot' => $isRadiant ? 0 : 128,
            'radiant_win' => $radiantWin,
            'duration' => (int) ($match['durationSeconds'] ?? 0),
            'game_mode' => $this->mapStratzGameMode($match['gameMode'] ?? null),
            'lobby_type' => $this->mapStratzLobbyType($match['lobbyType'] ?? null),
            'hero_id' => (int) ($p['heroId'] ?? 0),
            'start_time' => (int) ($match['startDateTime'] ?? 0),
            'kills' => (int) ($p['kills'] ?? 0),
            'deaths' => (int) ($p['deaths'] ?? 0),
            'assists' => (int) ($p['assists'] ?? 0),
            'gold_per_min' => (int) ($p['goldPerMinute'] ?? 0),
            'xp_per_min' => (int) ($p['experiencePerMinute'] ?? 0),
            'net_worth' => (int) ($p['networth'] ?? 0),
            'level' => (int) ($p['level'] ?? 0),
            'position' => $this->normalizePosition($p['position'] ?? null),
            'imp' => isset($p['imp']) && is_numeric($p['imp'])
                ? round((float) $p['imp'], 2)
                : null,
        ];
    }

    private function mapStratzGameMode(mixed $value): ?int
    {
        if (is_numeric($value)) return (int) $value;
        if (! is_string($value)) return null;
        return match ($value) {
            'NONE' => 0, 'ALL_PICK' => 1, 'CAPTAINS_MODE' => 2, 'RANDOM_DRAFT' => 3,
            'SINGLE_DRAFT' => 4, 'ALL_RANDOM' => 5, 'INTRO' => 6, 'THE_DIRETIDE' => 7,
            'REVERSE_CAPTAINS_MODE' => 8, 'THE_GREEVILING' => 9, 'TUTORIAL' => 10,
            'MID_ONLY' => 11, 'LEAST_PLAYED' => 12, 'LIMITED_HEROES' => 13,
            'COMPENDIUM_MATCHMAKING' => 14, 'CUSTOM' => 15, 'CAPTAINS_DRAFT' => 16,
            'BALANCED_DRAFT' => 17, 'ABILITY_DRAFT' => 18, 'EVENT' => 19,
            'ALL_RANDOM_DEATH_MATCH' => 20, 'SOLO_MID' => 21, 'ALL_PICK_RANKED' => 22,
            'TURBO' => 23, 'MUTATION' => 24,
            default => null,
        };
    }

    private function mapStratzLobbyType(mixed $value): ?int
    {
        if (is_numeric($value)) return (int) $value;
        if (! is_string($value)) return null;
        return match ($value) {
            'INVALID' => -1, 'UNRANKED' => 0, 'PRACTICE' => 1, 'TOURNAMENT' => 2,
            'TUTORIAL' => 3, 'COOP_VS_BOTS' => 4, 'TEAM_MATCH' => 5, 'SOLO_QUEUE' => 6,
            'RANKED' => 7, 'SOLO_MID' => 8, 'BATTLE_CUP' => 9,
            default => null,
        };
    }

    /**
     * Quick connectivity/token test requested for debugging.
     */
    public function testConnection(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.stratz.token'),
            'User-Agent' => 'STRATZ_API',
        ])->post((string) config('services.stratz.endpoint', 'https://api.stratz.com/graphql'), [
            'query' => '{ player(steamAccountId: 179749744) { steamAccountId } }',
        ]);

        $payload = [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->json(),
        ];
        Log::info('STRATZ TEST:', $payload);

        return $payload;
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
