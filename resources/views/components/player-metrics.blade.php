@php
    $games = (int) ($player['games'] ?? 0);
    $wins = (int) ($player['wins'] ?? 0);
    $losses = (int) ($player['losses'] ?? 0);
    $winrate = $player['winrate'] ?? null;
    $winrateValue = $winrate !== null ? (float) $winrate : 0.0;

    $history = $player['match_history'] ?? ($player['matches'] ?? []);
    $timestamps = [];
    foreach ($history as $m) {
        $ts = $m['start_time'] ?? null;
        if ($ts === null && ! empty($m['started_at'])) {
            $ts = \Carbon\CarbonImmutable::parse($m['started_at'])->getTimestamp();
        }
        if (is_numeric($ts) && (int) $ts > 0) {
            $timestamps[] = (int) $ts;
        }
    }
    $firstMatch = $timestamps ? \Carbon\CarbonImmutable::createFromTimestampUTC(min($timestamps)) : null;
    $lastMatch = $timestamps ? \Carbon\CarbonImmutable::createFromTimestampUTC(max($timestamps)) : null;
    $monthsRu = [1 => 'янв.', 2 => 'фев.', 3 => 'мар.', 4 => 'апр.', 5 => 'мая', 6 => 'июн.', 7 => 'июл.', 8 => 'авг.', 9 => 'сент.', 10 => 'окт.', 11 => 'нояб.', 12 => 'дек.'];
    $fmt = function ($dt) use ($monthsRu) {
        return $dt ? ($dt->day . ' ' . ($monthsRu[(int) $dt->format('n')] ?? $dt->format('M')) . ' ' . $dt->format('Y')) : '—';
    };

    $segmentCount = $games > 0 ? (int) ceil($games / 100) : 0;
@endphp

<div class="mt-4 flex flex-wrap gap-3" role="list" aria-label="{{ __('ui.lifetime_stats') }}">
    <div class="card min-w-[150px] flex-1 p-3" role="listitem">
        <p class="text-sm text-mist-400 font-bold uppercase">{{ __('ui.matches') }}</p>
        <p class="mt-1 text-4xl font-black text-mist-200">{{ number_format($games, 0, '.', ' ') }}</p>
        <div class="mt-1 text-[10px] text-mist-500">
            <div>{{ __('ui.first_match') }} {{ $fmt($firstMatch) }}</div>
            <div>{{ __('ui.last_match') }} {{ $fmt($lastMatch) }}</div>
        </div>
        @if ($segmentCount > 0)
            <div class="mt-2 flex gap-1">
                @for ($i = 0; $i < $segmentCount; $i++)
                    @php
                        $blockGames = min(100, max(0, $games - ($i * 100)));
                        $fill = $blockGames / 100 * 100;
                    @endphp
                    <div class="h-2 flex-1 overflow-hidden rounded-sm bg-ink-900/50">
                        <div class="h-full bg-yellow-500/80" style="width: {{ $fill }}%"></div>
                    </div>
                @endfor
            </div>
        @endif
    </div>

    <div class="card min-w-[150px] flex-1 p-3" role="listitem">
        <p class="text-sm text-mist-400 font-bold uppercase">{{ __('ui.winrate') }}</p>
        <p class="mt-1 text-4xl font-black">
            @if ($winrate !== null)
                <span class="{{ $winrateValue >= 50 ? 'text-green-500' : 'text-red-500' }}">{{ number_format($winrateValue, 1, '.', '') }}%</span>
            @else
                <span class="text-mist-200">—</span>
            @endif
        </p>
        <div class="mt-2 flex h-2 w-full rounded overflow-hidden">
            <div class="h-full bg-yellow-500" style="width: {{ $winrateValue }}%"></div>
            <div class="h-full bg-red-500" style="width: {{ max(0, 100 - $winrateValue) }}%"></div>
        </div>
        <div class="flex justify-between text-xs text-mist-400 mt-1">
            <span class="text-yellow-500">{{ $wins }} {{ __('ui.wins_lower') }}</span>
            <span class="text-red-500">{{ $losses }} {{ __('ui.losses_lower') }}</span>
        </div>
    </div>

    <div class="card min-w-[150px] flex-1 p-3" role="listitem">
        <p class="text-sm text-mist-400 font-bold uppercase">{{ __('ui.mmr') }}</p>
        <p class="mt-1 text-4xl font-black text-mist-200">
            {{ $player['mmr_estimate'] !== null ? number_format($player['mmr_estimate'], 0, '.', ' ') : '—' }}
        </p>
    </div>
</div>
