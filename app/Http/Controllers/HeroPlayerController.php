<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class HeroPlayerController extends Controller
{
    private const BENCHMARK_METRICS = [
        'gpm' => [
            'label' => 'GPM',
            'hint' => 'Золото в минуту',
            'format' => 'integer',
        ],
        'xpm' => [
            'label' => 'XPM',
            'hint' => 'Опыт в минуту',
            'format' => 'integer',
        ],
        'kda' => [
            'label' => 'KDA',
            'hint' => 'K + A / D за матчи',
            'format' => 'decimal',
        ],
    ];

    public function show(int $accountId, int $heroId, OpenDotaService $openDota): View|Response
    {
        try {
            $player = $openDota->playerHeader($accountId);
            $stats = $openDota->getPlayerHeroStats($accountId, $heroId);
            $distributions = $openDota->getHeroBenchmarks($heroId);
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

        return view('dota.hero', [
            'player' => $player,
            'pageTitle' => ($stats['hero']['name'] ?? 'Герой').' - '.$player['nickname'].' - DotaHub',
            'hero' => $stats['hero'],
            'summary' => $stats['summary'],
            'matches' => $stats['matches'],
            'benchmarks' => $this->benchmarkComparisons($stats['summary'], $distributions),
            'cachedAt' => $stats['cached_at'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, array<int, array{percentile: float, value: float}>>  $distributions
     * @return array<int, array<string, mixed>>
     */
    private function benchmarkComparisons(array $summary, array $distributions): array
    {
        $rows = [];

        foreach (self::BENCHMARK_METRICS as $key => $metric) {
            $distribution = $distributions[$key] ?? [];
            $value = isset($summary[$key]) && is_numeric($summary[$key]) ? (float) $summary[$key] : null;

            if ($value === null || $distribution === []) {
                $rows[] = $metric + [
                    'key' => $key,
                    'available' => false,
                    'value' => $value,
                    'value_label' => $value !== null ? $this->formatBenchmarkValue($value, $metric['format']) : '—',
                ];

                continue;
            }

            $percentile = $this->percentileFor($distribution, $value);
            $topPercent = max(0.1, (1 - $percentile) * 100);

            $rows[] = $metric + [
                'key' => $key,
                'available' => true,
                'value' => $value,
                'value_label' => $this->formatBenchmarkValue($value, $metric['format']),
                'percentile' => $percentile,
                'percentile_label' => number_format($percentile * 100, 1, '.', ''),
                'rank_label' => $percentile >= 0.5
                    ? 'Топ '.($topPercent < 1 ? '<1' : number_format($topPercent, $topPercent < 10 ? 1 : 0, '.', '')).'%'
                    : ($percentile >= 0.25 ? 'Средний уровень' : 'Ниже среднего'),
                'tone' => match (true) {
                    $percentile >= 0.9 => 'emerald',
                    $percentile >= 0.7 => 'sky',
                    $percentile >= 0.5 => 'amber',
                    $percentile >= 0.25 => 'orange',
                    default => 'rose',
                },
                'average_label' => $this->formatBenchmarkValue(
                    $this->benchmarkValueAt($distribution, 0.5) ?? 0,
                    $metric['format']
                ),
                'elite_label' => $this->formatBenchmarkValue(
                    $this->benchmarkValueAt($distribution, 0.9) ?? 0,
                    $metric['format']
                ),
            ];
        }

        return $rows;
    }

    /**
     * Interpolate the player's percentile within a benchmark distribution.
     *
     * @param  array<int, array{percentile: float, value: float}>  $distribution
     */
    private function percentileFor(array $distribution, float $value): float
    {
        $first = $distribution[0];
        $last = $distribution[array_key_last($distribution)];

        if ($value <= $first['value']) {
            if ($first['value'] <= 0) {
                return $first['percentile'];
            }

            return max(0.0, min(1.0, round($first['percentile'] * ($value / $first['value']), 4)));
        }

        if ($value >= $last['value']) {
            return 1.0;
        }

        $previous = $first;

        foreach ($distribution as $point) {
            if ($value <= $point['value']) {
                $span = $point['value'] - $previous['value'];

                if ($span <= 0) {
                    return $point['percentile'];
                }

                $fraction = ($value - $previous['value']) / $span;
                $percentile = $previous['percentile'] + $fraction * ($point['percentile'] - $previous['percentile']);

                return round(max(0.0, min(1.0, $percentile)), 4);
            }

            $previous = $point;
        }

        return 1.0;
    }

    /**
     * @param  array<int, array{percentile: float, value: float}>  $distribution
     */
    private function benchmarkValueAt(array $distribution, float $percentile): ?float
    {
        if ($distribution === []) {
            return null;
        }

        $first = $distribution[0];

        if ($percentile <= $first['percentile']) {
            return $first['value'];
        }

        $previous = $first;

        foreach ($distribution as $point) {
            if ($percentile <= $point['percentile']) {
                $span = $point['percentile'] - $previous['percentile'];

                if ($span <= 0) {
                    return $point['value'];
                }

                $fraction = ($percentile - $previous['percentile']) / $span;

                return $previous['value'] + $fraction * ($point['value'] - $previous['value']);
            }

            $previous = $point;
        }

        return $previous['value'];
    }

    private function formatBenchmarkValue(float $value, string $format): string
    {
        return $format === 'integer'
            ? number_format($value, 0, '.', ' ')
            : number_format($value, 2, '.', '');
    }
}
