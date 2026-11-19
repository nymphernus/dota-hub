@extends('layouts.app')

@section('title', 'Союзники '.$player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Игроки, с которыми '.$player['nickname'].' чаще всего играл: совместные матчи, винрейт и дата последней игры.')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <a href="{{ route('dota.player', ['accountId' => $player['account_id']]) }}" class="transition hover:text-white">{{ $player['nickname'] }}</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-slate-300">Союзники</span>
        </nav>

        <x-player-header :player="$player" :steamProfile="$steamProfile" />

        <x-player-tabs :player="$player" active="peers" />

        <div class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-4 sm:px-5">
                <div>
                    <p class="label">Совместные матчи</p>
                    <h2 class="mt-1 text-lg font-bold text-white">Союзники</h2>
                </div>
                <span class="badge border-white/10 bg-white/5 text-mist-400">{{ count($player['peers'] ?? []) }}</span>
            </div>

            @if (($player['peers'] ?? []) === [])
                <div class="p-10 text-center text-sm text-mist-400">Нет данных о совместных матчах</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-5">Игрок</th>
                                <th class="px-4 py-3 font-semibold">Матчи вместе</th>
                                <th class="px-4 py-3 font-semibold">Процент побед</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-5">Последний матч</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            @foreach ($player['peers'] as $peer)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 sm:px-5">
                                        <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="group flex items-center gap-3">
                                            <span class="h-9 w-9 shrink-0 overflow-hidden rounded-lg bg-ink-800 ring-1 ring-white/10">
                                                @if ($peer['avatar'])
                                                    <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] }}" class="h-full w-full object-cover" loading="lazy">
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="flex items-center gap-1.5 truncate text-[13px] font-semibold text-white transition group-hover:text-radar-200">
                                                    {{ $peer['nickname'] }}
                                                    @if ($peer['is_private'] ?? false)
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-label="Скрытый профиль" title="Скрытый профиль"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 018 0v3" stroke-linecap="round" /></svg>
                                                    @endif
                                                </span>
                                                @if ($peer['is_private'] ?? false)
                                                    <span class="text-[9px] text-amber-300/80">Скрытый профиль</span>
                                                @endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($peer['games'], 0, '.', ' ') }}</td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums {{ ($peer['winrate'] ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $peer['winrate'] !== null ? number_format($peer['winrate'], 1, '.', '').'%' : '—' }}</td>
                                    <td class="px-4 py-3 text-right text-[11px] text-mist-400 sm:px-5">
                                        {{ $peer['last_played_at'] ? \Carbon\CarbonImmutable::parse($peer['last_played_at'])->format('d.m.Y') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
