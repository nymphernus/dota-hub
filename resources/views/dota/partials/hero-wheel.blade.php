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

    $overallWinrate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0.0;
    $heroUrlTemplate = $playerId > 0 ? route('dota.player.hero', ['accountId' => $playerId, 'heroId' => '__HERO__']) : '';

    $sectorCount = count($items);
    $anglePerSector = $sectorCount > 0 ? (2 * M_PI) / $sectorCount : 0.0;
    $startAngle = -M_PI / 2;
    $sectors = [];

    foreach (array_values($items) as $index => $hero) {
        $a1 = $startAngle + ($index * $anglePerSector);
        $a2 = $a1 + $anglePerSector;
        $mid = ($a1 + $a2) / 2;

        $x1 = $cx + $outerRadius * cos($a1);
        $y1 = $cy + $outerRadius * sin($a1);
        $x2 = $cx + $outerRadius * cos($a2);
        $y2 = $cy + $outerRadius * sin($a2);
        $x3 = $cx + $innerRadius * cos($a2);
        $y3 = $cy + $innerRadius * sin($a2);
        $x4 = $cx + $innerRadius * cos($a1);
        $y4 = $cy + $innerRadius * sin($a1);
        $largeArc = $anglePerSector > M_PI ? 1 : 0;
        $path = sprintf(
            'M %.3F %.3F A %.3F %.3F 0 %d 1 %.3F %.3F L %.3F %.3F A %.3F %.3F 0 %d 0 %.3F %.3F Z',
            $x1, $y1, $outerRadius, $outerRadius, $largeArc, $x2, $y2,
            $x3, $y3, $innerRadius, $innerRadius, $largeArc, $x4, $y4
        );

        $iconMidRadius = ($innerRadius + $outerRadius) / 2;
        $iconSize = max(14.0, min(24.0, $iconMidRadius * $anglePerSector * 0.72));

        $lossRatio = $hero['games'] > 0 ? $hero['losses'] / $hero['games'] : 0.0;
        $lossRatio = max(0.0, min(1.0, $lossRatio));
        $red = (int) round(46 + (239 - 46) * $lossRatio);
        $green = (int) round(204 + (68 - 204) * $lossRatio);
        $blue = (int) round(113 + (68 - 113) * $lossRatio);

        $sectors[] = [
            'hero_id' => $hero['hero_id'],
            'name' => $hero['name'],
            'icon' => $hero['icon'],
            'games' => $hero['games'],
            'wins' => $hero['wins'],
            'losses' => $hero['losses'],
            'winrate' => $hero['winrate'],
            'color' => sprintf('#%02x%02x%02x', $red, $green, $blue),
            'path' => $path,
            'icon_x' => ($cx + $iconMidRadius * cos($mid)) - ($iconSize / 2),
            'icon_y' => ($cy + $iconMidRadius * sin($mid)) - ($iconSize / 2),
            'icon_size' => $iconSize,
        ];
    }
@endphp

@if ($sectors !== [])
    <div class="mt-3 flex flex-col items-center">
        <div class="relative aspect-square w-full max-w-[220px]">
            <svg class="h-full w-full" viewBox="0 0 240 240" role="img" aria-label="Круговая диаграмма героев">
                @foreach ($sectors as $sector)
                    <g>
                        <title>{{ $sector['name'] }}</title>
                        <a href="{{ $heroUrlTemplate !== '' ? str_replace('__HERO__', (string) $sector['hero_id'], $heroUrlTemplate) : '#' }}" aria-label="{{ $sector['name'] }}">
                            <path d="{{ $sector['path'] }}" fill="{{ $sector['color'] }}" stroke="#15171e" stroke-width="1.5" />
                            @if (! empty($sector['icon']))
                                <image href="{{ $sector['icon'] }}" x="{{ $sector['icon_x'] }}" y="{{ $sector['icon_y'] }}" width="{{ $sector['icon_size'] }}" height="{{ $sector['icon_size'] }}" preserveAspectRatio="xMidYMid slice" pointer-events="none" />
                            @endif
                        </a>
                    </g>
                @endforeach
            </svg>

            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <span class="text-xl font-black leading-none tracking-tight text-mist-200">{{ (int) round($overallWinrate) }}%</span>
            </div>
        </div>
    </div>
@endif
