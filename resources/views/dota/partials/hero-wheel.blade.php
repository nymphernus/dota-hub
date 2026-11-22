@php
    $heroes = $heroes ?? $allHeroes ?? [];
    $wheelHeroes = [];
    $playerId = (int) ($playerId ?? 0);

    foreach ($heroes as $hero) {
        $games = max(0, (int) ($hero['games'] ?? 0));

        if ($games === 0) {
            continue;
        }

        $wins = max(0, (int) ($hero['win'] ?? $hero['wins'] ?? 0));
        $losses = max(0, $games - $wins);

        $wheelHeroes[] = [
            'hero_id' => (int) ($hero['hero_id'] ?? $hero['id'] ?? 0),
            'name' => $hero['name'] ?? 'Герой',
            'icon' => $hero['icon'] ?? ($hero['image'] ?? ''),
            'image' => $hero['image'] ?? $hero['hero_image'] ?? $hero['icon'] ?? '',
            'games' => $games,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $games > 0 ? round($wins / $games * 100, 1) : 0,
        ];
    }

    $wheelTotal = max(1, array_sum(array_column($wheelHeroes, 'games')));
    $wheelTotalWins = array_sum(array_column($wheelHeroes, 'wins'));
    $wheelTotalLosses = array_sum(array_column($wheelHeroes, 'losses'));
    $wheelOverallWinrate = $wheelTotal > 0 ? round($wheelTotalWins / $wheelTotal * 100, 1) : 0;
    $topHeroIds = collect($wheelHeroes)->sortByDesc('games')->take(3)->pluck('hero_id')->all();
    $wheelMax = max([1, ...array_column($wheelHeroes, 'games')]);
    $wheelGap = count($wheelHeroes) > 1 ? 1.4 : 0;
    $wheelCursor = -90.0;
    $sectors = [];
    $cx = 120.0;
    $cy = 120.0;
    $outerRadius = 110.0;
    $innerRadius = 58.0;

    foreach ($wheelHeroes as $index => $hero) {
        $sweep = ($hero['games'] / $wheelTotal) * 360;
        $start = $wheelCursor + $wheelGap / 2;
        $end = $wheelCursor + $sweep - $wheelGap / 2;
        $wheelCursor += $sweep;

        if ($end <= $start) {
            continue;
        }

        $point = static function (float $radius, float $angle) use ($cx, $cy): string {
            $radians = deg2rad($angle);

            return sprintf('%.2f %.2f', $cx + $radius * cos($radians), $cy + $radius * sin($radians));
        };

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

        $sectors[] = [
            'index' => $index,
            'hero_id' => $hero['hero_id'],
            'name' => $hero['name'],
            'icon' => $hero['icon'],
            'image' => $hero['image'],
            'games' => $hero['games'],
            'wins' => $hero['wins'],
            'losses' => $hero['losses'],
            'winrate' => $hero['winrate'],
            'is_top' => in_array($hero['hero_id'], $topHeroIds, true),
            'path' => $sectorPath,
            'x' => sprintf('%.2f', $cx + $iconRadius * cos($iconRadians) - $iconSize / 2),
            'y' => sprintf('%.2f', $cy + $iconRadius * sin($iconRadians) - $iconSize / 2),
            'size' => sprintf('%.2f', $iconSize),
        ];
    }
@endphp

<div id="wheel-tooltip" class="hidden fixed z-50 bg-[#1c1e26] border border-[#2d313e] rounded p-2 text-white shadow-xl flex items-center gap-3"></div>

@if ($sectors === [])
    <div class="mt-4 grid h-48 place-items-center rounded-none border border-dashed border-line text-xs text-mist-400">
        Нет данных
    </div>
@else
    <div class="relative w-full max-w-[300px] mx-auto h-[300px]">
        <svg
            id="hero-wheel"
            class="hero-wheel block w-full h-full"
            viewBox="0 0 240 240"
            role="img"
            aria-label="Колесо героев"
            xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink"
        >
            <circle cx="120" cy="120" r="112" fill="#111318" />

            @foreach ($sectors as $sector)
                <a
                    href="{{ route('dota.player.hero', [$playerId, $sector['hero_id']]) }}"
                    class="cursor-pointer hover:opacity-80 focus:outline-none outline-none ring-0"
                    tabindex="0"
                    role="link"
                    aria-label="{{ $sector['name'] }}: {{ $sector['games'] }} игр, {{ $sector['winrate'] }}% побед"
                >
                    <g
                        class="hero-wheel__slice"
                        role="img"
                        aria-label="{{ $sector['name'] }}: {{ $sector['games'] }} игр, {{ $sector['winrate'] }}% побед"
                        data-wheel-tooltip
                        data-hero-name="{{ $sector['name'] }}"
                        data-hero-image="{{ $sector['image'] }}"
                        data-hero-games="{{ $sector['games'] }}"
                        data-hero-wins="{{ $sector['wins'] }}"
                        data-hero-losses="{{ $sector['losses'] }}"
                        data-hero-winrate="{{ $sector['winrate'] }}"
                    >
                        <path
                            class="hero-wheel__sector"
                            d="{{ $sector['path'] }}"
                            fill="{{ $sector['is_top'] ? '#3f4655' : '#2d313e' }}"
                        />
                        @if ($sector['icon'] !== '')
                            <image
                                class="hero-wheel__icon"
                                x="{{ $sector['x'] }}"
                                y="{{ $sector['y'] }}"
                                width="{{ $sector['size'] }}"
                                height="{{ $sector['size'] }}"
                                href="{{ $sector['icon'] }}"
                                xlink:href="{{ $sector['icon'] }}"
                                preserveAspectRatio="xMidYMid slice"
                            />
                        @endif
                    </g>
                </a>
            @endforeach

            <circle class="hero-wheel__hub" cx="120" cy="120" r="45" fill="#1c1e26" stroke="#2d313e" stroke-width="1" />
            <text x="120" y="112" text-anchor="middle" dominant-baseline="middle" class="text-3xl font-bold" fill="{{ $wheelOverallWinrate >= 50 ? '#10b981' : '#ef4444' }}">{{ number_format($wheelOverallWinrate, 1, '.', '') }}%</text>
            <text x="120" y="134" text-anchor="middle" dominant-baseline="middle" class="text-xs font-semibold" fill="#d1d5db">
                <tspan fill="#10b981">{{ $wheelTotalWins }}W</tspan>
                <tspan fill="#8b94a5"> / </tspan>
                <tspan fill="#ef4444">{{ $wheelTotalLosses }}L</tspan>
            </text>
        </svg>
    </div>
@endif
