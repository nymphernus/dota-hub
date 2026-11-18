@extends('layouts.app')

@section('title', 'Матч #'.$match['match_id'].' — Dota 2 — Stats Radar')
@section('description', 'Детальная статистика матча Dota 2 #'.$match['match_id'].': счёт, длительность, герои, K/D/A, GPM, XPM и урон игроков Radiant и Dire.')

@php
    $radiantWin = $match['radiant_win'];
    $winner = $radiantWin ? 'Radiant' : 'Dire';
    $startedAt = $match['started_at'] ? \Carbon\CarbonImmutable::parse($match['started_at']) : null;
    $teams = [
        'radiant' => [
            'label' => 'Radiant',
            'won' => $radiantWin,
            'score' => $match['radiant_score'],
            'players' => $match['players']['radiant'],
        ],
        'dire' => [
            'label' => 'Dire',
            'won' => ! $radiantWin,
            'score' => $match['dire_score'],
            'players' => $match['players']['dire'],
        ],
    ];
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-mist-400">Dota 2</span>
            @if ($highlightPlayerId)
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
                <a href="{{ route('dota.player', ['accountId' => $highlightPlayerId]) }}" class="transition hover:text-white">Профиль игрока</a>
            @endif
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="truncate text-slate-300">Матч #{{ $match['match_id'] }}</span>
        </nav>

        <section class="card relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-r {{ $radiantWin ? 'from-emerald-500/25 via-ink-800/40' : 'from-rose-500/25 via-ink-800/40' }} to-cyan-500/10" aria-hidden="true"></div>
            <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full {{ $radiantWin ? 'bg-emerald-500/10' : 'bg-rose-500/10' }} blur-3xl" aria-hidden="true"></div>

            <div class="relative px-4 pb-6 pt-14 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge border-dota-500/25 bg-dota-500/10 text-dota-300">Dota 2</span>
                            <span @class([
                                'badge',
                                'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $radiantWin,
                                'border-rose-500/25 bg-rose-500/10 text-rose-300' => ! $radiantWin,
                            ])>
                                Победа {{ $winner }}
                            </span>
                            @if ($match['game_mode_label'])
                                <span class="badge border-white/10 bg-white/5 text-mist-300">{{ $match['game_mode_label'] }}</span>
                            @endif
                        </div>

                        <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Матч <span class="font-mono text-2xl text-mist-300 sm:text-3xl">#{{ $match['match_id'] }}</span>
                        </h1>

                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-mist-400">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" /></svg>
                                {{ $match['duration_label'] }}
                            </span>
                            @if ($startedAt)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3v4M16 3v4M4 10h16" stroke-linecap="round" /></svg>
                                    <time datetime="{{ $match['started_at'] }}" title="{{ $startedAt->format('d.m.Y H:i') }}">{{ $startedAt->format('d.m.Y H:i') }}</time>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <div class="rounded-xl border border-emerald-500/15 bg-emerald-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-emerald-300/70">Radiant</p>
                            <p class="text-xl font-black text-emerald-300">{{ $match['radiant_score'] }}</p>
                        </div>
                        <span class="text-xs font-bold text-mist-500">:</span>
                        <div class="rounded-xl border border-rose-500/15 bg-rose-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-rose-300/70">Dire</p>
                            <p class="text-xl font-black text-rose-300">{{ $match['dire_score'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($teams as $teamKey => $team)
            <section class="card mt-4 overflow-hidden" aria-label="Состав {{ $team['label'] }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-4 sm:px-5">
                    <div class="flex items-center gap-3">
                        <span @class([
                            'grid h-9 w-9 place-items-center rounded-lg text-[10px] font-black uppercase',
                            'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/25' => $teamKey === 'radiant',
                            'bg-rose-500/15 text-rose-300 ring-1 ring-rose-500/25' => $teamKey === 'dire',
                        ])>
                            {{ mb_substr($team['label'], 0, 1) }}
                        </span>
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.18em] text-mist-500">Команда</p>
                            <h2 class="text-lg font-bold text-white">{{ $team['label'] }}</h2>
                        </div>
                    </div>
                    <span @class([
                        'badge',
                        'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $team['won'],
                        'border-rose-500/25 bg-rose-500/10 text-rose-300' => ! $team['won'],
                    ])>
                        {{ $team['won'] ? 'Победа' : 'Поражение' }} · {{ $team['score'] }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-5">Герой</th>
                                <th class="px-4 py-3 font-semibold">K / D / A</th>
                                <th class="px-4 py-3 font-semibold">GPM</th>
                                <th class="px-4 py-3 font-semibold">XPM</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-5">Урон</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            @foreach ($team['players'] as $player)
                                @php
                                    $isHighlighted = $highlightPlayerId !== null
                                        && $player['account_id'] !== null
                                        && $player['account_id'] === $highlightPlayerId;
                                @endphp
                                <tr @class([
                                    'transition',
                                    'bg-radar-500/[0.10] ring-1 ring-inset ring-radar-500/35' => $isHighlighted,
                                    'hover:bg-white/[0.03]' => ! $isHighlighted,
                                ])>
                                    <td @class([
                                        'px-4 py-3 sm:px-5',
                                        'border-l-2 border-radar-400' => $isHighlighted,
                                        'border-l-2 border-transparent' => ! $isHighlighted,
                                    ])>
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10">
                                                @if ($player['hero_image'])
                                                    <img src="{{ $player['hero_image'] }}" alt="{{ $player['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                @else
                                                    <span class="grid h-full w-full place-items-center text-xs font-bold text-mist-500">
                                                        {{ mb_strtoupper(mb_substr($player['hero_name'], 0, 1)) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p @class([
                                                    'truncate text-[13px] text-white',
                                                    'font-bold' => $isHighlighted,
                                                    'font-semibold' => ! $isHighlighted,
                                                ])>{{ $player['hero_name'] }}</p>
                                                <p class="mt-0.5 flex items-center gap-1.5 truncate text-[10px] text-mist-500">
                                                    @if ($player['nickname'])
                                                        <span class="truncate">{{ $player['nickname'] }}</span>
                                                    @endif
                                                    @if ($isHighlighted)
                                                        <span class="rounded bg-radar-500/15 px-1.5 py-0.5 font-bold uppercase tracking-wide text-radar-300">Вы</span>
                                                    @endif
                                                    @if ($player['level'] > 0)
                                                        <span>ур. {{ $player['level'] }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums">
                                        <span class="text-emerald-300">{{ $player['kills'] }}</span>
                                        <span class="text-mist-500/60">/</span>
                                        <span class="text-rose-300">{{ $player['deaths'] }}</span>
                                        <span class="text-mist-500/60">/</span>
                                        <span class="text-sky-300">{{ $player['assists'] }}</span>
                                        <span class="ml-1.5 text-[10px] text-mist-500">({{ number_format($player['kda'], 2, '.', '') }})</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($player['gpm'], 0, '.', ' ') }}</td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($player['xpm'], 0, '.', ' ') }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-[13px] tabular-nums text-mist-300 sm:px-5">{{ number_format($player['hero_damage'], 0, '.', ' ') }}</td>
                                </tr>
                            @endforeach

                            @if ($team['players'] === [])
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-mist-400">Нет данных по игрокам.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/[0.06] bg-ink-900/60 px-4 py-3 text-[11px] text-mist-500">
            <span>Данные OpenDota · кэш матча 1 час</span>
            <div class="flex items-center gap-3">
                @if ($highlightPlayerId)
                    <a href="{{ route('dota.player', ['accountId' => $highlightPlayerId]) }}" class="inline-flex items-center gap-1 font-semibold text-radar-300 transition hover:text-radar-200">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Вернуться к профилю
                    </a>
                @endif
                <a href="{{ route('home') }}" class="font-semibold text-mist-400 transition hover:text-white">На главную</a>
            </div>
        </div>
    </div>
@endsection
