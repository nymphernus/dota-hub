<?php

namespace App\Http\Controllers;

use App\Models\PlayerProfile;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

class HeroPlayerController extends Controller
{
    /**
     * @return array<string, array{label: string, hint: string, format: string}>
     */
    private function benchmarkMetrics(): array
    {
        return [
            'gpm' => [
                'label' => 'GPM',
                'hint' => __('ui.benchmark_gpm_hint'),
                'format' => 'integer',
            ],
            'xpm' => [
                'label' => 'XPM',
                'hint' => __('ui.benchmark_xpm_hint'),
                'format' => 'integer',
            ],
            'kda' => [
                'label' => 'KDA',
                'hint' => __('ui.benchmark_kda_hint'),
                'format' => 'decimal',
            ],
        ];
    }

    public function show(int $accountId, int $heroId, OpenDotaService $openDota): View|Response
    {
        $record = PlayerProfile::find($accountId);

        if ($record === null) {
            return response()->view('errors.player', [
                'title' => __('ui.player_not_found'),
                'message' => __('ui.hero_profile_not_found'),
                'backUrl' => route('home'),
            ], 404);
        }

        $profileData = is_array($record->data) ? $record->data : [];
        $player = [
            'account_id' => (int) $record->account_id,
            'nickname' => $record->nickname ?: ($profileData['nickname'] ?? null) ?: __('ui.anonymous_player'),
            'avatar' => $record->avatar ?: ($profileData['avatar'] ?? null),
        ];

        $heroMatches = $this->heroMatches($profileData, $heroId);
        $hero = $this->heroFromMatches($heroMatches, $heroId);
        $summary = $this->summarizeHeroMatches($heroMatches);

        $benchmarksUnavailable = false;

        try {
            $distributions = $openDota->getHeroBenchmarks($heroId);
        } catch (Throwable $exception) {
            report($exception);
            $distributions = [];
            $benchmarksUnavailable = true;
        }

        if ($distributions === []) {
            $benchmarksUnavailable = true;
        }

        return view('dota.hero', [
            'player' => $player,
            'pageTitle' => $hero['name'].' - '.$player['nickname'].' - DOTAHUB',
            'hero' => $hero,
            'summary' => $summary,
            'matches' => array_slice($heroMatches, 0, 10),
            'benchmarks' => $this->benchmarkComparisons($summary, $distributions),
            'benchmarksUnavailable' => $benchmarksUnavailable,
            'cachedAt' => $record->updated_at?->toIso8601String()
                ?? ($profileData['cached_at'] ?? now()->toIso8601String()),
        ]);
    }

    /**
     * Merge the persisted hero filter and the enriched recent-match feed.
     *
     * @param  array<string, mixed>  $profileData
     * @return array<int, array<string, mixed>>
     */
    private function heroMatches(array $profileData, int $heroId): array
    {
        $rows = [];

        foreach ([$profileData['match_history'] ?? [], $profileData['matches'] ?? []] as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($source as $match) {
                if (! is_array($match) || (int) ($match['hero_id'] ?? 0) !== $heroId) {
                    continue;
                }

                $matchId = isset($match['match_id']) && (int) $match['match_id'] > 0
                    ? (int) $match['match_id']
                    : null;
                $key = $matchId !== null ? 'match:'.$matchId : 'row:'.count($rows);
                $row = $this->normalizeHeroMatch($match);

                if (! isset($rows[$key])) {
                    $rows[$key] = $row;

                    continue;
                }

                foreach (['gpm', 'xpm', 'net_worth', 'hero_damage', 'duration', 'duration_label', 'started_at', 'started_timestamp'] as $field) {
                    if (! empty($row[$field])) {
                        $rows[$key][$field] = $row[$field];
                    }
                }
            }
        }

        $matches = array_values($rows);

        usort($matches, static fn (array $a, array $b): int => ($b['started_timestamp'] ?? 0) <=> ($a['started_timestamp'] ?? 0));

        foreach ($matches as &$match) {
            unset($match['started_timestamp']);
        }
        unset($match);

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function normalizeHeroMatch(array $match): array
    {
        $kills = (int) ($match['kills'] ?? 0);
        $deaths = (int) ($match['deaths'] ?? 0);
        $assists = (int) ($match['assists'] ?? 0);
        $duration = (int) ($match['duration'] ?? 0);
        $startTime = (int) ($match['start_time'] ?? 0);

        if (array_key_exists('win', $match)) {
            $win = (bool) $match['win'];
        } else {
            $isRadiant = (int) ($match['player_slot'] ?? 0) < 128;
            $win = array_key_exists('radiant_win', $match)
                ? (bool) $match['radiant_win'] === $isRadiant
                : false;
        }

        $startedAt = $match['started_at'] ?? null;
        if (! is_string($startedAt) && $startTime > 0) {
            $startedAt = now()->createFromTimestampUTC($startTime)->toIso8601String();
        }

        return [
            'match_id' => isset($match['match_id']) ? (int) $match['match_id'] : null,
            'hero_id' => (int) ($match['hero_id'] ?? 0),
            'hero_name' => $match['hero_name'] ?? null,
            'hero_image' => $match['hero_image'] ?? null,
            'hero_icon' => $match['hero_icon'] ?? null,
            'win' => $win,
            'kills' => $kills,
            'deaths' => $deaths,
            'assists' => $assists,
            'kda' => round(($kills + $assists) / max($deaths, 1), 2),
            'gpm' => (int) ($match['gpm'] ?? $match['gold_per_min'] ?? 0),
            'xpm' => (int) ($match['xpm'] ?? $match['xp_per_min'] ?? 0),
            'net_worth' => (int) ($match['net_worth'] ?? 0),
            'hero_damage' => (int) ($match['hero_damage'] ?? 0),
            'duration' => $duration,
            'duration_label' => $match['duration_label'] ?? ($duration > 0
                ? intdiv($duration, 60).':'.str_pad((string) ($duration % 60), 2, '0', STR_PAD_LEFT)
                : '—'),
            'started_at' => is_string($startedAt) ? $startedAt : null,
            'started_timestamp' => $startTime > 0
                ? $startTime
                : (is_string($startedAt) ? (int) strtotime($startedAt) : 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function heroFromMatches(array $matches, int $heroId): array
    {
        foreach ($matches as $match) {
            if (! empty($match['hero_name'])) {
                return [
                    'id' => $heroId,
                    'name' => (string) $match['hero_name'],
                    'image' => $match['hero_image'] ?? null,
                    'icon' => $match['hero_icon'] ?? null,
                ];
            }
        }

        $heroes = Cache::get('opendota:hero-stats:v4');
        if (is_array($heroes) && is_array($heroes[$heroId] ?? null)) {
            return [
                'id' => $heroId,
                'name' => $heroes[$heroId]['name'] ?? __('ui.hero_hash', ['id' => $heroId]),
                'image' => $heroes[$heroId]['image'] ?? null,
                'icon' => $heroes[$heroId]['icon'] ?? null,
            ];
        }

        return [
            'id' => $heroId,
            'name' => __('ui.hero_hash', ['id' => $heroId]),
            'image' => null,
            'icon' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function summarizeHeroMatches(array $matches): array
    {
        $games = count($matches);
        $wins = 0;
        $kills = 0;
        $deaths = 0;
        $assists = 0;
        $gpmValues = [];
        $xpmValues = [];

        foreach ($matches as $match) {
            $wins += (bool) $match['win'] ? 1 : 0;
            $kills += (int) $match['kills'];
            $deaths += (int) $match['deaths'];
            $assists += (int) $match['assists'];

            if ((int) $match['gpm'] > 0) {
                $gpmValues[] = (int) $match['gpm'];
            }

            if ((int) $match['xpm'] > 0) {
                $xpmValues[] = (int) $match['xpm'];
            }
        }

        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, array<int, array{percentile: float, value: float}>>  $distributions
     * @return array<int, array<string, mixed>>
     */
    private function benchmarkComparisons(array $summary, array $distributions): array
    {
        $rows = [];

        foreach ($this->benchmarkMetrics() as $key => $metric) {
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
                    ? __('ui.benchmark_top', ['pct' => $topPercent < 1 ? '<1' : number_format($topPercent, $topPercent < 10 ? 1 : 0, '.', '')])
                    : ($percentile >= 0.25 ? __('ui.benchmark_average') : __('ui.benchmark_below_average')),
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
