@php
    $playerId = (int) ($playerId ?? 0);
    $cx = 120.0;
    $cy = 120.0;
    $outerRadius = 110.0;
    $innerRadius = 58.0;

    $buildWheel = static function (array $heroes, string $gamesKey, string $winsKey, ?string $lossesKey) use ($cx, $cy, $outerRadius, $innerRadius): array {
        $items = [];
        $totalGames = 0;
        $totalWins = 0;

        foreach ($heroes as $hero) {
            if (! is_array($hero)) {
                continue;
            }

            $games = max(0, (int) ($hero[$gamesKey] ?? 0));

            if ($games === 0) {
                continue;
            }

            $wins = max(0, (int) ($hero[$winsKey] ?? 0));
            $losses = $lossesKey !== null
                ? max(0, (int) ($hero[$lossesKey] ?? 0))
                : max(0, $games - $wins);

            $items[] = [
                'hero_id' => (int) ($hero['hero_id'] ?? $hero['id'] ?? 0),
                'name' => $hero['name'] ?? $hero['localized_name'] ?? 'Герой',
                'icon' => $hero['icon'] ?? ($hero['image'] ?? ''),
                'games' => $games,
                'wins' => $wins,
                'losses' => $losses,
                'winrate' => round($wins / $games * 100, 1),
            ];

            $totalGames += $games;
            $totalWins += $wins;
        }

        if ($items === []) {
            return ['sectors' => [], 'winrate' => 0];
        }

        $wheelTotal = max(1, $totalGames);
        $wheelMax = max([1, ...array_column($items, 'games')]);
        $wheelGap = count($items) > 1 ? 1.4 : 0;
        $wheelCursor = -90.0;
        $sectors = [];

        $point = static function (float $radius, float $angle) use ($cx, $cy): string {
            $radians = deg2rad($angle);

            return sprintf('%.2f %.2f', $cx + $radius * cos($radians), $cy + $radius * sin($radians));
        };

        foreach ($items as $index => $hero) {
            $sweep = ($hero['games'] / $wheelTotal) * 360;
            $start = $wheelCursor + $wheelGap / 2;
            $end = $wheelCursor + $sweep - $wheelGap / 2;
            $wheelCursor += $sweep;

            if ($end <= $start) {
                continue;
            }

            $largeArc = ($end - $start) > 180 ? 1 : 0;
            $sectorPath = sprintf(
                'M %s A %.2f %.2f 0 %d 1 %s L %s A %.2f %.2f 0 %d 0 %s Z',
                $point($outerRadius, $start),
                $outerRadius,
                $outerRadius,
                $largeArc,
                $point($outerRadius, $end),
                $point($innerRadius, $end),
                $innerRadius,
                $innerRadius,
                $largeArc,
                $point($innerRadius, $start),
            );

            $middleAngle = ($start + $end) / 2;
            $iconRadius = ($innerRadius + $outerRadius) / 2;
            $iconSize = max(13.0, min(34.0, 14.0 + ($hero['games'] / $wheelMax) * 20.0));
            $iconRadians = deg2rad($middleAngle);

            $sectors[] = $hero + [
                'index' => $index,
                'path' => $sectorPath,
                'x' => sprintf('%.2f', $cx + $iconRadius * cos($iconRadians) - $iconSize / 2),
                'y' => sprintf('%.2f', $cy + $iconRadius * sin($iconRadians) - $iconSize / 2),
                'size' => sprintf('%.2f', $iconSize),
            ];
        }

        return [
            'sectors' => $sectors,
            'winrate' => $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0,
        ];
    };

    $wheelHeroes = is_array($heroes ?? null) ? $heroes : [];
    $allWheel = $buildWheel($wheelHeroes, 'games', 'wins', null);
    $rankedWheel = $buildWheel($wheelHeroes, 'ranked_games', 'ranked_wins', 'ranked_losses');
    $overallWinrate = $allWheel['winrate'];
@endphp

<div id="wheel-tooltip" class="hidden fixed z-50 bg-[#1c1e26] border border-[#2d313e] rounded p-2 text-white shadow-xl flex items-center gap-3"></div>

<script id="hero-wheel-all-data" type="application/json">@js($allWheel['sectors'])</script>
<script id="hero-wheel-ranked-data" type="application/json">@js($rankedWheel['sectors'])</script>
<div
    id="hero-wheel-data"
    class="hidden"
    data-url-template="{{ route('dota.player.hero', ['accountId' => $playerId, 'heroId' => '__HERO__']) }}"
    data-all-winrate="{{ $allWheel['winrate'] }}"
    data-ranked-winrate="{{ $rankedWheel['winrate'] }}"
></div>

<div class="mt-3" x-data="heroWheel()">
    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-mist-300">
        <input type="checkbox" x-model="rankedOnly" class="h-4 w-4 border-line bg-ink-900 accent-yellow-500 ring-0">
        Только рейтинг
    </label>

    @if ($allWheel['sectors'] === [] && $rankedWheel['sectors'] === [])
        <div class="mt-4 grid h-48 place-items-center rounded-none border border-dashed border-line text-xs text-mist-400">
            Нет данных
        </div>
    @else
        <div class="relative mx-auto mt-3 h-56 w-56 max-w-full" x-show="sectors.length > 0">
            <svg
                id="hero-wheel"
                class="hero-wheel mx-auto block h-56 w-56 max-w-full"
                viewBox="0 0 240 240"
                role="img"
                aria-label="Колесо героев"
                xmlns="http://www.w3.org/2000/svg"
            >
                <title>Колесо героев</title>

                <circle cx="120" cy="120" r="112" fill="#111318" />

                <template x-for="sector in sectors" :key="sector.hero_id">
                    <a
                        :href="heroUrl(sector.hero_id)"
                        class="cursor-pointer hover:opacity-80 focus:outline-none outline-none ring-0"
                        tabindex="0"
                        role="link"
                        :aria-label="sector.name + ': ' + sector.games + ' игр, ' + sector.winrate + '% побед'"
                    >
                        <g
                            class="hero-wheel__slice"
                            role="img"
                            :aria-label="sector.name + ': ' + sector.games + ' игр, ' + sector.winrate + '% побед'"
                            data-wheel-tooltip
                            :data-hero-name="sector.name"
                            :data-hero-image="sector.icon"
                            :data-hero-games="sector.games"
                            :data-hero-wins="sector.wins"
                            :data-hero-losses="sector.losses"
                            :data-hero-winrate="sector.winrate"
                        >
                            <title x-text="sector.name + ': ' + sector.games + ' игр, ' + sector.winrate + '% побед'"></title>
                            <path
                                class="hero-wheel__sector"
                                :d="sector.path"
                                fill="#1c1e26"
                                stroke="#2d313e"
                                stroke-width="1.5"
                            />
                            <image
                                class="hero-wheel__icon"
                                :x="sector.x"
                                :y="sector.y"
                                :width="sector.size"
                                :height="sector.size"
                                :href="sector.icon"
                                preserveAspectRatio="xMidYMid slice"
                            />
                        </g>
                    </a>
                </template>

                <circle class="hero-wheel__hub" cx="120" cy="120" r="45" fill="#1c1e26" stroke="#2d313e" stroke-width="1" />
            </svg>

            <div class="pointer-events-none absolute inset-0 grid place-items-center">
                <div class="text-2xl font-bold" x-text="overallWinrate + '%'">{{ $overallWinrate }}%</div>
            </div>
        </div>

        <p class="mt-2 text-center text-[11px] text-mist-500" x-show="sectors.length === 0" x-cloak>Нет ranked-игр</p>
    @endif
</div>
