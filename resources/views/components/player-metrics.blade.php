@php
    $games = (int) ($player['games'] ?? 0);
    $wins = (int) ($player['wins'] ?? 0);
    $losses = (int) ($player['losses'] ?? 0);
    $winrate = $player['winrate'] ?? null;
    $winShare = $games > 0 ? round($wins / $games * 100, 1) : 0;
    $lossShare = $games > 0 ? round($losses / $games * 100, 1) : 0;
@endphp

<div class="mt-4 flex flex-wrap gap-2" role="list" aria-label="Ключевая статистика">
    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">Матчи</p>
        <p class="mt-1 text-xl font-semibold tracking-tight text-mist-200 sm:text-2xl">{{ number_format($games, 0, '.', ' ') }}</p>
        <div class="mt-2 flex h-1.5 overflow-hidden rounded-none bg-ink-900/50">
            <div class="h-full bg-green-500" style="width: {{ $winShare }}%"></div>
            <div class="h-full bg-red-500" style="width: {{ $lossShare }}%"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[10px]">
            <span class="font-semibold text-green-500">{{ number_format($wins, 0, '.', ' ') }} побед</span>
            <span class="font-semibold text-red-500">{{ number_format($losses, 0, '.', ' ') }} поражений</span>
        </div>
    </div>

    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">Винрейт</p>
        <p class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl {{ ($winrate ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">
            {{ $winrate !== null ? number_format($winrate, 1, '.', '').'%' : '—' }}
        </p>
        <div class="mt-2 h-1.5 overflow-hidden rounded-none bg-ink-900/50">
            <div class="h-full rounded-none bg-green-500" style="width: {{ $winrate ?? 0 }}%"></div>
        </div>
    </div>

    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">MMR</p>
        <p class="mt-1 text-xl font-semibold tracking-tight text-mist-200 sm:text-2xl">
            {{ $player['mmr_estimate'] !== null ? number_format($player['mmr_estimate'], 0, '.', ' ') : '—' }}
        </p>
        <p class="mt-2 text-[10px] text-mist-500">
            {{ $player['mmr_is_estimated'] ? 'Оценка по рангу' : 'Данные OpenDota' }}
        </p>
    </div>
</div>
