@php
    $playerId = (int) ($playerId ?? 0);
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
            'icon' => $hero['icon'] ?? $hero['image'] ?? null,
            'image' => $hero['image'] ?? $hero['hero_image'] ?? $hero['icon'] ?? null,
            'games' => $games,
            'winrate' => $winrate,
        ];

        $totalGames += $games;
        $totalWins += $wins;
    }

    $overallWinrate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0.0;
    $heroUrlTemplate = $playerId > 0 ? route('dota.player.hero', ['accountId' => $playerId, 'heroId' => '__HERO__']) : '';

    $topHeroIds = collect($items)
        ->sortByDesc('games')
        ->take(3)
        ->pluck('hero_id')
        ->all();

    $chartHeroes = [];
    foreach ($items as $hero) {
        $isTop = in_array($hero['hero_id'], $topHeroIds, true);
        $chartHeroes[] = [
            'id' => $hero['hero_id'],
            'name' => $hero['name'],
            'games' => $hero['games'],
            'winrate' => $hero['winrate'],
            'icon' => $hero['icon'],
            'image' => $hero['image'],
            'color' => $isTop ? '#4b5563' : '#2d313e',
            'url' => $heroUrlTemplate !== '' ? str_replace('__HERO__', (string) $hero['hero_id'], $heroUrlTemplate) : null,
        ];
    }

    $chartPayload = [
        'heroes' => $chartHeroes,
        'overallWinrate' => $overallWinrate,
    ];
@endphp

@if ($chartHeroes !== [])
    <div class="mt-3 flex flex-col items-center">
        <div class="relative w-full max-w-[320px] mx-auto h-[320px]">
            <canvas id="heroWheelChart"></canvas>
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <span class="text-3xl font-bold text-white">{{ (int) round($overallWinrate) }}%</span>
            </div>
        </div>
    </div>
    <script type="application/json" id="hero-wheel-chart-data">@json($chartPayload)</script>
    <div id="hero-tooltip" class="hidden fixed z-[9999] bg-[#1c1e26] border border-[#2d313e] p-2 rounded shadow-xl items-center gap-3 pointer-events-none"></div>
@endif
