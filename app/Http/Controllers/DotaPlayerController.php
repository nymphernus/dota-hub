<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use App\Models\PlayerSearch;
use App\Services\Favorites\FavoriteService;
use App\Services\OpenDota\OpenDotaService;
use App\Services\Steam\SteamService;
use App\Support\SteamId;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class DotaPlayerController extends Controller
{
    private const MONTH_LABELS = [
        1 => 'Янв', 2 => 'Фев', 3 => 'Мар', 4 => 'Апр', 5 => 'Май', 6 => 'Июн',
        7 => 'Июл', 8 => 'Авг', 9 => 'Сент', 10 => 'Окт', 11 => 'Ноя', 12 => 'Дек',
    ];

    public function show(int $accountId, OpenDotaService $openDota, SteamService $steam, FavoriteService $favorites): View|Response
    {
        $player = $this->playerOrError($accountId, $openDota);

        if ($player instanceof Response) {
            return $player;
        }

        PlayerSearch::track(
            (string) $accountId,
            $player['is_private'] ? null : $player['nickname'],
            $player['avatar'],
        );

        $history = $player['match_history'] ?? [];
        $steamId = ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId);

        // The heatmap is a lifetime-activity view: it must receive every cached
        // match, including normal/turbo games. No lobby_type filter is applied
        // here — ranked-only filtering belongs to the records page alone.
        $heatmapMatches = array_values($history);

        $heroUsageUnavailable = false;
        try {
            $heroUsage = $this->heroUsage(array_slice($history, 0, 25), $openDota);
        } catch (\App\Exceptions\ApiUnavailableException|\App\Exceptions\ExternalApiException $e) {
            Log::warning('Failed to load hero usage for ' . $accountId, ['error' => $e->getMessage()]);
            $heroUsage = [];
            $heroUsageUnavailable = true;
        }

        return view('dota.player', [
            'player' => $player,
            'pageTitle' => $player['nickname'].' - '.__('ui.overview'),
            'activeTab' => 'overview',
            'profileSection' => __('ui.overview'),
            'winrate' => $player['winrate'],
            'recentMatches' => array_slice($player['matches'], 0, 25),
            'history' => $history,
            'activityHeatmap' => $this->activityHeatmap($heatmapMatches),
            'heroUsage' => $heroUsage,
            'heroUsageUnavailable' => $heroUsageUnavailable,
            'lifetimeStats' => $openDota->lifetimeStats($accountId, $history),
            'rankStats' => $this->rankStats($history),
            'steamProfile' => $steam->summary($steamId),
            'isFavorite' => $this->favoriteState($accountId, $favorites),
        ]);
    }

    /**
     * List the player's heroes with sort and hero filter.
     */
    public function heroes(int $accountId, Request $request, OpenDotaService $openDota, SteamService $steam, FavoriteService $favorites): View|Response
    {
        $player = $this->playerOrError($accountId, $openDota);

        if ($player instanceof Response) {
            return $player;
        }

        PlayerSearch::track(
            (string) $accountId,
            $player['is_private'] ? null : $player['nickname'],
            $player['avatar'],
        );

        $filters = [
            'hero_id' => $request->integer('hero_id') ?: 0,
            'sort' => in_array($request->query('sort'), ['games', 'winrate', 'kda'], true) ? $request->query('sort') : 'games',
        ];

        $heroes = collect($player['heroes'] ?? []);
        if ($filters['hero_id'] > 0) {
            $heroes = $heroes->where('hero_id', $filters['hero_id']);
        }

        $heroes = match ($filters['sort']) {
            'winrate' => $heroes->sortByDesc(fn ($h) => (float) ($h['win_rate'] ?? $h['winrate'] ?? 0))->values(),
            'kda' => $heroes->sortByDesc(fn ($h) => (float) ($h['kda'] ?? 0))->values(),
            default => $heroes->sortByDesc('games')->values(),
        };

        $perPage = min(50, max(5, $request->integer('per_page') ?: 25));
        $page = max(1, $request->integer('page') ?: 1);
        $total = $heroes->count();
        $rows = $heroes->forPage($page, $perPage)->values()->all();

        $paginator = new LengthAwarePaginator($rows, $total, $perPage, $page, ['path' => $request->url()]);
        $paginator->withQueryString();

        $heroOptions = collect($player['heroes'] ?? [])
            ->map(fn (array $hero) => [
                'hero_id' => (int) ($hero['hero_id'] ?? 0),
                'localized_name' => $hero['localized_name'] ?? $hero['name'] ?? '?????',
                'name' => $hero['name'] ?? $hero['localized_name'] ?? '?????',
                'icon' => $hero['icon'] ?? null,
                'image' => $hero['image'] ?? null,
            ])
            ->sortBy(fn (array $hero) => $hero['localized_name'])
            ->values()
            ->all();

        return view('dota.heroes', [
            'player' => $player,
            'pageTitle' => $player['nickname'].' - ?????',
            'activeTab' => 'heroes',
            'profileSection' => '?????',
            'steamProfile' => $steam->summary(
                ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId)
            ),
            'isFavorite' => $this->favoriteState($accountId, $favorites),
            'heroes' => $paginator,
            'heroOptions' => $heroOptions,
            'filters' => $filters,
        ]);
    }

    /**
     * Frequent teammates for the dedicated allies page.
     */
    public function peers(int $accountId, OpenDotaService $openDota, SteamService $steam, FavoriteService $favorites): View|Response
    {
        $player = $this->playerOrError($accountId, $openDota);

        if ($player instanceof Response) {
            return $player;
        }

        PlayerSearch::track(
            (string) $accountId,
            $player['is_private'] ? null : $player['nickname'],
            $player['avatar'],
        );

        return view('dota.peers', [
            'player' => $player,
            'pageTitle' => $player['nickname'].' - '.__('ui.peers'),
            'activeTab' => 'peers',
            'profileSection' => __('ui.peers'),
            'steamProfile' => $steam->summary(
                ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId)
            ),
            'isFavorite' => $this->favoriteState($accountId, $favorites),
        ]);
    }

    /**
     * Personal records rendered as tiles, backed by a 24-hour Redis cache.
     */
    public function records(int $accountId, OpenDotaService $openDota, SteamService $steam, FavoriteService $favorites): View|Response
    {
        $player = $this->playerOrError($accountId, $openDota);

        if ($player instanceof Response) {
            return $player;
        }

        PlayerSearch::track(
            (string) $accountId,
            $player['is_private'] ? null : $player['nickname'],
            $player['avatar'],
        );

        $history = $player['match_history'] ?? [];

        // Both tab payloads are resolved up front so switching tabs is purely
        // client-side and never reloads the profile.
        return view('dota.records', [
            'player' => $player,
            'pageTitle' => $player['nickname'].' - '.__('ui.records'),
            'activeTab' => 'records',
            'profileSection' => __('ui.records'),
            'records' => $openDota->playerRecords($accountId, $history, false),
            'rankedRecords' => $openDota->playerRecords($accountId, $history, true),
            'steamProfile' => $steam->summary(
                ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId)
            ),
            'isFavorite' => $this->favoriteState($accountId, $favorites),
        ]);
    }

    private function favoriteState(int $accountId, FavoriteService $favorites): bool
    {
        $sessionSteamId = (string) session('steam_id', '');

        return SteamId::isSteamId64($sessionSteamId) && $favorites->has($sessionSteamId, $accountId);
    }

    /**
     * Resolve a player profile, converting service failures into error views.
     *
     * @return array<string, mixed>|Response
     */
    private function playerOrError(int $accountId, OpenDotaService $openDota): array|Response
    {
        try {
            return $openDota->player($accountId);
        } catch (PlayerNotFoundException $exception) {
            return response()->view('errors.player', [
                'title' => __('ui.player_not_found'),
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 404);
        } catch (ApiRateLimitException $exception) {
            return response()->view('errors.player', [
                'title' => __('ui.api_limit'),
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 429);
        } catch (ApiUnavailableException|ExternalApiException $exception) {
            $cached = \App\Models\PlayerProfile::where('account_id', $accountId)->first();
            $data = is_array($cached?->data) ? $cached->data : [];
            if ($data !== []) {
                $data['api_warning'] = __('ui.api_warning');
                return $data;
            }

            return [
                'account_id' => $accountId,
                'steam_id' => (string) \App\Support\SteamId::steamId64FromAccountId($accountId),
                'nickname' => __('ui.player_hash', ['id' => $accountId]),
                'avatar' => null,
                'profile_url' => null,
                'country_code' => null,
                'last_login_at' => null,
                'is_private' => false,
                'rank_tier' => null,
                'rank_label' => __('ui.no_rank'),
                'rank_medal' => null,
                'rank_stars' => 0,
                'leaderboard_rank' => null,
                'mmr_estimate' => null,
                'mmr_is_estimated' => false,
                'mmr_source' => null,
                'mmr_games_considered' => 0,
                'games' => 0,
                'wins' => 0,
                'losses' => 0,
                'winrate' => null,
                'averages' => ['gpm' => 0, 'xpm' => 0, 'kills' => 0, 'deaths' => 0, 'assists' => 0, 'kda' => 0],
                'heroes' => [],
                'top_heroes' => [],
                'matches' => [],
                'match_history' => [],
                'peers' => [],
                'has_public_matches' => false,
                'dota_data_hidden' => false,
                'api_warning' => __('ui.api_limit_short'),
            ];
        }
    }

    /**
     * Paginated, filterable match list for one player.
     */
    public function matches(int $accountId, Request $request, OpenDotaService $openDota, SteamService $steam, FavoriteService $favorites): View|Response
    {
        $filters = [
            'hero_id' => $request->integer('hero_id') ?: 0,
            'result' => in_array($request->query('result'), ['win', 'loss'], true) ? $request->query('result') : '',
            'mode' => in_array($request->query('mode'), ['ranked', 'turbo', 'normal'], true) ? $request->query('mode') : '',
            'role' => in_array($request->query('role'), ['carry', 'mid', 'offlane', 'support', 'hard_support'], true) ? $request->query('role') : '',
            'date' => $this->validDate($request->query('date')),
        ];

        try {
            $result = $openDota->playerMatches(
                $accountId,
                max(1, $request->integer('page') ?: 1),
                min(50, max(5, $request->integer('per_page') ?: 25)),
                $filters,
            );
        } catch (PlayerNotFoundException $exception) {
            return response()->view('errors.player', [
                'title' => __('ui.player_not_found'),
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 404);
        } catch (ApiRateLimitException $exception) {
            return response()->view('errors.player', [
                'title' => __('ui.api_limit'),
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 429);
        } catch (ApiUnavailableException|ExternalApiException $exception) {
            $player = $this->playerOrError($accountId, $openDota);
            if ($player instanceof Response) { return $player; }
            $player['api_warning'] = $player['api_warning'] ?? __('ui.api_limit_short');
            $result = ['rows' => [], 'total' => 0, 'hero_options' => [], 'player' => $player];
        }

        $perPage = min(50, max(5, $request->integer('per_page') ?: 25));
        $matches = new LengthAwarePaginator(
            $result['rows'],
            $result['total'],
            $perPage,
            max(1, $request->integer('page') ?: 1),
            ['path' => $request->url()],
        );
        $matches->withQueryString();

        $player = $result['player'];
        $steamId = ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId);

        return view('dota.matches', [
            'player' => $player,
            'pageTitle' => $player['nickname'].' - '.__('ui.matches'),
            'activeTab' => 'matches',
            'profileSection' => __('ui.matches'),
            'steamProfile' => $steam->summary($steamId),
            'isFavorite' => $this->favoriteState($accountId, $favorites),
            'matches' => $matches,
            'heroOptions' => $result['heroes'],
            'filters' => $result['filters'],
        ]);
    }

    public function refresh(int $accountId, OpenDotaService $openDota, SteamService $steam): RedirectResponse
    {
        $openDota->forget($accountId);
        $steam->forget(SteamId::steamId64FromAccountId($accountId));

        return redirect()
            ->route('player', ['accountId' => $accountId])
            ->with('status', __('ui.cache_cleared'));
    }

    /**
     * Accept only an exact UTC calendar day from the date filter.
     */
    private function validDate(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return '';
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

            return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * GitHub-style daily activity for the last 13 weeks.
     *
     * Every supplied match is counted; there is deliberately no lobby_type or
     * is_ranked check in this method.
     *
     * Days are aligned to Monday-starting columns so weekday labels can sit
     * vertically on the left while month labels sit above the columns.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array{days: array<int, array<string, mixed>>, weeks: array<int, array<int, array<string, mixed>>>, months: array<int, array{column: int, label: string}>, weekdays: array<int, string>}
     */
    private function activityHeatmap(array $matches): array
    {
        $byDate = [];

        foreach ($matches as $match) {
            if (empty($match['started_at'])) {
                continue;
            }

            $date = CarbonImmutable::parse($match['started_at'])->toDateString();
            $byDate[$date] ??= ['count' => 0, 'wins' => 0, 'losses' => 0];
            $byDate[$date]['count']++;

            if ($match['win'] ?? false) {
                $byDate[$date]['wins']++;
            } else {
                $byDate[$date]['losses']++;
            }
        }

        $end = CarbonImmutable::now('UTC')->startOfDay();
        $start = $end->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(12);
        $days = [];
        $weeks = [];
        $months = [];
        $previousMonth = null;

        for ($weekIndex = 0; $weekIndex < 13; $weekIndex++) {
            $columnStart = $start->addWeeks($weekIndex);
            $week = [];

            for ($weekday = 0; $weekday < 7; $weekday++) {
                $day = $columnStart->addDays($weekday);
                $key = $day->toDateString();
                $row = $byDate[$key] ?? ['count' => 0, 'wins' => 0, 'losses' => 0];
                $count = (int) $row['count'];
                $isFuture = $day->greaterThan($end);

                $wins = (int) $row['wins'];
                $losses = (int) $row['losses'];

                $cell = [
                    'date' => $key,
                    'label' => $day->format('d.m.Y'),
                    'count' => $isFuture ? 0 : $count,
                    'wins' => $isFuture ? 0 : $wins,
                    'losses' => $isFuture ? 0 : $losses,
                    'level' => $isFuture ? 'future' : match (true) {
                        $count === 0 => 'empty',
                        $wins > 0 && $losses === 0 => 'win',
                        $losses > 0 && $wins === 0 => 'loss',
                        $wins > $losses => 'more_wins',
                        $losses > $wins => 'more_losses',
                        $wins === $losses => 'equal',
                        default => 'empty',
                    },
                    'column' => $weekIndex,
                    'weekday' => $weekday,
                ];

                $days[] = $cell;
                $week[] = $cell;
            }

            $weeks[] = $week;

            $monthKey = $columnStart->format('Y-m');

            if ($monthKey !== $previousMonth) {
                $months[] = [
                    'column' => $weekIndex,
                    'label' => self::MONTH_LABELS[(int) $columnStart->format('n')],
                ];
                $previousMonth = $monthKey;
            }
        }

        return [
            'days' => $days,
            'weeks' => $weeks,
            'months' => $months,
            'weekdays' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
        ];
    }

    /**
     * Every hero played in the last 25 matches — no "Другие" bucket.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<int, array<string, mixed>>
     */
    private function heroUsage(array $matches, OpenDotaService $openDota): array
    {
        $aggregated = [];
        $heroStats = $openDota->heroStats();

        foreach ($matches as $match) {
            $heroId = (int) ($match['hero_id'] ?? 0);

            if ($heroId <= 0) {
                continue;
            }

            $meta = $heroStats[$heroId] ?? [];
            $aggregated[$heroId] ??= [
                'hero_id' => $heroId,
                'name' => $match['hero_name'] ?? ($meta['localized_name'] ?? $meta['name'] ?? 'Hero #'.$heroId),
                'image' => $match['hero_image'] ?? ($meta['image'] ?? null),
                'icon' => $match['hero_icon'] ?? ($meta['icon'] ?? null),
                'primary_attr' => $meta['primary_attr'] ?? null,
                'games' => 0,
                'wins' => 0,
            ];

            $aggregated[$heroId]['games']++;

            if ($match['win'] ?? false) {
                $aggregated[$heroId]['wins']++;
            }
        }

        $rows = array_values($aggregated);
        usort($rows, fn (array $a, array $b) => [$b['games'], $b['wins']] <=> [$a['games'], $a['wins']]);

        $totalGames = array_sum(array_column($rows, 'games'));

        foreach ($rows as &$hero) {
            $hero['winrate'] = round($hero['wins'] / max($hero['games'], 1) * 100, 1);
            $hero['share'] = round($hero['games'] / max($totalGames, 1) * 100, 1);
        }
        unset($hero);

        return $rows;
    }
    /**
     * Ranked/unranked split used by the sidebar rank card.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array{ranked: int, unranked: int, total: int, ranked_share: float}
     */
    private function rankStats(array $matches): array
    {
        $ranked = count(array_filter($matches, fn (array $match) => (bool) ($match['is_ranked'] ?? false)));
        $total = count($matches);

        return [
            'ranked' => $ranked,
            'unranked' => $total - $ranked,
            'total' => $total,
            'ranked_share' => $total > 0 ? round($ranked / $total * 100, 1) : 0.0,
        ];
    }
}
