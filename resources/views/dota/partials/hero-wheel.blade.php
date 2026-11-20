@php
    $playerId = (int) ($playerId ?? 0);
    $cx = 160.0;
    $cy = 160.0;
    $outerRadius = 150.0;
    $innerRadius = 78.0;

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

        $items[] = [
            'hero_id' => (int) ($hero['hero_id'] ?? $hero['id'] ?? 0),
            'name' => $hero['name'] ?? $hero['localized_name'] ?? 'Герой',
            'image' => $hero['image'] ?? $hero['hero_image'] ?? $hero['icon'] ?? null,
            'icon' => $hero['icon'] ?? $hero['image'] ?? null,
            'games' => $games,
            'winrate' => $winrate,
        ];

        $totalGames += $games;
        $totalWins += $wins;
    }

    $overallWinrate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0.0;
    $heroUrlTemplate = $playerId > 0 ? route('dota.player.hero', ['accountId' => $playerId, 'heroId' => '__HERO__']) : '';

    $topHeroIds = collect($items)->sortByDesc('games')->take(3)->pluck('hero_id')->all();

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
        $iconSize = max(10.0, min(28.0, $iconMidRadius * $angle * 0.8));
        $isTop = in_array($hero['hero_id'], $topHeroIds, true);

        $sectors[] = [
            'hero_id' => $hero['hero_id'],
            'name' => $hero['name'],
            'image' => $hero['image'],
            'icon' => $hero['icon'],
            'games' => $hero['games'],
            'winrate' => $hero['winrate'],
            'fill' => $isTop ? '#4b5563' : '#2d313e',
            'path' => $path,
            'icon_x' => ($cx + $iconMidRadius * cos($mid)) - ($iconSize / 2),
            'icon_y' => ($cy + $iconMidRadius * sin($mid)) - ($iconSize / 2),
            'icon_size' => $iconSize,
        ];
    }
@endphp

@if ($sectors !== [])
    <div class="relative w-full max-w-[320px] mx-auto" style="aspect-ratio: 1/1;">
        <svg viewBox="0 0 320 320" class="w-full h-full" role="img" aria-label="Круговая диаграмма героев">
            <defs>
                <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-color="#000000" flood-opacity="0.6" />
                </filter>
            </defs>
            <g>
                @foreach ($sectors as $sector)
                    <g
                        data-hero-name="{{ $sector['name'] }}"
                        data-hero-image="{{ $sector['image'] ?? '' }}"
                        data-hero-games="{{ $sector['games'] }}"
                        data-hero-winrate="{{ number_format((float) $sector['winrate'], 1, '.', '') }}"
                    >
                        @if ($heroUrlTemplate !== '')
                            <a href="{{ str_replace('__HERO__', (string) $sector['hero_id'], $heroUrlTemplate) }}" aria-label="{{ $sector['name'] }}">
                                <path d="{{ $sector['path'] }}" fill="{{ $sector['fill'] }}" />
                                @if (! empty($sector['icon']))
                                    <image href="{{ $sector['icon'] }}" x="{{ $sector['icon_x'] }}" y="{{ $sector['icon_y'] }}" width="{{ $sector['icon_size'] }}" height="{{ $sector['icon_size'] }}" preserveAspectRatio="xMidYMid slice" filter="url(#shadow)" />
                                @endif
                            </a>
                        @else
                            <path d="{{ $sector['path'] }}" fill="{{ $sector['fill'] }}" />
                            @if (! empty($sector['icon']))
                                <image href="{{ $sector['icon'] }}" x="{{ $sector['icon_x'] }}" y="{{ $sector['icon_y'] }}" width="{{ $sector['icon_size'] }}" height="{{ $sector['icon_size'] }}" preserveAspectRatio="xMidYMid slice" filter="url(#shadow)" />
                            @endif
                        @endif
                    </g>
                @endforeach
            </g>
            <text x="160" y="160" text-anchor="middle" dominant-baseline="middle" class="text-4xl font-bold fill-white">{{ (int) round($overallWinrate) }}%</text>
        </svg>
    </div>
    <div id="wheel-tooltip" class="hidden fixed z-[9999] bg-[#1c1e26] border border-[#2d313e] p-2 rounded flex items-center gap-3 shadow-xl"></div>
@endif
