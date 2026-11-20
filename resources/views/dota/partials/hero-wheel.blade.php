@php
    $playerId = (int) ($playerId ?? 0);
    $cx = 120.0;
    $cy = 120.0;
    $outerRadius = 110.0;
    $innerRadius = 58.0;

    $items = [];
    $totalGames = 0;
    $totalWins = 0;

    foreach ($allHeroes as $hero) {
        if (! is_array($hero)) {
            continue;
        }

        $games = max(0, (int) ($hero['games'] ?? 0));
        if ($games === 0) {
            continue;
        }

        $wins = max(0, (int) ($hero['wins'] ?? 0));
        $losses = max(0, $games - $wins);
        $winrate = $games > 0 ? round($wins / $games * 100, 1) : 0.0;

        $items[] = [
            'hero_id' => (int) ($hero['hero_id'] ?? $hero['id'] ?? 0),
            'name' => $hero['name'] ?? $hero['localized_name'] ?? 'Герой',
            'icon' => $hero['icon'] ?? $hero['image'] ?? null,
            'games' => $games,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $winrate,
        ];

        $totalGames += $games;
        $totalWins += $wins;
    }

    usort($items, fn (array $a, array $b) => [$b['games'], $b['winrate']] <=> [$a['games'], $a['winrate']]);

    $topItems = array_slice($items, 0, 20);
    $tailItems = array_slice($items, 20);
    $tailGames = array_sum(array_column($tailItems, 'games'));
    $tailWins = array_sum(array_column($tailItems, 'wins'));

    if ($tailGames > 0) {
        $topItems[] = [
            'hero_id' => 0,
            'name' => 'Другие',
            'icon' => null,
            'games' => $tailGames,
            'wins' => $tailWins,
            'losses' => max(0, $tailGames - $tailWins),
            'winrate' => round($tailWins / $tailGames * 100, 1),
        ];
    }

    $palette = ['#34d399', '#22d3ee', '#818cf8', '#fb923c', '#fbbf24', '#fb7185', '#a78bfa', '#38bdf8'];
    $sectors = [];
    $currentAngle = -M_PI_2;
    $effectiveTotal = array_sum(array_column($topItems, 'games'));

    foreach ($topItems as $index => $item) {
        $fraction = $effectiveTotal > 0 ? ($item['games'] / $effectiveTotal) : 0;
        $angle = $fraction * M_PI * 2;

        if ($effectiveTotal > 0 && $index === count($topItems) - 1) {
            $endAngle = 1.5 * M_PI;
        } else {
            $endAngle = $currentAngle + $angle;
        }

        $gap = 0.012;
        $sectorStart = $currentAngle + $gap;
        $sectorEnd = max($sectorStart, $endAngle - $gap);
        $largeArc = ($sectorEnd - $sectorStart) > M_PI ? 1 : 0;

        $x1 = $cx + $outerRadius * cos($sectorStart);
        $y1 = $cy + $outerRadius * sin($sectorStart);
        $x2 = $cx + $outerRadius * cos($sectorEnd);
        $y2 = $cy + $outerRadius * sin($sectorEnd);
        $x3 = $cx + $innerRadius * cos($sectorEnd);
        $y3 = $cy + $innerRadius * sin($sectorEnd);
        $x4 = $cx + $innerRadius * cos($sectorStart);
        $y4 = $cy + $innerRadius * sin($sectorStart);

        $path = sprintf(
            'M %.4f %.4f A %.4f %.4f 0 %d 1 %.4f %.4f L %.4f %.4f A %.4f %.4f 0 %d 0 %.4f %.4f Z',
            $x1, $y1,
            $outerRadius, $outerRadius, $largeArc, $x2, $y2,
            $x3, $y3,
            $innerRadius, $innerRadius, $largeArc, $x4, $y4,
        );

        $midAngle = ($sectorStart + $sectorEnd) / 2;
        $iconRadius = ($outerRadius + $innerRadius) / 2;
        $iconSize = min(20, max(14, ($outerRadius - $innerRadius) * 0.55));

        $sectors[] = [
            'path' => $path,
            'color' => $palette[$index % count($palette)],
            'name' => $item['name'],
            'icon' => $item['icon'],
            'hero_id' => $item['hero_id'],
            'games' => $item['games'],
            'wins' => $item['wins'],
            'losses' => $item['losses'],
            'winrate' => $item['winrate'],
            'icon_x' => round($cx + $iconRadius * cos($midAngle) - ($iconSize / 2), 2),
            'icon_y' => round($cy + $iconRadius * sin($midAngle) - ($iconSize / 2), 2),
            'icon_size' => round($iconSize, 2),
        ];

        $currentAngle = $endAngle;
    }

    $overallWinrate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0.0;
@endphp

@if ($sectors !== [])
    <script type="application/json" id="hero-wheel-sectors-data">@json($sectors)</script>
    <div
        id="hero-wheel-data"
        class="hidden"
        data-url-template="{{ route('dota.player.hero', ['accountId' => $playerId, 'heroId' => '__HERO__']) }}"
        data-overall-winrate="{{ $overallWinrate }}"
    ></div>

    <div
        class="relative flex flex-col items-center justify-center py-2"
        x-data="heroWheel()"
    >
        <div class="relative">
            <svg viewBox="0 0 240 240" class="w-full max-w-[240px] drop-shadow-2xl" role="img" aria-label="Герои игрока по количеству игр">
                <template x-for="(sector, index) in sectors" :key="index">
                    <g class="transition-opacity duration-150 hover:opacity-90">
                        <title x-text="sector.name"></title>
                        <a :href="sector.hero_id ? sectorUrl(sector.hero_id) : null" :aria-label="sector.name">
                            <path
                                :d="sector.path"
                                :fill="sector.color"
                                stroke="#15171e"
                                stroke-width="1.5"
                            />
                            <template x-if="sector.icon">
                                <image
                                    :href="sector.icon"
                                    :x="sector.icon_x"
                                    :y="sector.icon_y"
                                    :width="sector.icon_size"
                                    :height="sector.icon_size"
                                    preserveAspectRatio="xMidYMid slice"
                                    clip-path="circle(50%)"
                                    pointer-events="none"
                                />
                            </template>
                        </a>
                    </g>
                </template>
            </svg>

            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-[9px] font-bold uppercase tracking-widest text-mist-500">Общий WR</span>
                <span class="text-xl font-black leading-none tracking-tight text-mist-200" x-text="centerWinrate"></span>
            </div>
        </div>

        <div class="mt-2 grid w-full grid-cols-2 gap-x-3 gap-y-1">
            <template x-for="(sector, index) in sectors.slice(0, 8)" :key="'legend-'+index">
                <div class="flex items-center gap-1.5">
                    <span class="h-2 w-2 shrink-0 rounded-full" :style="'background-color:' + sector.color"></span>
                    <span class="truncate text-[10px] leading-tight text-mist-300" x-text="sector.name"></span>
                    <span class="ml-auto font-mono text-[10px] text-mist-500" x-text="sector.games"></span>
                </div>
            </template>
        </div>
    </div>
@endif
