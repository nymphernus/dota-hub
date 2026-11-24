<?php

namespace App\Services\OpenDota;

use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\MatchNotFoundException;
use App\Exceptions\PlayerNotFoundException;
use App\Models\PlayerNameHistory;
use App\Models\PlayerProfile;
use App\Services\Stratz\StratzService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds the view models used by the Dota 2 profile page.
 *
 * Full player aggregates are stored in PostgreSQL so a profile remains
 * available even when Redis is cold or OpenDota is down. Small derived lookups
 * (match summaries, hero metadata) stay in the regular Laravel cache.
 */
class OpenDotaService
{
    private const HERO_STATS_CACHE_KEY = 'opendota:hero-stats:v3';

    private const ITEM_STATS_CACHE_KEY = 'opendota:item-stats:v3';

    private const RECENT_MATCH_DETAILS = 25;

    private const PROFILE_SCHEMA_VERSION = 7;

    /**
     * Human-readable labels for OpenDota /players/{id}/records keys.
     */
    private static function recordLabels(): array
    {
        return [
            'DURATION' => __('ui.longest_match'),
            'SHORTEST_DURATION' => __('ui.shortest_match'),
            'KILLS' => __('ui.most_kills'),
            'DEATHS' => __('ui.most_deaths'),
            'ASSISTS' => __('ui.most_assists'),
            'GPM' => __('ui.highest_gpm'),
            'XPM' => __('ui.highest_xpm'),
            'HERO_DAMAGE' => __('ui.most_hero_damage'),
            'TOWER_DAMAGE' => __('ui.most_tower_damage'),
            'HERO_HEALING' => __('ui.most_hero_healing'),
            'LAST_HITS' => __('ui.most_last_hits'),
            'DENIES' => __('ui.most_denies'),
            'GOLD' => __('ui.most_gold'),
            'NET_WORTH' => __('ui.most_net_worth'),
            'LONGEST_WIN_STREAK' => __('ui.longest_win_streak'),
        ];
    }

    private static function recordLabel(string $name): string
    {
        $labels = self::recordLabels();
        return $labels[$name] ?? ucwords(str_replace('_', ' ', strtolower($name)));
    }

    /** Record fields available in the cached match history. */
    private const HISTORY_RECORD_FIELDS = [
        'DURATION' => 'duration',
        'SHORTEST_DURATION' => 'duration',
        'KILLS' => 'kills',
        'DEATHS' => 'deaths',
        'ASSISTS' => 'assists',
        'GPM' => 'gpm',
        'XPM' => 'xpm',
        'HERO_DAMAGE' => 'hero_damage',
        'TOWER_DAMAGE' => 'tower_damage',
        'HERO_HEALING' => 'hero_healing',
        'LAST_HITS' => 'last_hits',
        'DENIES' => 'denies',
        'GOLD' => 'gold',
        'NET_WORTH' => 'net_worth',
    ];

    private const MMR_SAMPLE_SIZE = 100;

    private const MMR_WIN = 25;

    private const MMR_LOSS = -25;

    /**
     * Official rank medal base values: five stars per medal, then Immortal.
     * Tiers 1-6 add 154 MMR per star, Divine adds 200 MMR per star.
     */
    private const RANK_MMR_TABLE = [
        1 => [0, 154, 308, 462, 616],
        2 => [770, 924, 1078, 1232, 1386],
        3 => [1540, 1694, 1848, 2002, 2156],
        4 => [2310, 2464, 2618, 2772, 2926],
        5 => [3080, 3234, 3388, 3542, 3696],
        6 => [3850, 4004, 4158, 4312, 4466],
        7 => [4620, 4820, 5020, 5220, 5420],
    ];

    private const IMMORTAL_BASE_MMR = 5420;

    private const TEAM_CHART_COLORS = [
        '#34d399',
        '#22d3ee',
        '#fb923c',
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

    public function __construct(
        private readonly OpenDotaClient $client,
        private readonly StratzService $stratz,
    ) {}

    /**
     * Full player profile, persisted in PostgreSQL for offline access.
     *
     * Fresh database rows are returned immediately. Stale rows are kept as a
     * fallback: if OpenDota becomes unreachable the last known snapshot is
     * rendered with an explicit offline marker instead of a 502 page.
     *
     * @return array<string, mixed>
     */
    public function player(int $accountId): array
    {
        $ttl = (int) config('stats.cache.dota_profile', 3600);
        $record = PlayerProfile::find($accountId);

        if ($record !== null && $this->isFresh($record, $ttl)) {
            return $this->profileFromRecord($record, $ttl, false);
        }

        try {
            $profile = $this->fetchPlayer($accountId, $ttl);
            $this->storeProfile($accountId, $profile);
            $profile['name_history'] = $this->nameHistory($accountId);

            return $profile;
        } catch (ApiUnavailableException $exception) {
            if ($record === null) {
                throw $exception;
            }

            $profile = $this->profileFromRecord($record, $ttl, true);
            $profile['served_from_fresh_db'] = false;

            return $profile;
        }
    }

    private function isFresh(PlayerProfile $record, int $ttl): bool
    {
        $updatedAt = $record->updated_at;

        return $updatedAt !== null
            && ($record->data['schema_version'] ?? 0) === self::PROFILE_SCHEMA_VERSION
            && $updatedAt->greaterThan(CarbonImmutable::now()->subSeconds(max($ttl, 1)));
    }

    /**
     * @return array<string, mixed>
     */
    private function profileFromRecord(PlayerProfile $record, int $ttl, bool $isOffline = false): array
    {
        $profile = is_array($record->data) ? $record->data : [];
        $profile['cached_at'] = $record->updated_at?->toIso8601String()
            ?? ($profile['cached_at'] ?? CarbonImmutable::now()->toIso8601String());
        $profile['cache_ttl'] = $ttl;
        $profile['is_offline_cache'] = $isOffline;
        $profile['served_from_fresh_db'] = ! $isOffline;
        $profile['name_history'] = $this->nameHistory((int) $record->account_id);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function storeProfile(int $accountId, array $profile): void
    {
        $existing = PlayerProfile::find($accountId);
        $oldNickname = $existing?->nickname;
        $newNickname = $profile['nickname'] ?? null;

        if (is_string($oldNickname) && is_string($newNickname) && $oldNickname !== $newNickname) {
            PlayerNameHistory::firstOrCreate(
                ['account_id' => $accountId, 'name' => $oldNickname],
                ['changed_at' => CarbonImmutable::now()],
            );
        }

        PlayerProfile::updateOrCreate(
            ['account_id' => $accountId],
            [
                'nickname' => $newNickname,
                'avatar' => $profile['avatar'] ?? null,
                'data' => $profile,
                'updated_at' => CarbonImmutable::now(),
            ]
        );
    }

    /**
     * @return array<int, array{name: string, changed_at: string}>
     */
    private function nameHistory(int $accountId): array
    {
        return PlayerNameHistory::query()
            ->where('account_id', $accountId)
            ->orderByDesc('changed_at')
            ->limit(20)
            ->get()
            ->map(fn (PlayerNameHistory $row) => [
                'name' => (string) $row->name,
                'changed_at' => $row->changed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Lightweight player identity used by the hero detail page.
     *
     * The profile page stores this entry while building its aggregate, so
     * navigating from the profile to a hero costs no extra OpenDota request.
     *
     * @return array<string, mixed>
     *
     * @throws PlayerNotFoundException
     */
    public function playerHeader(int $accountId): array
    {
        $ttl = (int) config('stats.cache.dota_profile', 1800);
        $cacheKey = $this->playerHeaderCacheKey($accountId);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $profileResponse = $this->client->get("players/{$accountId}");
        $header = $this->playerIdentity($profileResponse, $accountId);
        Cache::put($cacheKey, $header, $ttl);

        return $header;
    }

    /**
     * Estimate a player's MMR without spending extra requests on a normal
     * profile render. OpenDota's own estimate wins when it is available;
     * otherwise the current medal is the baseline and the last 20 ranked
     * games move it by +25 for a win and -20 for a loss.
     */
    public function calculateMmrEstimate(int $accountId): ?int
    {
        $profileResponse = $this->client->get("players/{$accountId}");
        $rankTier = isset($profileResponse['rank_tier']) && is_numeric($profileResponse['rank_tier'])
            ? (int) $profileResponse['rank_tier']
            : null;

        // Ask for a wider window so the fallback still finds 20 ranked games when
        // unranked/turbo matches are interleaved.
        $matches = $this->optionalGet("players/{$accountId}/matches", [
            'limit' => 100,
        ]);

        return $this->estimateMmr(
            $rankTier,
            $matches,
            isset($profileResponse['mmr_estimate']['estimate'])
                ? (int) $profileResponse['mmr_estimate']['estimate']
                : null,
        )['value'];
    }

    /**
     * @param  array<int, mixed>  $matches
     * @return array{value: int|null, is_estimated: bool, source: string|null, games: int}
     */
    private function estimateMmr(?int $rankTier, array $matches, ?int $openDotaEstimate = null): array
    {
        if ($openDotaEstimate !== null && $openDotaEstimate > 0) {
            return [
                'value' => $openDotaEstimate,
                'is_estimated' => false,
                'source' => 'OpenDota',
                'games' => 0,
            ];
        }

        if ($rankTier === null || $rankTier <= 0) {
            return ['value' => null, 'is_estimated' => false, 'source' => null, 'games' => 0];
        }

        $ranked = array_values(array_filter($matches, static function ($m): bool {
            return is_array($m)
                && ! empty($m['start_time'])
                && (int) ($m['lobby_type'] ?? -1) === 7;
        }));

        usort($ranked, static fn (array $a, array $b): int => (int) ($b['start_time'] ?? 0) <=> (int) ($a['start_time'] ?? 0));
        $ranked = array_slice($ranked, 0, self::MMR_SAMPLE_SIZE);

        $wins = 0;
        $losses = 0;
        foreach ($ranked as $match) {
            $radiantWin = (bool) ($match['radiant_win'] ?? false);
            $isRadiant = (int) ($match['player_slot'] ?? 0) < 128;
            if ($radiantWin === $isRadiant) {
                $wins++;
            } else {
                $losses++;
            }
        }

        $base = $this->baseMmr($rankTier);
        $value = max(0, $base + ($wins * self::MMR_WIN) + ($losses * self::MMR_LOSS));

        return [
            'value' => $value,
            'is_estimated' => true,
            'source' => 'rank+recent',
            'games' => $wins + $losses,
        ];
    }

    private function baseMmr(int $rankTier): int
    {
        $tier = intdiv($rankTier, 10);

        if ($tier === 8) {
            return self::IMMORTAL_BASE_MMR;
        }

        $table = self::RANK_MMR_TABLE[$tier] ?? null;
        $stars = $rankTier % 10;

        if ($table === null || $stars < 1 || $stars > 5) {
            return 0;
        }

        return $table[$stars - 1];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPlayer(int $accountId, int $ttl): array
    {
        $profileResponse = $this->client->get("players/{$accountId}");

        $profile = $profileResponse['profile'] ?? null;
        $isProfileHidden = ! is_array($profile) || $profile === [];
        $profile = is_array($profile) ? $profile : [];

        Cache::put($this->playerHeaderCacheKey($accountId), $this->playerIdentity($profileResponse, $accountId), $ttl);

        // Optional endpoints. A failure here must not hide the profile itself,
        // but rate limit errors do bubble up from the client.
        $winLoss = $this->optionalGet('players/'.$accountId.'/wl');
        $recentMatches = $this->optionalGet('players/'.$accountId.'/recentMatches');
        $heroStats = $this->optionalGet('players/'.$accountId.'/heroes');
        $totals = $this->optionalGet('players/'.$accountId.'/totals');
        $matchHistory = $this->fetchMatchHistory($accountId);

        // OpenDota's hero endpoint is not always populated. STRATZ exposes the
        // same aggregate through GraphQL, so use it before showing an empty pool.
        if ($heroStats === []) {
            $heroStats = $this->stratz->playerHeroes($accountId);
        }

        // If STRATZ is blocked (403 Cloudflare) or returns no rows, aggregate the
        // cached match history instead of leaving "Самые играемые герои" empty.
        if ($heroStats === []) {
            $heroStats = $this->heroesFromHistory($matchHistory);
        }

        $peers = $recentMatches === []
            ? []
            : $this->optionalGet('players/'.$accountId.'/peers');

        $heroes = $this->heroStats();
        $matches = $this->transformMatches($recentMatches, $heroes);
        $matches = $this->enrichMatches($matches, $accountId, self::RECENT_MATCH_DETAILS);
        $favoriteHeroes = $this->peersFavoriteHeroes(array_column($matches, 'match_id'), $heroes);
        $history = $this->transformHistory($matchHistory, $heroes);

        // The history endpoint can be unavailable even when recent matches are
        // not. The last 20 games are still a useful (if shorter) fallback.
        if ($history === []) {
            $history = $this->transformHistory($recentMatches, $heroes);
        }

        $totalsByField = $this->indexTotals($totals);

        $wins = (int) ($winLoss['win'] ?? 0);
        $losses = (int) ($winLoss['lose'] ?? 0);

        if ($wins === 0 && $losses === 0 && $matches !== []) {
            $wins = count(array_filter($matches, fn (array $match) => $match['win']));
            $losses = count($matches) - $wins;
        }

        $games = $wins + $losses;

        // When STRATZ returns full history, trust its count for the profile total.
        if (count($history) > $games) {
            $games = count($history);
            $wins = count(array_filter($history, static fn (array $m): bool => ! empty($m['win'])));
            $losses = max(0, $games - $wins);
        }

        $averages = $this->averages($totalsByField, $matches);

        $rankTier = isset($profileResponse['rank_tier']) && is_numeric($profileResponse['rank_tier'])
            ? (int) $profileResponse['rank_tier']
            : null;

        $rank = $this->rank($rankTier);
        $mmr = $this->estimateMmr(
            $rankTier,
            $matchHistory,
            isset($profileResponse['mmr_estimate']['estimate'])
                ? (int) $profileResponse['mmr_estimate']['estimate']
                : null,
        );

        $nickname = $profile['personaname'] ?? null;
        $heroRows = $this->transformHeroes($heroStats, $heroes, $recentMatches, $history);
        $hasPublicMatches = $matches !== [] || $history !== [];

        return [
            'account_id' => (int) ($profile['account_id'] ?? $accountId),
            'steam_id' => (string) ($profile['steamid'] ?? ''),
            'nickname' => $nickname ?: 'Анонимный игрок',
            'avatar' => $profile['avatarfull'] ?? $profile['avatarmedium'] ?? $profile['avatar'] ?? null,
            'profile_url' => $profile['profileurl'] ?? null,
            'country_code' => $profile['loccountrycode'] ?? null,
            'last_login_at' => $this->dateTime($profile['last_login'] ?? null),
            'is_private' => $isProfileHidden,
            'rank_tier' => $rankTier,
            'rank_label' => $rank['label'],
            'rank_medal' => $rank['medal'],
            'rank_stars' => $rank['stars'],
            'leaderboard_rank' => isset($profileResponse['leaderboard_rank']) ? (int) $profileResponse['leaderboard_rank'] : null,
            'mmr_estimate' => $mmr['value'],
            'mmr_is_estimated' => $mmr['is_estimated'],
            'mmr_source' => $mmr['source'],
            'mmr_games_considered' => $mmr['games'],
            'games' => $games,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $games > 0 ? round($wins / $games * 100, 1) : null,
            'averages' => $averages,
            'heroes' => $heroRows,
            'top_heroes' => array_slice($heroRows, 0, 5),
            'matches' => array_slice($matches, 0, 25),
            'match_history' => $history,
            'peers' => $this->transformPeers($peers, $favoriteHeroes),
            'has_public_matches' => $hasPublicMatches,
            'dota_data_hidden' => ! $hasPublicMatches,
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
            'cache_ttl' => $ttl,
            'schema_version' => self::PROFILE_SCHEMA_VERSION,
        ];
    }

    /**
     * Frequent teammates from OpenDota's aggregated peers endpoint.
     *
     * @param  array<int, mixed>  $peers
     * @param  array<int, array<string, mixed>>  $favoriteHeroes
     * @return array<int, array<string, mixed>>
     */
    private function transformPeers(array $peers, array $favoriteHeroes = []): array
    {
        $rows = [];

        foreach ($peers as $peer) {
            if (! is_array($peer) || (int) ($peer['account_id'] ?? 0) <= 0) {
                continue;
            }

            $games = (int) ($peer['games'] ?? 0);
            $wins = (int) ($peer['win'] ?? 0);
            $lastMatch = $peer['last_match_time'] ?? null;
            $profile = $peer['profile'] ?? null;
            $profileHidden = array_key_exists('profile', $peer) && empty($profile);
            $accountId = (int) $peer['account_id'];

            $rows[] = [
                'account_id' => $accountId,
                'nickname' => ($peer['personaname'] ?? null) ?: 'Анонимный игрок',
                'avatar' => $peer['avatarfull'] ?? $peer['avatar'] ?? null,
                'profile_url' => is_array($profile) ? ($profile['profileurl'] ?? null) : null,
                'is_private' => $profileHidden,
                'games' => $games,
                'wins' => $wins,
                'winrate' => $games > 0 ? round($wins / $games * 100, 1) : null,
                'last_played_at' => $this->dateTime($lastMatch),
                'favorite_hero' => $favoriteHeroes[$accountId] ?? null,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['games'], $b['wins']] <=> [$a['games'], $a['wins']]);

        return array_slice($rows, 0, 50);
    }

    /**
     * Most frequent hero played alongside each teammate in the sampled matches.
     *
     * The per-match map is written by matchPlayerSummary() while the details are
     * already being fetched, so this never adds an OpenDota request.
     *
     * @param  array<int, mixed>  $matchIds
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function peersFavoriteHeroes(array $matchIds, array $heroes): array
    {
        $counts = [];

        foreach ($matchIds as $matchId) {
            $map = Cache::get('opendota:match-peer-heroes:v1:'.(int) $matchId);

            if (! is_array($map)) {
                continue;
            }

            foreach ($map as $peerId => $heroCounts) {
                if (! is_array($heroCounts)) {
                    continue;
                }

                foreach ($heroCounts as $heroId => $games) {
                    $peerId = (int) $peerId;
                    $heroId = (int) $heroId;
                    $counts[$peerId][$heroId] = ($counts[$peerId][$heroId] ?? 0) + (int) $games;
                }
            }
        }

        $favorites = [];

        foreach ($counts as $peerId => $heroCounts) {
            arsort($heroCounts);
            $heroId = (int) array_key_first($heroCounts);
            $meta = $heroes[$heroId] ?? null;

            $favorites[$peerId] = [
                'hero_id' => $heroId,
                'name' => $meta['name'] ?? ('Герой #'.$heroId),
                'image' => $meta['image'] ?? null,
                'games' => (int) reset($heroCounts),
            ];
        }

        return $favorites;
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

        return Cache::remember("opendota:match:v4:{$matchId}", $ttl, function () use ($matchId) {
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
        PlayerProfile::whereKey($accountId)->delete();
        Cache::forget($this->cacheKey($accountId));
        Cache::forget($this->playerHeaderCacheKey($accountId));
        Cache::forget("opendota:records:v1:{$accountId}");
        Cache::forget("opendota:records:v2:{$accountId}");
        Cache::forget("opendota:records:v4:{$accountId}:all");
        Cache::forget("opendota:records:v4:{$accountId}:ranked");
        Cache::forget("opendota:records:v5:{$accountId}:all");
        Cache::forget("opendota:records:v5:{$accountId}:ranked");
        Cache::forget("opendota:records:v6:{$accountId}:all");
        Cache::forget("opendota:records:v6:{$accountId}:ranked");
    }

    /**
     * Percentile distributions for a hero, cached for a day.
     *
     * OpenDota returns separate distributions for kills, deaths and assists.
     * The KDA distribution is derived from matching percentiles so it can be
     * compared on the same scale as GPM and XPM.
     *
     * @return array<string, array<int, array{percentile: float, value: float}>>
     */
    public function getHeroBenchmarks(int $heroId): array
    {
        if ($heroId <= 0) {
            return [];
        }

        $ttl = (int) config('stats.cache.dota_benchmarks', 86400);

        return Cache::remember("opendota:hero-benchmarks:v1:{$heroId}", $ttl, function () use ($heroId) {
            try {
                $response = $this->client->get('benchmarks', ['hero_id' => $heroId]);
            } catch (ApiUnavailableException $exception) {
                throw $exception;
            } catch (ExternalApiException|PlayerNotFoundException $exception) {
                Log::warning('OpenDota hero benchmarks are unavailable', [
                    'hero_id' => $heroId,
                    'message' => $exception->getMessage(),
                ]);

                return [];
            }

            $raw = isset($response['result']) && is_array($response['result'])
                ? $response['result']
                : $response;

            if (! is_array($raw)) {
                return [];
            }

            $gpm = $this->normalizeBenchmark($raw['gold_per_min'] ?? []);
            $xpm = $this->normalizeBenchmark($raw['xp_per_min'] ?? []);
            $kills = $this->normalizeBenchmark($raw['kills_per_min'] ?? []);
            $deaths = $this->normalizeBenchmark($raw['deaths_per_min'] ?? []);
            $assists = $this->normalizeBenchmark($raw['assists_per_min'] ?? []);

            return array_filter([
                'gpm' => $gpm,
                'xpm' => $xpm,
                'kda' => $this->kdaBenchmark($kills, $deaths, $assists),
            ], fn (array $distribution) => $distribution !== []);
        });
    }

    /**
     * Aggregated stats and the latest matches for one hero.
     *
     * @return array<string, mixed>
     */
    public function getPlayerHeroStats(int $accountId, int $heroId): array
    {
        $ttl = (int) config('stats.cache.dota_player_hero', 1800);
        $cacheKey = "opendota:player-hero:v2:{$accountId}:{$heroId}";

        return Cache::remember($cacheKey, $ttl, function () use ($accountId, $heroId) {
            $heroes = $this->heroStats();
            $matches = $this->transformMatches(
                $this->optionalGet("players/{$accountId}/matches", [
                    'hero_id' => $heroId,
                    'limit' => 100,
                ]),
                $heroes,
            );

            // OpenDota's list endpoint no longer includes GPM/XPM. Enrich a
            // bounded sample from match details instead of spending 100 calls.
            $matches = $this->enrichHeroMatchSample($matches, $accountId);

            $hero = $heroes[$heroId] ?? [
                'id' => $heroId,
                'name' => 'Герой #'.$heroId,
                'image' => null,
                'icon' => null,
            ];

            $games = count($matches);
            $wins = count(array_filter($matches, fn (array $match) => $match['win']));
            $kills = array_sum(array_column($matches, 'kills'));
            $deaths = array_sum(array_column($matches, 'deaths'));
            $assists = array_sum(array_column($matches, 'assists'));
            $gpmValues = array_values(array_filter(array_column($matches, 'gpm'), fn (int $value) => $value > 0));
            $xpmValues = array_values(array_filter(array_column($matches, 'xpm'), fn (int $value) => $value > 0));

            return [
                'hero' => $hero,
                'summary' => [
                    'games' => $games,
                    'wins' => $wins,
                    'losses' => $games - $wins,
                    'winrate' => $games > 0 ? round($wins / $games * 100, 1) : null,
                    'kills' => $games > 0 ? round($kills / $games, 1) : null,
                    'deaths' => $games > 0 ? round($deaths / $games, 1) : null,
                    'assists' => $games > 0 ? round($assists / $games, 1) : null,
                    'kda' => $games > 0 ? round(($kills + $assists) / max($deaths, 1), 2) : null,
                    'gpm' => $gpmValues !== [] ? (int) round(array_sum($gpmValues) / count($gpmValues)) : null,
                    'xpm' => $xpmValues !== [] ? (int) round(array_sum($xpmValues) / count($xpmValues)) : null,
                    'gpm_games' => count($gpmValues),
                    'xpm_games' => count($xpmValues),
                ],
                'matches' => array_slice($matches, 0, 10),
                'cached_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Full match history for the dedicated matches tab.
     *
     * @param  array{hero_id?: int, result?: string, mode?: string, role?: string, date?: string}  $filters
     * @return array{player: array<string, mixed>, rows: array<int, array<string, mixed>>, total: int, heroes: array<int, array<string, mixed>>, filters: array<string, mixed>}
     */
    public function playerMatches(int $accountId, int $page, int $perPage, array $filters = []): array
    {
        $profile = $this->player($accountId);
        $servedFromFreshDb = (bool) ($profile['served_from_fresh_db'] ?? false);
        $rows = $profile['match_history'] ?? [];

        if ($rows === [] && ! $servedFromFreshDb) {
            $heroes = $this->heroStats();
            $rows = $this->transformHistory($this->fetchMatchHistory($accountId), $heroes);
        }

        $known = [];

        foreach ($profile['matches'] ?? [] as $match) {
            if (! empty($match['match_id'])) {
                $known[(int) $match['match_id']] = $match;
            }
        }

        foreach ($rows as &$row) {
            $detail = $known[(int) ($row['match_id'] ?? 0)] ?? null;

            if (is_array($detail)) {
                foreach ([
                    'kills', 'deaths', 'assists', 'gpm', 'xpm', 'duration', 'duration_label',
                    'net_worth', 'level', 'items', 'lobby_type', 'game_mode',
                    'lobby_type_label', 'game_mode_label', 'match_type_label', 'is_ranked',
                ] as $field) {
                    $value = $detail[$field] ?? null;

                    if ($value !== null && $value !== [] && $value !== false) {
                        $row[$field] = $value;
                    }
                }
            }

            $row['kda'] = round(
                ((int) ($row['kills'] ?? 0) + (int) ($row['assists'] ?? 0)) / max((int) ($row['deaths'] ?? 0), 1),
                2
            );
        }
        unset($row);

        $heroId = (int) ($filters['hero_id'] ?? 0);
        $result = (string) ($filters['result'] ?? '');
        $mode = (string) ($filters['mode'] ?? '');
        $role = (string) ($filters['role'] ?? '');
        $date = (string) ($filters['date'] ?? '');
        $dateRange = null;

        if ($date !== '') {
            try {
                $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

                if ($day !== false && $day->format('Y-m-d') === $date) {
                    $dateRange = [$day->getTimestamp(), $day->addDay()->getTimestamp()];
                }
            } catch (\Throwable) {
                $dateRange = null;
            }
        }

        $rows = array_values(array_filter($rows, function (array $row) use ($heroId, $result, $mode, $role, $dateRange) {
            if ($heroId > 0 && (int) ($row['hero_id'] ?? 0) !== $heroId) {
                return false;
            }

            if ($result === 'win' && ! ($row['win'] ?? false)) {
                return false;
            }

            if ($result === 'loss' && ($row['win'] ?? false)) {
                return false;
            }

            $gameMode = (int) ($row['game_mode'] ?? 0);

            if ($mode === 'ranked' && ! $this->isRankedMode($gameMode)) {
                return false;
            }

            if ($mode === 'turbo' && $gameMode !== 23) {
                return false;
            }

            if ($mode === 'normal' && ($this->isRankedMode($gameMode) || $gameMode === 23)) {
                return false;
            }

            if ($role !== '' && ($row['role_key'] ?? null) !== $role) {
                return false;
            }

            if ($dateRange !== null) {
                $startTime = isset($row['start_time']) && is_numeric($row['start_time'])
                    ? (int) $row['start_time']
                    : null;

                if ($startTime === null && is_string($row['started_at'] ?? null)) {
                    try {
                        $startTime = CarbonImmutable::parse($row['started_at'])->getTimestamp();
                    } catch (\Throwable) {
                        $startTime = null;
                    }
                }

                if ($startTime === null || $startTime < $dateRange[0] || $startTime >= $dateRange[1]) {
                    return false;
                }
            }

            return true;
        }));

        usort($rows, fn (array $a, array $b) => strcmp((string) ($b['started_at'] ?? ''), (string) ($a['started_at'] ?? '')));

        $total = count($rows);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($rows, $offset, $perPage);
        $pageRows = $this->enrichMatches($pageRows, $accountId, count($pageRows), ! $servedFromFreshDb);

        return [
            'player' => $profile,
            'rows' => $pageRows,
            'total' => $total,
            'heroes' => $profile['heroes'] ?? [],
            'filters' => [
                'hero_id' => $heroId,
                'result' => $result,
                'mode' => $mode,
                'role' => $role,
                'date' => $date,
            ],
        ];
    }

    /**
     * Lifetime summary grouped by lobby, game mode and faction.
     *
     * The match history is already part of the PlayerProfile JSON, so this
     * method never calls an external API. The aggregated table gets its own
     * 24-hour Redis entry keyed by the history fingerprint.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array{total: int, wins: int, losses: int, winrate: float|null, groups: array<int, array<string, mixed>>}
     */
    public function lifetimeStats(int $accountId, array $history): array
    {
        $ttl = (int) config('stats.cache.dota_lifetime_stats', 86400);
        $fingerprint = sha1(implode(':', array_map(
            fn (array $match) => (string) ($match['match_id'] ?? '').':'.(string) ($match['win'] ?? ''),
            $history,
        )));
        $cacheKey = "opendota:lifetime-stats:v1:{$accountId}:{$fingerprint}";

        return Cache::remember($cacheKey, $ttl, fn (): array => $this->aggregateLifetimeStats($history));
    }

    /**
     * Personal records from OpenDota, cached for 24 hours.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array<string, mixed>>
     */
    public function playerRecords(int $accountId, array $history = [], bool $rankedOnly = false): array
    {
        $scope = $rankedOnly ? 'ranked' : 'all';

        if ($rankedOnly) {
            $history = array_values(array_filter(
                $history,
                fn (array $match): bool => (int) ($match['lobby_type'] ?? 0) === 7,
            ));
        }

        $ttl = (int) config('stats.cache.dota_records', 86400);
        $cacheKey = "opendota:records:v6:{$accountId}:{$scope}";
        $fingerprint = sha1($scope.':'.implode(':', array_map(
            fn (array $match) => (string) ($match['match_id'] ?? '').'@'.(string) ($match['started_at'] ?? ($match['start_time'] ?? '')),
            $history,
        )));

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && ($cached['_fingerprint'] ?? null) === $fingerprint) {
            return is_array($cached['records'] ?? null) ? $cached['records'] : [];
        }

        $derived = $history !== [] ? $this->recordsFromHistory($history, []) : [];
        $records = $this->orderRecords($derived);

        Cache::put($cacheKey, [
            '_fingerprint' => $fingerprint,
            'records' => $records,
        ], $ttl);

        return $records;
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{total: int, wins: int, losses: int, winrate: float|null, groups: array<int, array<string, mixed>>}
     */
    private function aggregateLifetimeStats(array $history): array
    {
        $total = count($history);
        $wins = count(array_filter($history, fn (array $match) => (bool) ($match['win'] ?? false)));

        $buckets = ['mode' => [], 'side' => []];
        $sumKills = 0;
        $sumDeaths = 0;
        $sumAssists = 0;
        $sumGpm = 0;
        $sumXpm = 0;
        $counted = 0;

        foreach ($history as $match) {
            if (! is_array($match)) {
                continue;
            }

            $mode = (string) ($match['game_mode_label'] ?? 'Неизвестно');
            $side = ($match['is_radiant'] ?? ((int) ($match['player_slot'] ?? 0) < 128)) ? 'Radiant' : 'Dire';
            $win = (bool) ($match['win'] ?? false);

            foreach ([['mode', $mode], ['side', $side]] as [$group, $label]) {
                $buckets[$group][$label] ??= ['label' => $label, 'games' => 0, 'wins' => 0, 'losses' => 0];
                $buckets[$group][$label]['games']++;
                if ($win) {
                    $buckets[$group][$label]['wins']++;
                } else {
                    $buckets[$group][$label]['losses']++;
                }
            }

            $sumKills += (int) ($match['kills'] ?? 0);
            $sumDeaths += (int) ($match['deaths'] ?? 0);
            $sumAssists += (int) ($match['assists'] ?? 0);
            $sumGpm += (int) ($match['gpm'] ?? $match['gold_per_min'] ?? 0);
            $sumXpm += (int) ($match['xpm'] ?? $match['xp_per_min'] ?? 0);
            $counted++;
        }

        $rows = function (array $bucket): array {
            $rows = array_values($bucket);

            foreach ($rows as &$row) {
                $row['winrate'] = $row['games'] > 0
                    ? round($row['wins'] / $row['games'] * 100, 1)
                    : null;
            }
            unset($row);

            usort($rows, fn (array $left, array $right) => [$right['games'], $left['label']] <=> [$left['games'], $right['label']]);

            return $rows;
        };

        $avgKda = $counted > 0 ? round(($sumKills + $sumAssists) / max(1, $sumDeaths), 2) : null;
        $avgGpm = $counted > 0 ? (int) round($sumGpm / $counted) : null;
        $avgXpm = $counted > 0 ? (int) round($sumXpm / $counted) : null;

        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $total - $wins,
            'winrate' => $total > 0 ? round($wins / $total * 100, 1) : null,
            'averages' => [
                'kda' => $avgKda,
                'gpm' => $avgGpm,
                'xpm' => $avgXpm,
            ],
            'groups' => [
                ['key' => 'mode', 'title' => __('ui.by_game_mode'), 'rows' => $rows($buckets['mode'])],
                ['key' => 'side', 'title' => __('ui.by_faction'), 'rows' => $rows($buckets['side'])],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function lifetimeLobbyLabel(array $match): string
    {
        $lobbyType = isset($match['lobby_type']) && is_numeric($match['lobby_type'])
            ? (int) $match['lobby_type']
            : null;
        $gameMode = isset($match['game_mode']) && is_numeric($match['game_mode'])
            ? (int) $match['game_mode']
            : null;

        return $this->matchTypeLabel($lobbyType, $gameMode);
    }

    /**
     * @param  array<int, mixed>  $records
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function transformRecords(array $records, array $heroes): array
    {
        $rows = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $name = strtoupper((string) ($record['name'] ?? $record['record_name'] ?? ''));
            $value = $record['value'] ?? null;

            if ($name === '' || ! is_numeric($value)) {
                continue;
            }

            $heroId = (int) ($record['hero_id'] ?? 0);
            $hero = $heroes[$heroId] ?? [];
            $startTime = (int) ($record['start_time'] ?? 0);
            $value = (int) $value;

            $row = [
                'key' => $name,
                'label' => self::recordLabel($name),
                'value' => $value,
                'value_label' => $this->recordValueLabel($name, $value),
                'hero_id' => $heroId > 0 ? $heroId : null,
                'hero_name' => $hero['name'] ?? null,
                'hero_image' => $hero['image'] ?? null,
                'hero_icon' => $hero['icon'] ?? null,
                'match_id' => isset($record['match_id']) ? (int) $record['match_id'] : null,
                'played_at' => $startTime > 0 ? CarbonImmutable::createFromTimestampUTC($startTime)->toIso8601String() : null,
                'is_team' => (bool) ($record['is_team'] ?? false),
            ];

            // OpenDota may report one record several times; keep the best value.
            $existing = $rows[$row['key']] ?? null;

            if ($existing === null || $row['value'] > $existing['value']) {
                $rows[$row['key']] = $row;
            }
        }

        return $this->orderRecords(array_values($rows));
    }

    /**
     * Build the core record tiles from the cached match history.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function recordsFromHistory(array $history, array $heroes): array
    {
        $best = [];

        foreach ($history as $match) {
            if (! is_array($match)) {
                continue;
            }

            foreach (self::HISTORY_RECORD_FIELDS as $name => $field) {
                $value = (int) ($match[$field] ?? 0);

                if ($value <= 0) {
                    continue;
                }

                $current = $best[$name]['value'] ?? null;
                $isBetter = $name === 'SHORTEST_DURATION'
                    ? ($current === null || $value < $current)
                    : ($current === null || $value > $current);

                if ($isBetter) {
                    $best[$name] = $this->historyRecordRow($name, $value, $match, $heroes);
                }
            }
        }

        foreach ($this->recordsFromStreaks($history, $heroes) as $row) {
            $best[$row['key']] = $row;
        }

        return $this->orderRecords(array_values($best));
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<string, mixed>
     */
    private function historyRecordRow(string $name, int $value, array $match, array $heroes): array
    {
        $heroId = (int) ($match['hero_id'] ?? 0);
        $hero = $heroes[$heroId] ?? [];

        return [
            'key' => $name,
            'label' => self::recordLabel($name),
            'value' => $value,
            'value_label' => $this->recordValueLabel($name, $value),
            'hero_id' => $heroId > 0 ? $heroId : null,
            'hero_name' => $hero['name'] ?? ($match['hero_name'] ?? null),
            'hero_image' => $hero['image'] ?? ($match['hero_image'] ?? null),
            'hero_icon' => $hero['icon'] ?? ($match['hero_icon'] ?? null),
            'match_id' => (int) ($match['match_id'] ?? 0) ?: null,
            'played_at' => $match['started_at'] ?? null,
            'is_team' => false,
        ];
    }

    /**
     * Longest run of consecutive wins/losses in the cached match history.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function recordsFromStreaks(array $history, array $heroes): array
    {
        $chronological = array_values(array_filter(
            $history,
            fn (mixed $match): bool => is_array($match) && ! empty($match['started_at']),
        ));

        usort($chronological, fn (array $left, array $right): int => strcmp(
            (string) $left['started_at'],
            (string) $right['started_at'],
        ));

        $best = [];
        $wins = 0;
        $losses = 0;

        foreach ($chronological as $match) {
            if ((bool) ($match['win'] ?? false)) {
                $wins++;
                $losses = 0;
                $key = 'LONGEST_WIN_STREAK';
                $value = $wins;
                if ($value > ($best[$key]['value'] ?? 0)) {
                    $best[$key] = $this->historyRecordRow($key, $value, $match, $heroes);
                }
            } else {
                $losses++;
                $wins = 0;
            }
        }

        return array_values($best);
    }

    private function recordValueLabel(string $name, int $value): string
    {
        return match ($name) {
            'DURATION', 'SHORTEST_DURATION' => $this->durationLabel($value),
            'LONGEST_WIN_STREAK' => $this->streakLabel($value, 'победа', 'победы', 'побед'),
            default => number_format($value, 0, '.', ' '),
        };
    }

    private function streakLabel(int $value, string $one, string $few, string $many): string
    {
        $lastDigit = $value % 10;
        $lastTwoDigits = $value % 100;

        $word = match (true) {
            $lastDigit === 1 && $lastTwoDigits !== 11 => $one,
            $lastDigit >= 2 && $lastDigit <= 4 && ($lastTwoDigits < 12 || $lastTwoDigits > 14) => $few,
            default => $many,
        };

        return $value.' '.$word;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function orderRecords(array $rows): array
    {
        $order = array_keys(self::recordLabels());

        usort($rows, function (array $left, array $right) use ($order): int {
            $leftIndex = array_search($left['key'], $order, true);
            $rightIndex = array_search($right['key'], $order, true);

            return ($leftIndex === false ? PHP_INT_MAX : $leftIndex) <=> ($rightIndex === false ? PHP_INT_MAX : $rightIndex);
        });

        return $rows;
    }

    private function cacheKey(int $accountId): string
    {
        return "opendota:player:v3:{$accountId}";
    }

    private function playerHeaderCacheKey(int $accountId): string
    {
        return "opendota:player-header:v1:{$accountId}";
    }

    /**
     * @param  array<string, mixed>  $profileResponse
     * @return array<string, mixed>
     */
    private function playerIdentity(array $profileResponse, int $accountId = 0): array
    {
        $profile = is_array($profileResponse['profile'] ?? null) ? $profileResponse['profile'] : [];
        $nickname = $profile['personaname'] ?? null;

        return [
            'account_id' => (int) ($profile['account_id'] ?? $accountId),
            'steam_id' => (string) ($profile['steamid'] ?? ''),
            'nickname' => $nickname ?: 'Анонимный игрок',
            'avatar' => $profile['avatarfull'] ?? $profile['avatarmedium'] ?? $profile['avatar'] ?? null,
            'profile_url' => $profile['profileurl'] ?? null,
            'is_private' => $profile === [],
            'rank_tier' => isset($profileResponse['rank_tier']) && is_numeric($profileResponse['rank_tier'])
                ? (int) $profileResponse['rank_tier']
                : null,
            'leaderboard_rank' => isset($profileResponse['leaderboard_rank'])
                ? (int) $profileResponse['leaderboard_rank']
                : null,
        ];
    }

    /**
     * Fetch an optional endpoint and return an empty array on failure.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function optionalGet(string $path, array $query = []): array
    {
        try {
            return $this->client->get($path, $query);
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
     * Request the full match history. Prefer STRATZ (no OpenDota daily limit),
     * fallback to OpenDota pagination when STRATZ is unavailable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchMatchHistory(int $accountId): array
    {
        try {
            $stratzMatches = $this->stratz->playerMatches($accountId);
            if (is_array($stratzMatches) && $stratzMatches !== []) {
                Log::info('Match history loaded from STRATZ', ['account_id' => $accountId, 'total' => count($stratzMatches)]);
                return $stratzMatches;
            }
        } catch (\Throwable $e) {
            Log::warning('STRATZ match history failed, falling back to OpenDota', ['account_id' => $accountId, 'message' => $e->getMessage()]);
        }

        return $this->fetchMatchHistoryFromOpenDota($accountId);
    }

    /**
     * OpenDota fallback: follow less_than_match_id cursor until exhausted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchMatchHistoryFromOpenDota(int $accountId): array
    {
        $allMatches = [];
        $lessThanMatchId = null;
        $hasMore = true;

        while ($hasMore) {
            $query = [
                'limit' => 500,
                'significant' => 0,
            ];

            if ($lessThanMatchId !== null) {
                $query['less_than_match_id'] = $lessThanMatchId;
            }

            try {
                $batch = $this->optionalGet("players/{$accountId}/matches", $query);
            } catch (ApiUnavailableException $e) {
                Log::error('Ошибка API OpenDota при загрузке матчей: '.$e->getMessage());
                break;
            }

            if (! is_array($batch) || empty($batch)) {
                $hasMore = false;
                break;
            }

            $allMatches = array_merge($allMatches, $batch);
            Log::info('Загружено матчей: '.count($allMatches).'. Текущая пачка: '.count($batch));

            if (count($batch) < 500) {
                $hasMore = false;
                break;
            }

            $lastMatch = end($batch);
            $lessThanMatchId = is_array($lastMatch) ? ($lastMatch['match_id'] ?? null) : null;

            if (! $lessThanMatchId) {
                $hasMore = false;
                break;
            }

            usleep(250000);
        }

        $uniqueMatches = [];
        foreach ($allMatches as $match) {
            if (! is_array($match)) {
                continue;
            }
            $matchId = $match['match_id'] ?? null;
            if ($matchId !== null) {
                $uniqueMatches[(string) $matchId] = $match;
            }
        }

        $result = array_values($uniqueMatches);
        usort($result, function ($a, $b) {
            return (int) ($b['start_time'] ?? 0) <=> (int) ($a['start_time'] ?? 0);
        });

        $oldest = null;
        foreach ($result as $m) {
            $st = isset($m['start_time']) && is_numeric($m['start_time']) ? (int) $m['start_time'] : 0;
            if ($st > 0 && ($oldest === null || $st < $oldest)) {
                $oldest = $st;
            }
        }

        Log::info('ИТОГО загружено матчей: '.count($result), [
            'account_id' => $accountId,
            'turbo_game_mode_23' => count(array_filter($result, fn ($m) => is_array($m) && (int) ($m['game_mode'] ?? -1) === 23)),
            'oldest_start_time' => $oldest,
            'oldest_date' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
        ]);

        return $result;
    }

    /**
     * Normalize one OpenDota benchmark distribution.
     *
     * @return array<int, array{percentile: float, value: float}>
     */
    private function normalizeBenchmark(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $points = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_numeric($row['percentile'] ?? null) || ! is_numeric($row['value'] ?? null)) {
                continue;
            }

            $points[] = [
                'percentile' => (float) $row['percentile'],
                'value' => (float) $row['value'],
            ];
        }

        usort($points, fn (array $left, array $right) => $left['percentile'] <=> $right['percentile']);

        return $points;
    }

    /**
     * Derive a KDA benchmark from the kills/deaths/assists distributions.
     *
     * @param  array<int, array{percentile: float, value: float}>  $kills
     * @param  array<int, array{percentile: float, value: float}>  $deaths
     * @param  array<int, array{percentile: float, value: float}>  $assists
     * @return array<int, array{percentile: float, value: float}>
     */
    private function kdaBenchmark(array $kills, array $deaths, array $assists): array
    {
        if ($kills === [] || $deaths === [] || $assists === []) {
            return [];
        }

        $deathsByPercentile = [];
        $assistsByPercentile = [];

        foreach ($deaths as $row) {
            $deathsByPercentile[(string) $row['percentile']] = $row['value'];
        }

        foreach ($assists as $row) {
            $assistsByPercentile[(string) $row['percentile']] = $row['value'];
        }

        $points = [];

        foreach ($kills as $row) {
            $key = (string) $row['percentile'];
            $deathsAtPercentile = $deathsByPercentile[$key] ?? null;
            $assistsAtPercentile = $assistsByPercentile[$key] ?? null;

            if ($deathsAtPercentile === null || $assistsAtPercentile === null || $deathsAtPercentile <= 0) {
                continue;
            }

            $points[] = [
                'percentile' => $row['percentile'],
                'value' => round(($row['value'] + $assistsAtPercentile) / $deathsAtPercentile, 2),
            ];
        }

        return $points;
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

            $short = isset($hero['name']) ? str_replace('npc_dota_hero_', '', (string) $hero['name']) : '';
            $reactImage = $short !== ''
                ? 'https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/'.$short.'.png'
                : (isset($hero['img']) ? 'https://cdn.cloudflare.steamstatic.com'.$hero['img'] : null);
            $localRel = $short !== '' ? 'images/heroes/'.$short.'.png' : null;
            $localImage = $localRel && is_file(public_path($localRel)) ? '/'.$localRel : $reactImage;
            $iconCdn = isset($hero['icon']) ? 'https://cdn.cloudflare.steamstatic.com'.$hero['icon'] : null;
            $minimapCdn = $short !== '' ? 'https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/icons/'.$short.'.png' : $iconCdn;
            $minimapRel = $short !== '' ? 'images/heroes/minimap/'.$short.'.png' : null;
            $minimapIcon = $minimapRel && is_file(public_path($minimapRel)) ? '/'.$minimapRel : ($minimapCdn ?: $iconCdn);

            $map[(int) $hero['id']] = [
                'id' => (int) $hero['id'],
                'name' => $hero['localized_name'] ?? 'Hero #'.$hero['id'],
                'image' => $localImage,
                'image_cdn' => $reactImage,
                'icon' => $iconCdn,
                'minimap_icon' => $minimapIcon,
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
     * @return array<string, array{id: int, name: string, image: string, cost: int}>
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

            $itemKey = (string) $key;
            $cdnImage = isset($item['img'])
                ? 'https://cdn.cloudflare.steamstatic.com'.$item['img']
                : $this->itemImage($itemKey);
            $localRel = 'images/items/'.$itemKey.'.png';
            $localImage = is_file(public_path($localRel)) ? '/'.$localRel : $cdnImage;

            $map[$itemKey] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) $item['dname'],
                'image' => $localImage,
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
        $stratzPositions = $this->stratz->matchPositions((int) ($match['match_id'] ?? 0));

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
            $stratzPlayer = $stratzPositions[$accountId] ?? null;
            $rankTier = isset($stratzPlayer['rank_tier']) && is_numeric($stratzPlayer['rank_tier'])
                ? (int) $stratzPlayer['rank_tier']
                : (isset($player['rank_tier']) && is_numeric($player['rank_tier']) ? (int) $player['rank_tier'] : null);
            $rank = $this->rank($rankTier);

            $row = [
                'account_id' => $accountId > 0 ? $accountId : null,
                'player_slot' => $slot,
                'party_id' => isset($player['party_id']) && is_numeric($player['party_id'])
                    ? (int) $player['party_id']
                    : null,
                'party_size' => isset($player['party_size']) && is_numeric($player['party_size'])
                    ? (int) $player['party_size']
                    : null,
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
                'tower_damage' => (int) ($player['tower_damage'] ?? 0),
                'hero_healing' => (int) ($player['hero_healing'] ?? 0),
                'last_hits' => (int) ($player['last_hits'] ?? 0),
                'denies' => (int) ($player['denies'] ?? 0),
                'net_worth' => (int) ($player['net_worth'] ?? 0),
                'rank_tier' => $rankTier,
                'rank_label' => $rank['label'],
                'rank_medal' => $rank['medal'],
                'role' => $this->matchRole($player, $stratzPlayer),
                'impact' => isset($stratzPlayer['imp'])
                    ? (float) $stratzPlayer['imp']
                    : null,
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
            'match_type_label' => $this->matchTypeLabel($lobbyType, $gameMode),
            'is_ranked' => $this->isRankedMode($gameMode),
            'radiant_score' => $radiantScore,
            'dire_score' => $direScore,
            'players' => [
                'radiant' => $radiant,
                'dire' => $dire,
            ],
            'picks_bans' => $this->transformPicksBans($match['picks_bans'] ?? null, $heroes),
            'net_worth_chart' => $this->netWorthChart($radiant, $dire),
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $player
     * @param  array{position: int|null, imp: float|null}|null  $stratz
     * @return array{label: string|null, name: string|null, source: string|null}
     */
    private function matchRole(array $player, ?array $stratz = null): array
    {
        $stratzPosition = isset($stratz['position']) && is_numeric($stratz['position'])
            ? (int) $stratz['position']
            : 0;

        if (isset(self::POSITION_ROLES[$stratzPosition])) {
            return self::POSITION_ROLES[$stratzPosition] + ['source' => 'STRATZ'];
        }

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

    private function isRankedMode(int $gameMode): bool
    {
        return in_array($gameMode, [16, 22], true);
    }

    private function gameModeLabel(?int $mode): ?string
    {
        return match ($mode) {
            1 => __('ui.mode_1'),
            2 => __('ui.mode_2'),
            4 => __('ui.mode_4'),
            5 => __('ui.mode_5'),
            16 => __('ui.mode_16'),
            18 => __('ui.mode_18'),
            22 => __('ui.mode_22'),
            23 => __('ui.mode_23'),
            default => null,
        };
    }

    private function lobbyTypeLabel(?int $lobbyType): ?string
    {
        return $lobbyType !== null ? (self::LOBBY_TYPES[$lobbyType] ?? null) : null;
    }

    private function matchTypeLabel(?int $lobbyType, ?int $gameMode): string
    {
        // The match table must show the game mode (All Pick, Turbo, ...), not
        // the lobby type. Lobby labels remain only as a fallback for unknown
        // modes and for the "Ranked" badge on the match detail page.
        return $this->gameModeLabel($gameMode)
            ?? $this->lobbyTypeLabel($lobbyType)
            ?? 'Normal';
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function partyLabel(array $match): string
    {
        $partySize = isset($match['party_size']) && is_numeric($match['party_size'])
            ? (int) $match['party_size']
            : 0;

        if ($partySize > 1
            || ! empty($match['is_party'])
            || (int) ($match['party_id'] ?? 0) > 0) {
            return 'Party';
        }

        return 'Solo';
    }

    /**
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function transformPicksBans(mixed $picksBans, array $heroes): array
    {
        if (! is_array($picksBans)) {
            return [];
        }

        $rows = [];

        foreach ($picksBans as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $heroId = (int) ($entry['hero_id'] ?? 0);

            if ($heroId <= 0) {
                continue;
            }

            $hero = $heroes[$heroId] ?? [
                'id' => $heroId,
                'name' => 'Hero #'.$heroId,
                'image' => null,
            ];
            $team = isset($entry['team']) && is_numeric($entry['team']) ? (int) $entry['team'] : null;

            $rows[] = [
                'is_pick' => (bool) ($entry['is_pick'] ?? false),
                'team' => $team,
                'team_label' => $team === 0 ? 'Radiant' : ($team === 1 ? 'Dire' : null),
                'hero_id' => $heroId,
                'hero_name' => $hero['name'] ?? 'Hero #'.$heroId,
                'hero_image' => $hero['image'] ?? null,
                'order' => isset($entry['order']) && is_numeric($entry['order']) ? (int) $entry['order'] : null,
            ];
        }

        usort($rows, fn (array $left, array $right) => ($left['order'] ?? 0) <=> ($right['order'] ?? 0));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function roleKey(array $match): ?string
    {
        $position = isset($match['position_est']) && is_numeric($match['position_est'])
            ? (int) $match['position_est']
            : 0;

        if ($position < 1 && isset($match['position']) && is_numeric($match['position'])) {
            $position = (int) $match['position'];
        }

        $role = match ($position) {
            1 => 'carry',
            2 => 'mid',
            3 => 'offlane',
            4 => 'support',
            5 => 'hard_support',
            default => null,
        };

        if ($role !== null) {
            return $role;
        }

        $lane = isset($match['lane_role']) && is_numeric($match['lane_role'])
            ? (int) $match['lane_role']
            : 0;

        return match ($lane) {
            1 => 'carry',
            2 => 'mid',
            3 => 'offlane',
            4 => 'jungle',
            default => null,
        };
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
                'lobby_type' => isset($match['lobby_type']) ? (int) $match['lobby_type'] : null,
                'game_mode' => isset($match['game_mode']) ? (int) $match['game_mode'] : null,
                'lobby_type_label' => $this->lobbyTypeLabel(isset($match['lobby_type']) ? (int) $match['lobby_type'] : null),
                'game_mode_label' => $this->gameModeLabel(isset($match['game_mode']) ? (int) $match['game_mode'] : null),
                'match_type_label' => $this->matchTypeLabel(
                    isset($match['lobby_type']) ? (int) $match['lobby_type'] : null,
                    isset($match['game_mode']) ? (int) $match['game_mode'] : null,
                ),
                'is_ranked' => $this->isRankedMode((int) ($match['game_mode'] ?? 0)),
                'party_label' => $this->partyLabel($match),
                'party_id' => isset($match['party_id']) && is_numeric($match['party_id']) ? (int) $match['party_id'] : null,
                'party_size' => isset($match['party_size']) && is_numeric($match['party_size']) ? (int) $match['party_size'] : null,
                'role_key' => $this->roleKey($match),
                'lane_role' => isset($match['lane_role']) ? (int) $match['lane_role'] : null,
                'level' => null,
                'net_worth' => null,
                'item_0' => null,
                'item_1' => null,
                'item_2' => null,
                'item_3' => null,
                'item_4' => null,
                'item_5' => null,
                'items' => array_fill(0, 6, null),
            ];
        }

        return $transformed;
    }

    /**
     * The recent-matches feed does not include level, net worth or inventory.
     * Fetch a bounded number of match details and cache a slim per-player row.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<int, array<string, mixed>>
     */
    private function enrichMatches(array $matches, int $accountId, int $limit, bool $allowExternalFetch = true): array
    {
        $limit = min($limit, count($matches));

        if ($limit === 0) {
            return $matches;
        }

        $itemsById = [];
        $itemStats = [];

        if ($allowExternalFetch) {
            try {
                $itemStats = $this->itemStats();
            } catch (ExternalApiException $exception) {
                Log::info('OpenDota item metadata failed for profile', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($itemStats as $item) {
            $itemId = (int) ($item['id'] ?? 0);

            if ($itemId > 0) {
                $itemsById[$itemId] = $item;
            }
        }

        $ttl = (int) config('stats.cache.dota_match', 3600);

        for ($index = 0; $index < $limit; $index++) {
            $matchId = (int) ($matches[$index]['match_id'] ?? 0);
            $details = $this->matchPlayerSummary($matchId, $accountId, $ttl, $allowExternalFetch);

            if ($details === null) {
                continue;
            }

            if ($details['level'] > 0) {
                $matches[$index]['level'] = $details['level'];
            }

            if ($details['net_worth'] > 0) {
                $matches[$index]['net_worth'] = $details['net_worth'];
            }

            if ($details['gold_per_min'] > 0) {
                $matches[$index]['gpm'] = $details['gold_per_min'];
            }

            if ($details['xp_per_min'] > 0) {
                $matches[$index]['xpm'] = $details['xp_per_min'];
            }

            $slots = [];

            for ($slot = 0; $slot < 6; $slot++) {
                $itemId = (int) ($details['item_'.$slot] ?? 0);
                $item = null;

                if ($itemId > 0) {
                    $meta = $itemsById[$itemId] ?? null;
                    $item = [
                        'id' => $itemId,
                        'name' => $meta['name'] ?? 'Item #'.$itemId,
                        'image' => $meta['image'] ?? null,
                    ];
                }

                $matches[$index]['item_'.$slot] = $item;
                $slots[] = $item;
            }

            $matches[$index]['items'] = $slots;
        }

        return $matches;
    }

    /**
     * @return array{level: int, net_worth: int, gold_per_min: int, xp_per_min: int, item_0: int, item_1: int, item_2: int, item_3: int, item_4: int, item_5: int}|null
     */
    private function matchPlayerSummary(int $matchId, int $accountId, int $ttl, bool $allowExternalFetch = true): ?array
    {
        if ($matchId <= 0) {
            return null;
        }

        $cacheKey = "opendota:match-player:v3:{$matchId}:{$accountId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if (! $allowExternalFetch) {
            return null;
        }

        try {
            $match = $this->client->get("matches/{$matchId}");
        } catch (ApiUnavailableException|ExternalApiException|PlayerNotFoundException $exception) {
            Log::warning('OpenDota match details are unavailable', [
                'match_id' => $matchId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $peerHeroes = [];

        foreach (($match['players'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidateAccount = (int) ($candidate['account_id'] ?? 0);
            $candidateHero = (int) ($candidate['hero_id'] ?? 0);

            if ($candidateAccount > 0 && $candidateHero > 0) {
                $peerHeroes[$candidateAccount][$candidateHero] = ($peerHeroes[$candidateAccount][$candidateHero] ?? 0) + 1;
            }
        }

        Cache::put("opendota:match-peer-heroes:v1:{$matchId}", $peerHeroes, $ttl);

        $player = null;

        foreach (($match['players'] ?? []) as $candidate) {
            if (is_array($candidate) && (int) ($candidate['account_id'] ?? 0) === $accountId) {
                $player = $candidate;
                break;
            }
        }

        if (! is_array($player)) {
            return null;
        }

        $summary = [
            'level' => (int) ($player['level'] ?? 0),
            'net_worth' => (int) ($player['net_worth'] ?? 0),
            'hero_damage' => (int) ($player['hero_damage'] ?? 0),
            'gold_per_min' => (int) ($player['gold_per_min'] ?? 0),
            'xp_per_min' => (int) ($player['xp_per_min'] ?? 0),
            'item_0' => (int) ($player['item_0'] ?? 0),
            'item_1' => (int) ($player['item_1'] ?? 0),
            'item_2' => (int) ($player['item_2'] ?? 0),
            'item_3' => (int) ($player['item_3'] ?? 0),
            'item_4' => (int) ($player['item_4'] ?? 0),
            'item_5' => (int) ($player['item_5'] ?? 0),
        ];

        Cache::put($cacheKey, $summary, $ttl);

        return $summary;
    }

    /**
     * Fill GPM/XPM for the most recent hero matches from cached match details.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<int, array<string, mixed>>
     */
    private function enrichHeroMatchSample(array $matches, int $accountId, bool $allowExternalFetch = true): array
    {
        $limit = min(self::RECENT_MATCH_DETAILS, count($matches));

        if ($limit === 0) {
            return $matches;
        }

        $ttl = (int) config('stats.cache.dota_match', 3600);

        for ($index = 0; $index < $limit; $index++) {
            if (($matches[$index]['gpm'] ?? 0) > 0 && ($matches[$index]['xpm'] ?? 0) > 0) {
                continue;
            }

            $details = $this->matchPlayerSummary(
                (int) ($matches[$index]['match_id'] ?? 0),
                $accountId,
                $ttl,
                $allowExternalFetch,
            );

            if ($details === null) {
                continue;
            }

            if ($details['gold_per_min'] > 0) {
                $matches[$index]['gpm'] = $details['gold_per_min'];
            }

            if ($details['xp_per_min'] > 0) {
                $matches[$index]['xpm'] = $details['xp_per_min'];
            }

            if ($details['level'] > 0) {
                $matches[$index]['level'] = $details['level'];
            }

            if ($details['net_worth'] > 0) {
                $matches[$index]['net_worth'] = $details['net_worth'];
            }

            if ($details['hero_damage'] > 0) {
                $matches[$index]['hero_damage'] = $details['hero_damage'];
            }
        }

        return $matches;
    }

    /**
     * Slim match history used by the activity heatmap and hero usage chart.
     *
     * @param  array<int, mixed>  $matches
     * @param  array<int, array<string, mixed>>  $heroes
     * @return array<int, array<string, mixed>>
     */
    private function transformHistory(array $matches, array $heroes): array
    {
        $rows = [];

        foreach ($matches as $match) {
            if (! is_array($match) || ! isset($match['hero_id'], $match['player_slot'])) {
                continue;
            }

            $startTime = (int) ($match['start_time'] ?? 0);

            if ($startTime <= 0) {
                continue;
            }

            $hero = $heroes[(int) $match['hero_id']] ?? [
                'id' => (int) $match['hero_id'],
                'name' => 'Hero #'.$match['hero_id'],
                'image' => null,
            ];

            $radiantWin = (bool) ($match['radiant_win'] ?? false);
            $isRadiant = (int) $match['player_slot'] < 128;

            $rows[] = [
                'match_id' => isset($match['match_id']) ? (int) $match['match_id'] : null,
                'hero_id' => $hero['id'],
                'hero_name' => $hero['name'],
                'hero_image' => $hero['image'],
                'hero_icon' => $hero['icon'] ?? null,
                'win' => $radiantWin === $isRadiant,
                'is_radiant' => $isRadiant,
                'start_time' => $startTime,
                'started_at' => CarbonImmutable::createFromTimestampUTC($startTime)->toIso8601String(),
                'duration' => (int) ($match['duration'] ?? 0),
                'kills' => (int) ($match['kills'] ?? 0),
                'deaths' => (int) ($match['deaths'] ?? 0),
                'assists' => (int) ($match['assists'] ?? 0),
                'gpm' => (int) ($match['gold_per_min'] ?? 0),
                'xpm' => (int) ($match['xp_per_min'] ?? 0),
                'last_hits' => (int) ($match['last_hits'] ?? 0),
                'denies' => (int) ($match['denies'] ?? 0),
                'gold' => (int) ($match['gold'] ?? 0),
                'hero_damage' => (int) ($match['hero_damage'] ?? 0),
                'tower_damage' => (int) ($match['tower_damage'] ?? 0),
                'hero_healing' => (int) ($match['hero_healing'] ?? 0),
                'level' => (int) ($match['level'] ?? 0),
                'net_worth' => (int) ($match['net_worth'] ?? 0),
                'lobby_type' => isset($match['lobby_type']) ? (int) $match['lobby_type'] : null,
                'game_mode' => isset($match['game_mode']) ? (int) $match['game_mode'] : null,
                'lobby_type_label' => $this->lobbyTypeLabel(isset($match['lobby_type']) ? (int) $match['lobby_type'] : null),
                'game_mode_label' => $this->gameModeLabel(isset($match['game_mode']) ? (int) $match['game_mode'] : null),
                'match_type_label' => $this->matchTypeLabel(
                    isset($match['lobby_type']) ? (int) $match['lobby_type'] : null,
                    isset($match['game_mode']) ? (int) $match['game_mode'] : null,
                ),
                'is_ranked' => $this->isRankedMode((int) ($match['game_mode'] ?? 0)),
                'party_label' => $this->partyLabel($match),
                'party_id' => isset($match['party_id']) && is_numeric($match['party_id']) ? (int) $match['party_id'] : null,
                'party_size' => isset($match['party_size']) && is_numeric($match['party_size']) ? (int) $match['party_size'] : null,
                'role_key' => $this->roleKey($match),
                'lane_role' => isset($match['lane_role']) ? (int) $match['lane_role'] : null,
            ];
        }

        return $rows;
    }

    private function dateTime(mixed $value): ?string
    {
        if (is_numeric($value) && (int) $value > 0) {
            return CarbonImmutable::createFromTimestampUTC((int) $value)->toIso8601String();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Last-resort hero aggregate built from matches already fetched from
     * OpenDota. This is real history data, not invented placeholder rows, and
     * keeps the hero block useful when both hero endpoints fail.
     *
     * @param  array<int, mixed>  $matches
     * @return array<int, array{hero_id: int, games: int, win: int, last_played: int}>
     */
    private function heroesFromHistory(array $matches): array
    {
        $aggregated = [];

        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            $heroId = (int) ($match['hero_id'] ?? 0);

            if ($heroId <= 0) {
                continue;
            }

            $isRadiant = (int) ($match['player_slot'] ?? 0) < 128;
            $win = (bool) ($match['radiant_win'] ?? false) === $isRadiant;
            $playedAt = (int) ($match['start_time'] ?? 0);

            $aggregated[$heroId] ??= [
                'hero_id' => $heroId,
                'games' => 0,
                'win' => 0,
                'last_played' => 0,
            ];
            $aggregated[$heroId]['games']++;
            $aggregated[$heroId]['win'] += (int) $win;
            $aggregated[$heroId]['last_played'] = max($aggregated[$heroId]['last_played'], $playedAt);
        }

        $rows = array_values($aggregated);
        usort($rows, fn (array $left, array $right) => [$right['games'], $right['win']] <=> [$left['games'], $left['win']]);

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $heroStats
     * @param  array<int, array<string, mixed>>  $heroes
     * @param  array<int, mixed>  $recentMatches
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array<string, mixed>>
     */
    private function transformHeroes(array $heroStats, array $heroes, array $recentMatches = [], array $history = []): array
    {
        $kdaByHero = $this->heroKda($recentMatches);
        $rankedByHero = [];

        foreach ($history as $match) {
            if (! is_array($match) || (int) ($match['lobby_type'] ?? 0) !== 7) {
                continue;
            }

            $heroId = (int) ($match['hero_id'] ?? 0);

            if ($heroId <= 0) {
                continue;
            }

            $rankedByHero[$heroId] ??= ['games' => 0, 'wins' => 0];
            $rankedByHero[$heroId]['games']++;

            if (! empty($match['win'])) {
                $rankedByHero[$heroId]['wins']++;
            }
        }

        $rows = collect($heroStats)
            ->filter(fn ($hero) => is_array($hero) && isset($hero['hero_id']) && (int) ($hero['games'] ?? 0) > 0)
            ->sortByDesc(fn (array $hero) => (int) ($hero['games'] ?? 0))
            ->map(function (array $hero) use ($heroes, $kdaByHero, $rankedByHero) {
                $meta = $heroes[(int) $hero['hero_id']] ?? [
                    'id' => (int) $hero['hero_id'],
                    'name' => 'Hero #'.$hero['hero_id'],
                    'image' => null,
                ];

                $games = (int) ($hero['games'] ?? 0);
                $wins = (int) ($hero['win'] ?? 0);
                $winRate = $games > 0 ? round($wins / $games * 100, 1) : null;
                $ranked = $rankedByHero[$meta['id']] ?? ['games' => 0, 'wins' => 0];
                $rankedLosses = max(0, $ranked['games'] - $ranked['wins']);

                return [
                    'hero_id' => $meta['id'],
                    // Keep both naming schemes: localized_name/win_rate feed the
                    // interactive table, name/winrate the existing overview.
                    'localized_name' => $meta['name'],
                    'name' => $meta['name'],
                    'image' => $meta['image'],
                    'icon' => $meta['icon'] ?? null,
                    'games' => $games,
                    'win' => $wins,
                    'wins' => $wins,
                    'win_rate' => $winRate,
                    'winrate' => $winRate,
                    'ranked_games' => $ranked['games'],
                    'ranked_wins' => $ranked['wins'],
                    'ranked_losses' => $rankedLosses,
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
