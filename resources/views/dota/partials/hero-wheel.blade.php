@php
    $wheelHeroes = [];

    foreach (($heroes ?? []) as $hero) {
        $games = max(0, (int) ($hero['games'] ?? 0));

        if ($games === 0) {
            continue;
        }

        $wheelHeroes[] = [
            'name' => $hero['name'] ?? 'Герой',
            'icon' => $hero['icon'] ?? ($hero['image'] ?? ''),
            'games' => $games,
        ];
    }

    $wheelTotal = max(1, array_sum(array_column($wheelHeroes, 'games')));
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
            'name' => $hero['name'],
            'icon' => $hero['icon'],
            'games' => $hero['games'],
            'path' => $sectorPath,
            'x' => sprintf('%.2f', $cx + $iconRadius * cos($iconRadians) - $iconSize / 2),
            'y' => sprintf('%.2f', $cy + $iconRadius * sin($iconRadians) - $iconSize / 2),
            'size' => sprintf('%.2f', $iconSize),
        ];
    }
@endphp

@if ($sectors === [])
    <div class="mt-4 grid h-48 place-items-center rounded-xl border border-dashed border-line text-xs text-mist-500">
        Нет данных
    </div>
@else
    <svg
        id="hero-wheel"
        class="hero-wheel mx-auto mt-4 block h-56 w-56 max-w-full"
        viewBox="0 0 240 240"
        role="img"
        aria-label="Колесо героев"
        xmlns="http://www.w3.org/2000/svg"
        xmlns:xlink="http://www.w3.org/1999/xlink"
    >
        <title>Колесо героев</title>

        <defs>
            @foreach ($sectors as $sector)
                <radialGradient id="hero-wheel-gradient-{{ $sector['index'] }}" cx="50%" cy="50%" r="62%">
                    @if ($sector['index'] < 3)
                        <stop offset="0%" stop-color="#e2b02b" stop-opacity="0.75" />
                        <stop offset="55%" stop-color="#3b82f6" stop-opacity="0.85" />
                    @else
                        <stop offset="0%" stop-color="#2a3042" />
                        <stop offset="55%" stop-color="#1b1f2e" />
                    @endif
                    <stop offset="100%" stop-color="#0f111a" />
                </radialGradient>
            @endforeach

            <radialGradient id="hero-wheel-hub" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#1b1f2e" />
                <stop offset="100%" stop-color="#0f111a" />
            </radialGradient>
        </defs>

        <circle cx="120" cy="120" r="112" fill="#0f111a" />

        @foreach ($sectors as $sector)
            <g class="hero-wheel__slice" tabindex="0" role="img" aria-label="{{ $sector['name'] }}: {{ $sector['games'] }} игр">
                <title>{{ $sector['name'] }}: {{ $sector['games'] }} игр</title>
                <path
                    class="hero-wheel__sector"
                    d="{{ $sector['path'] }}"
                    fill="url(#hero-wheel-gradient-{{ $sector['index'] }})"
                    stroke="#0f111a"
                    stroke-width="1.5"
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
        @endforeach

        <circle class="hero-wheel__hub" cx="120" cy="120" r="45" fill="url(#hero-wheel-hub)" stroke="#2a3042" stroke-width="1" />
        <text class="hero-wheel__hub-value" x="120" y="116" text-anchor="middle">{{ count($sectors) }}</text>
        <text class="hero-wheel__hub-label" x="120" y="132" text-anchor="middle">героев</text>
    </svg>
@endif
