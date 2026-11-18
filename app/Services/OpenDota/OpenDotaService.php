<?php

namespace App\Services\OpenDota;

use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds the view models used by the Dota 2 profile page.
 *
 * Each player is cached as a single aggregate entry, so rendering a profile
 * normally costs zero upstream requests. The "refresh" action forgets exactly
 * one key and rebuilds it on the next request.
 */
class OpenDotaService
{
    private const HERO_STATS_CACHE_KEY = 'opendota:hero-stats:v1';

    private const RANK_MEDALS = [
        1 => 'Herald',
        2 => 'Guardian',
        3 => 'Crusader',
        4 => 'Archon',
        5 => 'Legend',
        6 => 'Ancient',
        7 => 'Divine',
        8 => 'Immortal',
    ];

    public function __construct(private readonly OpenDotaClient $client) {}

    /**
     * Full cached player profile.
     *
     * @return array<string, mixed>
     */
    public function player(int $accountId): array
    {
        $ttl = (int) config('stats.cache.dota_profile', 1800);

        return Cache::remember($this->cacheKey($accountId), $ttl, function () use ($accountId, $ttl) {
            return $this->fetchPlayer($accountId, $ttl);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPlayer(int $accountId, int $ttl): array
    {
        $profileResponse = $this->client->get("players/{$accountId}");

        $profile = $profileResponse['profile'] ?? null;

        if (! is_array($profile) || empty($profile['account_id'])) {
            throw new PlayerNotFoundException('dota');
        }

        // Optional endpoints. A failure here must not hide the profile itself,
        // but rate limit errors do bubble up from the client.
        $winLoss = $this->optionalGet('players/'.$accountId.'/wl');
        $recentMatches = $this->optionalGet('players/'.$accountId.'/recentMatches');
        $heroStats = $this->optionalGet('players/'.$accountId.'/heroes');
        $totals = $this->optionalGet('players/'.$accountId.'/totals');

        $heroes = $this->heroStats();
        $matches = $this->transformMatches($recentMatches, $heroes);
        $totalsByField = $this->indexTotals($totals);

        $wins = (int) ($winLoss['win'] ?? 0);
        $losses = (int) ($winLoss['lose'] ?? 0);

        if ($wins === 0 && $losses === 0 && $matches !== []) {
            $wins = count(array_filter($matches, fn (array $match) => $match['win']));
            $losses = count($matches) - $wins;
        }

        $games = $wins + $losses;
        $averages = $this->averages($totalsByField, $matches);

        $rankTier = isset($profileResponse['rank_tier']) && is_numeric($profileResponse['rank_tier'])
            ? (int) $profileResponse['rank_tier']
            : null;

        $rank = $this->rank($rankTier);

        $nickname = $profile['personaname'] ?? null;

        return [
            'account_id' => (int) $profile['account_id'],
            'steam_id' => (string) ($profile['steamid'] ?? ''),
            'nickname' => $nickname ?: 'Анонимный игрок',
            'avatar' => $profile['avatarfull'] ?? $profile['avatarmedium'] ?? $profile['avatar'] ?? null,
            'profile_url' => $profile['profileurl'] ?? null,
            'country_code' => $profile['loccountrycode'] ?? null,
            'is_private' => empty($nickname),
            'rank_tier' => $rankTier,
            'rank_label' => $rank['label'],
            'rank_medal' => $rank['medal'],
            'rank_stars' => $rank['stars'],
            'leaderboard_rank' => isset($profileResponse['leaderboard_rank']) ? (int) $profileResponse['leaderboard_rank'] : null,
            'mmr_estimate' => isset($profileResponse['mmr_estimate']['estimate'])
                ? (int) $profileResponse['mmr_estimate']['estimate']
                : null,
            'games' => $games,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $games > 0 ? round($wins / $games * 100, 1) : null,
            'averages' => $averages,
            'heroes' => $this->transformHeroes($heroStats, $heroes),
            'matches' => array_slice($matches, 0, 20),
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
            'cache_ttl' => $ttl,
        ];
    }

    /**
     * Search Dota 2 players by nickname through OpenDota.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        $cacheKey = 'opendota:search:'.sha1(mb_strtolower($query));

        return Cache::remember($cacheKey, 3600, function () use ($query) {
            try {
                $results = $this->client->get('search', ['q' => $query]);
            } catch (PlayerNotFoundException) {
                return [];
            }

            return collect($results)
                ->filter(fn ($result) => is_array($result) && ! empty($result['account_id']))
                ->take(10)
                ->map(fn (array $result) => [
                    'account_id' => (int) $result['account_id'],
                    'nickname' => $result['personaname'] ?? null,
                    'avatar' => $result['avatarfull'] ?? null,
                    'last_match_at' => isset($result['last_match_time'])
                        ? CarbonImmutable::parse($result['last_match_time'])->toIso8601String()
                        : null,
                ])
                ->values()
                ->all();
        });
    }

    public function forget(int $accountId): void
    {
        Cache::forget($this->cacheKey($accountId));
    }

    private function cacheKey(int $accountId): string
    {
        return "opendota:player:{$accountId}";
    }

    /**
     * Fetch an optional endpoint and return an empty array on failure.
     *
     * @return array<mixed>
     */
    private function optionalGet(string $path): array
    {
        try {
            return $this->client->get($path);
        } catch (ExternalApiException|PlayerNotFoundException $exception) {
            Log::info('OpenDota optional endpoint failed', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Global hero metadata (names + images), cached for a day.
     *
     * @return array<int, array<string, mixed>>
     */
    public function heroStats(): array
    {
        $cached = Cache::get(self::HERO_STATS_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $heroes = $this->client->get('heroStats');
        } catch (ExternalApiException $exception) {
            Log::warning('OpenDota hero stats are unavailable', ['message' => $exception->getMessage()]);

            return [];
        }

        $map = [];

        foreach ($heroes as $hero) {
            if (! is_array($hero) || ! isset($hero['id'])) {
                continue;
            }

            $map[(int) $hero['id']] = [
                'id' => (int) $hero['id'],
                'name' => $hero['localized_name'] ?? 'Hero #'.$hero['id'],
                'image' => isset($hero['img']) ? 'https://cdn.cloudflare.steamstatic.com'.$hero['img'] : null,
                'icon' => isset($hero['icon']) ? 'https://cdn.cloudflare.steamstatic.com'.$hero['icon'] : null,
                'primary_attr' => $hero['primary_attr'] ?? null,
                'attack_type' => $hero['attack_type'] ?? null,
            ];
        }

        if ($map !== []) {
            Cache::put(
                self::HERO_STATS_CACHE_KEY,
                $map,
                (int) config('stats.cache.dota_heroes', 86400)
            );
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $matches
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function transformMatches(array $matches, array $heroes): array
    {
        $transformed = [];

        foreach ($matches as $match) {
            if (! is_array($match) || ! isset($match['hero_id'], $match['player_slot'])) {
                continue;
            }

            $hero = $heroes[(int) $match['hero_id']] ?? [
                'id' => (int) $match['hero_id'],
                'name' => 'Hero #'.$match['hero_id'],
                'image' => null,
            ];

            $radiantWin = (bool) ($match['radiant_win'] ?? false);
            $isRadiant = (int) $match['player_slot'] < 128;
            $win = $radiantWin === $isRadiant;

            $kills = (int) ($match['kills'] ?? 0);
            $deaths = (int) ($match['deaths'] ?? 0);
            $assists = (int) ($match['assists'] ?? 0);
            $duration = (int) ($match['duration'] ?? 0);
            $startTime = (int) ($match['start_time'] ?? 0);

            $transformed[] = [
                'match_id' => isset($match['match_id']) ? (int) $match['match_id'] : null,
                'hero_id' => $hero['id'],
                'hero_name' => $hero['name'],
                'hero_image' => $hero['image'],
                'win' => $win,
                'kills' => $kills,
                'deaths' => $deaths,
                'assists' => $assists,
                'kda' => round(($kills + $assists) / max($deaths, 1), 2),
                'gpm' => (int) ($match['gold_per_min'] ?? 0),
                'xpm' => (int) ($match['xp_per_min'] ?? 0),
                'duration' => $duration,
                'duration_label' => $this->durationLabel($duration),
                'started_at' => $startTime > 0
                    ? CarbonImmutable::createFromTimestampUTC($startTime)->toIso8601String()
                    : null,
            ];
        }

        return $transformed;
    }

    /**
     * @param  array<int, mixed>  $heroStats
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function transformHeroes(array $heroStats, array $heroes): array
    {
        $rows = collect($heroStats)
            ->filter(fn ($hero) => is_array($hero) && isset($hero['hero_id']))
            ->sortByDesc(fn (array $hero) => (int) ($hero['games'] ?? 0))
            ->take(5)
            ->map(function (array $hero) use ($heroes) {
                $meta = $heroes[(int) $hero['hero_id']] ?? [
                    'id' => (int) $hero['hero_id'],
                    'name' => 'Hero #'.$hero['hero_id'],
                    'image' => null,
                ];

                $games = (int) ($hero['games'] ?? 0);
                $wins = (int) ($hero['win'] ?? 0);

                return [
                    'hero_id' => $meta['id'],
                    'name' => $meta['name'],
                    'image' => $meta['image'],
                    'games' => $games,
                    'wins' => $wins,
                    'winrate' => $games > 0 ? round($wins / $games * 100, 1) : null,
                    'last_played_at' => isset($hero['last_played'])
                        ? CarbonImmutable::createFromTimestampUTC((int) $hero['last_played'])->toIso8601String()
                        : null,
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $totals
     * @return array<string, array{n: int, sum: float}>
     */
    private function indexTotals(array $totals): array
    {
        $indexed = [];

        foreach ($totals as $row) {
            if (! is_array($row) || ! isset($row['field'])) {
                continue;
            }

            $indexed[(string) $row['field']] = [
                'n' => (int) ($row['n'] ?? 0),
                'sum' => (float) ($row['sum'] ?? 0),
            ];
        }

        return $indexed;
    }

    /**
     * @param  array<string, array{n: int, sum: float}>  $totals
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<string, float|int|null>
     */
    private function averages(array $totals, array $matches): array
    {
        $fromTotals = function (string $field) use ($totals): ?float {
            $row = $totals[$field] ?? null;

            if (! $row || $row['n'] <= 0) {
                return null;
            }

            return $row['sum'] / $row['n'];
        };

        $recentAverage = function (string $field) use ($matches): ?float {
            if ($matches === []) {
                return null;
            }

            return array_sum(array_column($matches, $field)) / count($matches);
        };

        $kills = $fromTotals('kills') ?? $recentAverage('kills');
        $deaths = $fromTotals('deaths') ?? $recentAverage('deaths');
        $assists = $fromTotals('assists') ?? $recentAverage('assists');
        $gpm = $fromTotals('gold_per_min') ?? $recentAverage('gpm');
        $xpm = $fromTotals('xp_per_min') ?? $recentAverage('xpm');

        return [
            'kills' => $kills !== null ? round($kills, 1) : null,
            'deaths' => $deaths !== null ? round($deaths, 1) : null,
            'assists' => $assists !== null ? round($assists, 1) : null,
            'kda' => ($kills !== null && $assists !== null)
                ? round(($kills + $assists) / max($deaths ?? 1, 1), 2)
                : null,
            'gpm' => $gpm !== null ? (int) round($gpm) : null,
            'xpm' => $xpm !== null ? (int) round($xpm) : null,
        ];
    }

    /**
     * @return array{label: string|null, medal: string|null, stars: int|null}
     */
    private function rank(?int $rankTier): array
    {
        if ($rankTier === null || $rankTier <= 0) {
            return ['label' => null, 'medal' => null, 'stars' => null];
        }

        $tier = intdiv($rankTier, 10);
        $medal = self::RANK_MEDALS[$tier] ?? null;
        $stars = $tier >= 1 && $tier <= 7 ? $rankTier % 10 : null;

        $label = $medal;

        if ($label !== null && $stars !== null && $stars > 0) {
            $label .= ' '.$stars;
        }

        return ['label' => $label, 'medal' => $medal, 'stars' => $stars];
    }

    private function durationLabel(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
}
