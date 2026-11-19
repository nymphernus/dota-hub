@php
    $games = (int) ($player['games'] ?? 0);
    $wins = (int) ($player['wins'] ?? 0);
    $losses = (int) ($player['losses'] ?? 0);
    $winrate = $player['winrate'] ?? null;
    $winShare = $games > 0 ? round($wins / $games * 100, 1) : 0;
    $lossShare = $games > 0 ? round($losses / $games * 100, 1) : 0;
    $kda = $player['averages']['kda'] ?? null;
@endphp

<div class="mt-4 flex flex-wrap gap-2" role="list" aria-label="Ключевая статистика">
    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">Матчи</p>
        <p class="mt-1 text-xl font-black tracking-tight text-white sm:text-2xl">{{ number_format($games, 0, '.', ' ') }}</p>
        <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-white/[0.05]">
            <div class="h-full bg-amber-400" style="width: {{ $winShare }}%"></div>
            <div class="h-full bg-rose-500" style="width: {{ $lossShare }}%"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[10px]">
            <span class="font-semibold text-amber-300">{{ number_format($wins, 0, '.', ' ') }} побед</span>
            <span class="font-semibold text-rose-300">{{ number_format($losses, 0, '.', ' ') }} поражений</span>
        </div>
    </div>

    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">Винрейт</p>
        <p class="mt-1 text-xl font-black tracking-tight sm:text-2xl {{ ($winrate ?? 0) >= 50 ? 'text-amber-300' : 'text-rose-300' }}">
            {{ $winrate !== null ? number_format($winrate, 1, '.', '').'%' : '—' }}
        </p>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.05]">
            <div class="h-full rounded-full {{ ($winrate ?? 0) >= 50 ? 'bg-gradient-to-r from-amber-500 to-amber-300' : 'bg-gradient-to-r from-rose-600 to-rose-400' }}" style="width: {{ $winrate ?? 0 }}%"></div>
        </div>
        <p class="mt-1.5 text-[10px] text-mist-500">{{ $wins }} из {{ $games }} побед</p>
    </div>

    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">KDA</p>
        <p class="mt-1 text-xl font-black tracking-tight text-white sm:text-2xl">{{ $games > 0 && $kda !== null ? number_format($kda, 2, '.', '') : '—' }}</p>
        <p class="mt-2 text-[10px] text-mist-500">K + A / D</p>
    </div>

    <div class="card min-w-[150px] flex-1 p-3 sm:p-4" role="listitem">
        <p class="label">MMR</p>
        <p class="mt-1 text-xl font-black tracking-tight text-white sm:text-2xl">
            {{ $player['mmr_estimate'] !== null ? number_format($player['mmr_estimate'], 0, '.', ' ') : '—' }}
        </p>
        <p class="mt-2 text-[10px] text-mist-500">
            {{ $player['mmr_is_estimated'] ? 'Оценка по рангу' : 'Данные OpenDota' }}
        </p>
    </div>
</div>
