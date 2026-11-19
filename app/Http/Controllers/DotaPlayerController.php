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
use Illuminate\Http\Response;

class DotaPlayerController extends Controller
{
    private const HERO_USAGE_COLORS = [
        '#34d399',
        '#22d3ee',
        '#a78bfa',
        '#fbbf24',
        '#fb7185',
        '#f472b6',
        '#60a5fa',
        '#facc15',
        '#4ade80',
        '#94a3b8',
    ];

    public function show(int $accountId, OpenDotaService $openDota, SteamService $steam): View|Response
    {
        try {
            $player = $openDota->player($accountId);
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
            'steamBans' => $steam->bans($steamId),
            'rankStats' => $this->rankStats($history),
            'steamProfile' => $steam->summary($steamId),
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
     * GitHub-style daily activity for the last six months.
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
        $start = $end->subMonths(6)->startOfWeek(CarbonImmutable::MONDAY);
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
                    $count <= 2 => 'low',
                    $count <= 4 => 'mid',
                    default => 'high',
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

        foreach ($rows as $index => &$hero) {
            $hero['winrate'] = round($hero['wins'] / max($hero['games'], 1) * 100, 1);
            $hero['share'] = round($hero['games'] / max($totalGames, 1) * 100, 1);
            $hero['color'] = self::HERO_USAGE_COLORS[$index % count(self::HERO_USAGE_COLORS)];
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
