<?php

namespace App\Services\OpenDota;

use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\MatchNotFoundException;
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

    private const ITEM_STATS_CACHE_KEY = 'opendota:item-stats:v1';

    private const TEAM_CHART_COLORS = [
        '#34d399',
        '#22d3ee',
        '#a78bfa',
        '#fbbf24',
        '#fb7185',
    ];

    private const POSITION_ROLES = [
        1 => ['label' => 'Safe', 'name' => 'Carry'],
        2 => ['label' => 'Mid', 'name' => 'Mid'],
        3 => ['label' => 'Off', 'name' => 'Offlane'],
        4 => ['label' => 'Soft', 'name' => 'Soft Support'],
        5 => ['label' => 'Hard', 'name' => 'Hard Support'],
    ];

    private const LANE_ROLES = [
        1 => ['label' => 'Safe', 'name' => 'Safe Lane'],
        2 => ['label' => 'Mid', 'name' => 'Mid Lane'],
        3 => ['label' => 'Off', 'name' => 'Off Lane'],
        4 => ['label' => 'Jungle', 'name' => 'Jungle'],
    ];

    private const LOBBY_TYPES = [
        0 => 'Normal',
        1 => 'Practice',
        2 => 'Tournament',
        3 => 'Tutorial',
        4 => 'Co-op vs Bots',
        5 => 'Team Match',
        6 => 'Solo Queue',
        7 => 'Ranked',
        8 => '1v1 Mid',
        9 => 'Battle Cup',
    ];

    private const PURCHASE_SKIP = [
        'tpscroll',
        'tango',
        'branches',
        'clarity',
        'flask',
        'faerie_fire',
        'enchanted_mango',
        'blood_grenade',
        'dust',
        'smoke_of_deceit',
        'ward_observer',
        'ward_sentry',
        'ward_dispenser',
        'magic_stick',
    ];

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
        $heroRows = $this->transformHeroes($heroStats, $heroes, $recentMatches);

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
            'heroes' => $heroRows,
            'top_heroes' => array_slice($heroRows, 0, 5),
            'matches' => array_slice($matches, 0, 20),
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
            'cache_ttl' => $ttl,
        ];
    }

    /**
     * Cached match details with a per-team scoreboard.
     *
     * Finished matches never change, so the payload is cached for an hour and
     * re-opening the page costs no OpenDota requests.
     *
     * @return array<string, mixed>
     *
     * @throws MatchNotFoundException
     */
    public function getMatch(int $matchId): array
    {
        $ttl = (int) config('stats.cache.dota_match', 3600);

        return Cache::remember("opendota:match:v2:{$matchId}", $ttl, function () use ($matchId) {
            try {
                $match = $this->client->get("matches/{$matchId}");
            } catch (PlayerNotFoundException) {
                throw new MatchNotFoundException($matchId);
            }

            if (! is_array($match) || empty($match['match_id']) || empty($match['players'])) {
                throw new MatchNotFoundException($matchId);
            }

            return $this->transformMatch($match);
        });
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
        } catch (ApiUnavailableException $exception) {
            throw $exception;
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
        } catch (ApiUnavailableException $exception) {
            throw $exception;
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
     * Global item metadata (localized names, images and costs), cached for a day.
     *
     * @return array<string, array{name: string, image: string, cost: int}>
     */
    public function itemStats(): array
    {
        $cached = Cache::get(self::ITEM_STATS_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $items = $this->client->get('constants/items');
        } catch (ApiUnavailableException $exception) {
            throw $exception;
        } catch (ExternalApiException|PlayerNotFoundException $exception) {
            Log::warning('OpenDota item metadata is unavailable', ['message' => $exception->getMessage()]);

            return [];
        }

        $map = [];

        foreach ($items as $key => $item) {
            if (! is_array($item) || ! isset($item['dname'])) {
                continue;
            }

            $map[(string) $key] = [
                'name' => (string) $item['dname'],
                'image' => isset($item['img'])
                    ? 'https://cdn.cloudflare.steamstatic.com'.$item['img']
                    : $this->itemImage((string) $key),
                'cost' => (int) ($item['cost'] ?? 0),
            ];
        }

        if ($map !== []) {
            Cache::put(
                self::ITEM_STATS_CACHE_KEY,
                $map,
                (int) config('stats.cache.dota_items', 86400)
            );
        }

        return $map;
    }

    /**
     * Build the match view model from the raw OpenDota payload.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function transformMatch(array $match): array
    {
        $heroes = $this->heroStats();
        $items = $this->itemStats();

        $radiant = [];
        $dire = [];

        foreach ($match['players'] as $player) {
            if (! is_array($player) || ! isset($player['hero_id'], $player['player_slot'])) {
                continue;
            }

            $heroId = (int) $player['hero_id'];
            $hero = $heroes[$heroId] ?? [
                'id' => $heroId,
                'name' => 'Hero #'.$heroId,
                'image' => null,
            ];

            $kills = (int) ($player['kills'] ?? 0);
            $deaths = (int) ($player['deaths'] ?? 0);
            $assists = (int) ($player['assists'] ?? 0);
            $slot = (int) $player['player_slot'];
            $accountId = isset($player['account_id']) ? (int) $player['account_id'] : 0;
            $purchaseLog = is_array($player['purchase_log'] ?? null) ? $player['purchase_log'] : [];

            $row = [
                'account_id' => $accountId > 0 ? $accountId : null,
                'player_slot' => $slot,
                'hero_id' => $hero['id'],
                'hero_name' => $hero['name'],
                'hero_image' => $hero['image'],
                'nickname' => $player['personaname'] ?? null,
                'level' => (int) ($player['level'] ?? 0),
                'kills' => $kills,
                'deaths' => $deaths,
                'assists' => $assists,
                'kda' => round(($kills + $assists) / max($deaths, 1), 2),
                'gpm' => (int) ($player['gold_per_min'] ?? 0),
                'xpm' => (int) ($player['xp_per_min'] ?? 0),
                'hero_damage' => (int) ($player['hero_damage'] ?? 0),
                'last_hits' => (int) ($player['last_hits'] ?? 0),
                'denies' => (int) ($player['denies'] ?? 0),
                'net_worth' => (int) ($player['net_worth'] ?? 0),
                'role' => $this->matchRole($player),
                'purchases' => $this->transformPurchases($purchaseLog, $items),
                'purchase_log_count' => count($purchaseLog),
                'net_worth_history' => $this->numericSeries($player['networth_t'] ?? []),
                'xp_history' => $this->numericSeries($player['xp_t'] ?? []),
            ];

            if ($slot < 128) {
                $radiant[] = $row;
            } else {
                $dire[] = $row;
            }
        }

        usort($radiant, fn (array $a, array $b) => $a['player_slot'] <=> $b['player_slot']);
        usort($dire, fn (array $a, array $b) => $a['player_slot'] <=> $b['player_slot']);

        $duration = (int) ($match['duration'] ?? 0);
        $startTime = (int) ($match['start_time'] ?? 0);
        $lobbyType = isset($match['lobby_type']) ? (int) $match['lobby_type'] : null;
        $gameMode = isset($match['game_mode']) ? (int) $match['game_mode'] : null;

        $radiantScore = isset($match['radiant_score'])
            ? (int) $match['radiant_score']
            : array_sum(array_column($radiant, 'kills'));

        $direScore = isset($match['dire_score'])
            ? (int) $match['dire_score']
            : array_sum(array_column($dire, 'kills'));

        return [
            'match_id' => (int) $match['match_id'],
            'radiant_win' => (bool) ($match['radiant_win'] ?? false),
            'duration' => $duration,
            'duration_label' => $this->durationLabel($duration),
            'started_at' => $startTime > 0
                ? CarbonImmutable::createFromTimestampUTC($startTime)->toIso8601String()
                : null,
            'game_mode' => $gameMode,
            'game_mode_label' => $this->gameModeLabel($gameMode),
            'lobby_type' => $lobbyType,
            'lobby_type_label' => $this->lobbyTypeLabel($lobbyType),
            'is_ranked' => $lobbyType === 7,
            'radiant_score' => $radiantScore,
            'dire_score' => $direScore,
            'players' => [
                'radiant' => $radiant,
                'dire' => $dire,
            ],
            'net_worth_chart' => $this->netWorthChart($radiant, $dire),
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $player
     * @return array{label: string|null, name: string|null, source: string|null}
     */
    private function matchRole(array $player): array
    {
        $position = isset($player['position_est']) && is_numeric($player['position_est'])
            ? (int) $player['position_est']
            : 0;

        if (isset(self::POSITION_ROLES[$position])) {
            return self::POSITION_ROLES[$position] + ['source' => 'OpenDota'];
        }

        $laneRole = isset($player['lane_role']) && is_numeric($player['lane_role'])
            ? (int) $player['lane_role']
            : 0;

        if (isset(self::LANE_ROLES[$laneRole])) {
            return self::LANE_ROLES[$laneRole] + ['source' => 'OpenDota lane'];
        }

        if (! empty($player['is_roaming'])) {
            return ['label' => 'Roam', 'name' => 'Roaming', 'source' => 'OpenDota'];
        }

        return ['label' => null, 'name' => null, 'source' => null];
    }

    /**
     * Keep 5-10 meaningful purchases (skipping consumables and recipes).
     *
     * @param  array<int, mixed>  $log
     * @param  array<string, array{name: string, image: string, cost: int}>  $items
     * @return array<int, array<string, mixed>>
     */
    private function transformPurchases(array $log, array $items): array
    {
        $purchases = [];

        foreach ($log as $entry) {
            if (! is_array($entry) || ! isset($entry['key'])) {
                continue;
            }

            $key = (string) $entry['key'];

            if (str_starts_with($key, 'recipe_') || in_array($key, self::PURCHASE_SKIP, true)) {
                continue;
            }

            $meta = $items[$key] ?? [];
            $cost = (int) ($meta['cost'] ?? 0);

            if ($cost > 0 && $cost < 500) {
                continue;
            }

            $time = (int) ($entry['time'] ?? 0);

            $purchases[] = [
                'key' => $key,
                'name' => $meta['name'] ?? $this->itemName($key),
                'image' => $meta['image'] ?? $this->itemImage($key),
                'cost' => $cost,
                'time' => $time,
                'time_label' => $this->matchTimeLabel($time),
            ];
        }

        $purchases = array_slice($purchases, 0, 10);

        if ($purchases === [] && $log !== []) {
            foreach (array_slice($log, 0, 8) as $entry) {
                if (! is_array($entry) || ! isset($entry['key'])) {
                    continue;
                }

                $key = (string) $entry['key'];
                $meta = $items[$key] ?? [];
                $time = (int) ($entry['time'] ?? 0);

                $purchases[] = [
                    'key' => $key,
                    'name' => $meta['name'] ?? $this->itemName($key),
                    'image' => $meta['image'] ?? $this->itemImage($key),
                    'cost' => (int) ($meta['cost'] ?? 0),
                    'time' => $time,
                    'time_label' => $this->matchTimeLabel($time),
                ];
            }
        }

        return $purchases;
    }

    private function itemName(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private function itemImage(string $key): string
    {
        return 'https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/items/'.rawurlencode($key).'.png';
    }

    private function matchTimeLabel(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'старт';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * @return array<int, int>
     */
    private function numericSeries(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        return array_values(array_map(
            fn ($value) => is_numeric($value) ? (int) $value : 0,
            $series
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $radiant
     * @param  array<int, array<string, mixed>>  $dire
     * @return array{labels: array<int, int>, radiant: array{datasets: array<int, array<string, mixed>>}, dire: array{datasets: array<int, array<string, mixed>>}, has_data: bool}
     */
    private function netWorthChart(array $radiant, array $dire): array
    {
        $lengths = array_merge(
            [0],
            array_map(fn (array $player) => count($player['net_worth_history']), $radiant),
            array_map(fn (array $player) => count($player['net_worth_history']), $dire),
        );
        $maxLength = max($lengths);

        if ($maxLength === 0) {
            return [
                'labels' => [],
                'radiant' => ['datasets' => []],
                'dire' => ['datasets' => []],
                'has_data' => false,
            ];
        }

        $datasets = function (array $players) use ($maxLength): array {
            $result = [];
            $index = 0;

            foreach ($players as $player) {
                $history = $player['net_worth_history'];

                if ($history === []) {
                    continue;
                }

                $color = self::TEAM_CHART_COLORS[$index % count(self::TEAM_CHART_COLORS)];
                $index++;

                $result[] = [
                    'label' => $player['hero_name'],
                    'data' => array_pad($history, $maxLength, null),
                    'borderColor' => $color,
                    'backgroundColor' => $color,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'borderWidth' => 2,
                    'tension' => 0.28,
                    'fill' => false,
                ];
            }

            return $result;
        };

        $radiantDatasets = $datasets($radiant);
        $direDatasets = $datasets($dire);

        return [
            'labels' => range(0, $maxLength - 1),
            'radiant' => ['datasets' => $radiantDatasets],
            'dire' => ['datasets' => $direDatasets],
            'has_data' => $radiantDatasets !== [] || $direDatasets !== [],
        ];
    }

    private function gameModeLabel(?int $mode): ?string
    {
        return match ($mode) {
            1 => 'All Pick',
            2 => 'Captains Mode',
            3 => 'Random Draft',
            4 => 'Single Draft',
            5 => 'All Random',
            6 => 'Intro',
            7 => 'Diretide',
            8 => 'Reverse Captains Mode',
            9 => 'Greeviling',
            10 => 'Tutorial',
            11 => 'Mid Only',
            12 => 'Least Played',
            13 => 'Limited Heroes',
            14 => 'Compendium',
            15 => 'Custom',
            16 => 'Captains Draft',
            17 => 'Balanced Draft',
            18 => 'Ability Draft',
            19 => 'Event',
            20 => 'All Random Deathmatch',
            21 => '1v1 Mid',
            22 => 'Ranked All Pick',
            23 => 'Turbo',
            24 => 'Mutation',
            default => null,
        };
    }

    private function lobbyTypeLabel(?int $lobbyType): ?string
    {
        return $lobbyType !== null ? (self::LOBBY_TYPES[$lobbyType] ?? null) : null;
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
     * @param  array<int, mixed>  $recentMatches
     * @return array<int, array<string, mixed>>
     */
    private function transformHeroes(array $heroStats, array $heroes, array $recentMatches = []): array
    {
        $kdaByHero = $this->heroKda($recentMatches);

        $rows = collect($heroStats)
            ->filter(fn ($hero) => is_array($hero) && isset($hero['hero_id']) && (int) ($hero['games'] ?? 0) > 0)
            ->sortByDesc(fn (array $hero) => (int) ($hero['games'] ?? 0))
            ->map(function (array $hero) use ($heroes, $kdaByHero) {
                $meta = $heroes[(int) $hero['hero_id']] ?? [
                    'id' => (int) $hero['hero_id'],
                    'name' => 'Hero #'.$hero['hero_id'],
                    'image' => null,
                ];

                $games = (int) ($hero['games'] ?? 0);
                $wins = (int) ($hero['win'] ?? 0);
                $winRate = $games > 0 ? round($wins / $games * 100, 1) : null;

                return [
                    'hero_id' => $meta['id'],
                    // Keep both naming schemes: localized_name/win_rate feed the
                    // interactive table, name/winrate the existing overview.
                    'localized_name' => $meta['name'],
                    'name' => $meta['name'],
                    'image' => $meta['image'],
                    'games' => $games,
                    'win' => $wins,
                    'wins' => $wins,
                    'win_rate' => $winRate,
                    'winrate' => $winRate,
                    'kda' => $kdaByHero[$meta['id']] ?? null,
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
     * Per-hero KDA from the recent matches payload (OpenDota hero totals do not
     * include kills/deaths/assists). Heroes outside the recent window get null.
     *
     * @param  array<int, mixed>  $matches
     * @return array<int, float>
     */
    private function heroKda(array $matches): array
    {
        $totals = [];

        foreach ($matches as $match) {
            if (! is_array($match) || ! isset($match['hero_id'])) {
                continue;
            }

            $heroId = (int) $match['hero_id'];
            $totals[$heroId] ??= ['kills' => 0, 'deaths' => 0, 'assists' => 0];

            $totals[$heroId]['kills'] += (int) ($match['kills'] ?? 0);
            $totals[$heroId]['deaths'] += (int) ($match['deaths'] ?? 0);
            $totals[$heroId]['assists'] += (int) ($match['assists'] ?? 0);
        }

        return array_map(
            fn (array $row) => round(($row['kills'] + $row['assists']) / max($row['deaths'], 1), 2),
            $totals
        );
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
