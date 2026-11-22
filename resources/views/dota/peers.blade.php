@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Союзники - DotaHub'))
@section('description', 'Игроки, с которыми '.$player['nickname'].' чаще всего играл в Dota 2: совместные игры и винрейт.')

@section('profile-content')
    <section class="mt-4 border border-[#2d313e] bg-[#1c1e26]">
        <div class="flex items-center justify-between gap-2 border-b border-[#2d313e] px-4 py-3">
            <h2 class="section-heading">Союзники</h2>
            <span class="text-[10px] text-mist-500">{{ count($player['peers'] ?? []) }}</span>
        </div>

        @if (($player['peers'] ?? []) === [])
            <div class="p-10 text-center text-sm text-mist-400">Нет данных о союзниках.</div>
        @else
            <div class="divide-y divide-[#2d313e]">
                @foreach ($player['peers'] as $peer)
                    @php
                        $games = (int) ($peer['games'] ?? 0);
                        $winrate = $peer['winrate'] !== null ? (float) $peer['winrate'] : null;
                        $wins = $winrate !== null ? (int) round($games * $winrate / 100) : 0;
                        $losses = max(0, $games - $wins);
                        $wrClass = $winrate !== null && $winrate >= 50 ? 'text-green-500' : 'text-red-500';
                    @endphp
                    <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="flex items-center gap-4 px-4 py-3 transition hover:bg-ink-900/30">
                        <span class="h-10 w-10 shrink-0 overflow-hidden bg-ink-800">
                            @if (!empty($peer['avatar']))
                                <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] ?? 'Игрок' }}" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold text-mist-200">{{ $peer['nickname'] ?? 'Игрок' }}</span>
                                @if ($peer['is_private'] ?? false)
                                    <span class="text-[10px] text-accent-300/80">Приватный</span>
                                @endif
                            </div>
                            <div class="mt-1 flex h-1.5 w-full max-w-[240px] overflow-hidden bg-ink-900/60">
                                <div class="h-full bg-green-500" style="width: {{ $winrate ?? 0 }}%"></div>
                                <div class="h-full bg-red-500" style="width: {{ max(0, 100 - ($winrate ?? 0)) }}%"></div>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="text-xs text-mist-300 font-mono">{{ number_format($games, 0, '.', ' ') }} совместных игр</div>
                            <div class="text-xs font-mono {{ $wrClass }}">{{ $winrate !== null ? number_format($winrate, 1, '.', '').'%' : '—' }} винрейт</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
