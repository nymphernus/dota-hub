<?php

namespace App\Http\Controllers;

use App\Models\PlayerProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class HeroPlayerController extends Controller
{
    private const MATCHES_PER_PAGE = 20;

    public function show(int $accountId, int $heroId, Request $request): View|Response
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

        $matches = $this->profileMatches($profileData);
        $heroMatches = array_values(array_filter(
            $matches,
            static fn (array $match): bool => (int) ($match['hero_id'] ?? 0) === $heroId,
        ));
        $hero = $this->heroFromMatches($heroMatches, $heroId);
        $summary = $this->summarizeMatches($heroMatches);

        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            array_slice($heroMatches, ($page - 1) * self::MATCHES_PER_PAGE, self::MATCHES_PER_PAGE),
            count($heroMatches),
            self::MATCHES_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('dota.hero', [
            'accountId' => $accountId,
            'player' => $player,
            'pageTitle' => $hero['name'].' - '.$player['nickname'].' - DOTAHUB',
            'hero' => $hero,
            'summary' => $summary,
            'matches' => $paginator,
            'allMatches' => $heroMatches,
            'personalStats' => $this->personalStats($heroMatches),
            'cachedAt' => $record->updated_at?->toIso8601String()
                ?? ($profileData['cached_at'] ?? now()->toIso8601String()),
        ]);
    }

    /**
     * Normalize and merge the persisted history with the enriched recent feed.
     *
     * @param  array<string, mixed>  $profileData
     * @return array<int, array<string, mixed>>
     */
    private function profileMatches(array $profileData): array
    {
        $history = $profileData['match_history'] ?? ($profileData['matches'] ?? []);
        $recent = $profileData['matches'] ?? [];
        $rows = [];

        foreach (is_array($history) ? [$history, $recent] : [] as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($source as $match) {
                if (! is_array($match)) {
                    continue;
                }

                $matchId = isset($match['match_id']) && (int) $match['match_id'] > 0
                    ? (int) $match['match_id']
                    : null;
                $key = $matchId !== null ? 'match:'.$matchId : 'row:'.count($rows);
                $row = $this->normalizeMatch($match);

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
    private function normalizeMatch(array $match): array
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
    private function summarizeMatches(array $matches): array
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
     * Personal records built from the persisted matches on this hero.
     *
     * @param  array<int, array<string, mixed>>  $heroMatches
     * @return array<string, mixed>
     */
    private function personalStats(array $heroMatches): array
    {
        $maxGpm = 0;
        $maxXpm = 0;
        $maxKda = 0.0;
        $maxKills = 0;
        $bestStreak = 0;
        $currentStreak = 0;

        foreach ($heroMatches as $match) {
            $maxGpm = max($maxGpm, (int) ($match['gpm'] ?? $match['gold_per_min'] ?? 0));
            $maxXpm = max($maxXpm, (int) ($match['xpm'] ?? $match['xp_per_min'] ?? 0));
            $maxKda = max($maxKda, (float) ($match['kda'] ?? 0));
            $maxKills = max($maxKills, (int) $match['kills']);

            if ($match['win']) {
                $currentStreak++;
                $bestStreak = max($bestStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return [
            'max_gpm' => $maxGpm,
            'max_xpm' => $maxXpm,
            'max_kda' => round($maxKda, 2),
            'max_kills' => $maxKills,
            'best_streak' => $bestStreak,
            'best_streak_label' => __('ui.wins_streak', ['count' => $bestStreak]),
        ];
    }
}
