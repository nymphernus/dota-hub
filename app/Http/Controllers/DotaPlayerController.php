<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use App\Models\PlayerSearch;
use App\Services\OpenDota\OpenDotaService;
use App\Services\Steam\SteamService;
use App\Support\SteamId;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class DotaPlayerController extends Controller
{
    public function show(int $accountId, OpenDotaService $openDota, SteamService $steam): View|Response
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

        return view('dota.player', [
            'player' => $player,
            'winrate' => $player['winrate'],
            'recentMatches' => array_slice($player['matches'], 0, 25),
            'activityHeatmap' => $this->activityHeatmap($history),
            'heroUsage' => $this->heroUsage(array_slice($history, 0, 25)),
            'rankStats' => $this->rankStats($history),
            'steamProfile' => $steam->summary($steamId),
        ]);
    }

    /**
     * Full hero pool for the dedicated heroes page.
     */
    public function heroes(int $accountId, OpenDotaService $openDota, SteamService $steam): View|Response
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

        return view('dota.heroes', [
            'player' => $player,
            'steamProfile' => $steam->summary(
                ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId)
            ),
        ]);
    }

    /**
     * Frequent teammates for the dedicated allies page.
     */
    public function peers(int $accountId, OpenDotaService $openDota, SteamService $steam): View|Response
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
            'steamProfile' => $steam->summary(
                ($player['steam_id'] ?? '') ?: SteamId::steamId64FromAccountId($accountId)
            ),
        ]);
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
                'title' => 'Игрок не найден',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 404);
        } catch (ApiRateLimitException $exception) {
            return response()->view('errors.player', [
                'title' => 'Лимит API исчерпан',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 429);
        } catch (ApiUnavailableException $exception) {
            return response()->view('errors.api', [
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 502);
        } catch (ExternalApiException $exception) {
            return response()->view('errors.player', [
                'title' => 'OpenDota недоступна',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 502);
        }
    }

    /**
     * Paginated, filterable match list for one player.
     */
    public function matches(int $accountId, Request $request, OpenDotaService $openDota): View|Response
    {
        $filters = [
            'hero_id' => $request->integer('hero_id') ?: 0,
            'result' => in_array($request->query('result'), ['win', 'loss'], true) ? $request->query('result') : '',
            'mode' => in_array($request->query('mode'), ['ranked', 'turbo', 'normal'], true) ? $request->query('mode') : '',
            'role' => in_array($request->query('role'), ['carry', 'mid', 'offlane', 'support', 'hard_support'], true) ? $request->query('role') : '',
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
                'title' => 'Игрок не найден',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 404);
        } catch (ApiRateLimitException $exception) {
            return response()->view('errors.player', [
                'title' => 'Лимит API исчерпан',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 429);
        } catch (ApiUnavailableException|ExternalApiException $exception) {
            return response()->view('errors.api', [
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 502);
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

        return view('dota.matches', [
            'player' => $result['player'],
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
            ->route('dota.player', ['accountId' => $accountId])
            ->with('status', 'Кэш очищен — загружаем свежие данные из OpenDota.');
    }

    /**
     * GitHub-style daily activity for the last 90 days, shown as 13 full weeks.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<int, array{date: string, label: string, count: int, wins: int, losses: int, level: string}>
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
        $start = $end->subDays(90);
        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $row = $byDate[$key] ?? ['count' => 0, 'wins' => 0, 'losses' => 0];
            $count = (int) $row['count'];

            $days[] = [
                'date' => $key,
                'label' => $day->format('d.m.Y'),
                'count' => $count,
                'wins' => (int) $row['wins'],
                'losses' => (int) $row['losses'],
                'level' => match (true) {
                    $count === 0 => 'empty',
                    $row['losses'] === 0 => 'win',
                    $row['wins'] === 0 => 'loss',
                    $row['losses'] > $row['wins'] => 'loss_majority',
                    $row['wins'] > $row['losses'] => 'win_majority',
                    default => 'mixed',
                },
            ];
        }

        return $days;
    }

    /**
     * Every hero played in the last 25 matches — no "Другие" bucket.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<int, array<string, mixed>>
     */
    private function heroUsage(array $matches): array
    {
        $aggregated = [];

        foreach ($matches as $match) {
            $heroId = (int) ($match['hero_id'] ?? 0);

            if ($heroId <= 0) {
                continue;
            }

            $aggregated[$heroId] ??= [
                'hero_id' => $heroId,
                'name' => $match['hero_name'] ?? 'Hero #'.$heroId,
                'image' => $match['hero_image'] ?? null,
                'icon' => $match['hero_icon'] ?? null,
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
