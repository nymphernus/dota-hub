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
        $winrate = $games > 0 ? round($wins / $games * 100, 1) : 0.0;

        $attr = strtolower((string) ($hero['primary_attr'] ?? ''));
        $color = match ($attr) {
            'str', 'strength' => '#b73a3a',
            'agi', 'agility' => '#3ab75e',
            'int', 'intelligence' => '#3a75b7',
            'all', 'universal' => '#b73aab',
            default => '#4b5563',
        };

        $items[] = [
            'hero_id' => (int) ($hero['hero_id'] ?? $hero['id'] ?? 0),
            'name' => $hero['name'] ?? $hero['localized_name'] ?? 'Герой',
            'image' => $hero['image'] ?? $hero['hero_image'] ?? $hero['icon'] ?? null,
            'icon' => $hero['icon'] ?? $hero['image'] ?? null,
            'games' => $games,
            'winrate' => $winrate,
            'color' => $color,
        ];

        $totalGames += $games;
        $totalWins += $wins;
    }

    $overallWinrate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0.0;
    $heroUrlTemplate = $playerId > 0 ? route('dota.player.hero', ['accountId' => $playerId, 'heroId' => '__HERO__']) : '';

    $startAngle = -M_PI / 2;
    $currentAngle = $startAngle;
    $sectors = [];

    foreach ($items as $hero) {
        $angle = $totalGames > 0 ? ($hero['games'] / $totalGames) * 2 * M_PI : 0.0;
        $a1 = $currentAngle;
        $a2 = $currentAngle + $angle;
        $mid = ($a1 + $a2) / 2;
        $currentAngle = $a2;

        $x1 = $cx + $outerRadius * cos($a1);
        $y1 = $cy + $outerRadius * sin($a1);
        $x2 = $cx + $outerRadius * cos($a2);
        $y2 = $cy + $outerRadius * sin($a2);
        $x3 = $cx + $innerRadius * cos($a2);
        $y3 = $cy + $innerRadius * sin($a2);
        $x4 = $cx + $innerRadius * cos($a1);
        $y4 = $cy + $innerRadius * sin($a1);
        $largeArc = $angle > M_PI ? 1 : 0;
        $path = sprintf(
            'M %.3F %.3F A %.3F %.3F 0 %d 1 %.3F %.3F L %.3F %.3F A %.3F %.3F 0 %d 0 %.3F %.3F Z',
            $x1, $y1, $outerRadius, $outerRadius, $largeArc, $x2, $y2,
            $x3, $y3, $innerRadius, $innerRadius, $largeArc, $x4, $y4
        );

        $iconMidRadius = ($innerRadius + $outerRadius) / 2;
        $iconSize = max(10.0, min(26.0, $iconMidRadius * $angle * 0.8));

        $sectors[] = [
            'hero_id' => $hero['hero_id'],
            'name' => $hero['name'],
            'image' => $hero['image'],
            'icon' => $hero['icon'],
            'games' => $hero['games'],
            'winrate' => $hero['winrate'],
            'color' => $hero['color'],
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
                    <g
                        class="hover:scale-105"
                        style="transform-box: fill-box; transform-origin: center;"
                        data-hero-name="{{ $sector['name'] }}"
                        data-hero-image="{{ $sector['image'] ?? '' }}"
                        data-hero-games="{{ $sector['games'] }}"
                        data-hero-winrate="{{ number_format((float) $sector['winrate'], 1, '.', '') }}"
                    >
                        <title>{{ $sector['name'] }}: {{ $sector['games'] }} игр, {{ number_format((float) $sector['winrate'], 1, '.', '') }}% побед</title>
                        @if ($heroUrlTemplate !== '')
                            <a href="{{ str_replace('__HERO__', (string) $sector['hero_id'], $heroUrlTemplate) }}" aria-label="{{ $sector['name'] }}">
                                <path d="{{ $sector['path'] }}" fill="{{ $sector['color'] }}" stroke="#15171e" stroke-width="1.5" />
                                @if (! empty($sector['icon']))
                                    <image href="{{ $sector['icon'] }}" x="{{ $sector['icon_x'] }}" y="{{ $sector['icon_y'] }}" width="{{ $sector['icon_size'] }}" height="{{ $sector['icon_size'] }}" preserveAspectRatio="xMidYMid slice" />
                                @endif
                            </a>
                        @else
                            <path d="{{ $sector['path'] }}" fill="{{ $sector['color'] }}" stroke="#15171e" stroke-width="1.5" />
                            @if (! empty($sector['icon']))
                                <image href="{{ $sector['icon'] }}" x="{{ $sector['icon_x'] }}" y="{{ $sector['icon_y'] }}" width="{{ $sector['icon_size'] }}" height="{{ $sector['icon_size'] }}" preserveAspectRatio="xMidYMid slice" />
                            @endif
                        @endif
                    </g>
                @endforeach
                <text x="120" y="120" text-anchor="middle" dominant-baseline="middle" class="text-xl font-bold fill-white">{{ (int) round($overallWinrate) }}%</text>
            </svg>
        </div>
    </div>
    <div id="wheel-tooltip" class="hidden fixed z-[9999] bg-[#1c1e26] border border-[#2d313e] p-2 rounded flex items-center gap-3 shadow-xl"></div>
@endif
