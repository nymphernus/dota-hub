@extends('layouts.app')

@section('title', $player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Статистика Dota 2 игрока '.$player['nickname'].': ранг, винрейт, KDA, GPM, XPM, топ героев и последние матчи.')

@php
    $winrate = $player['winrate'];
    $rankTone = match ($player['rank_medal']) {
        'Herald' => 'from-zinc-400 to-zinc-600',
        'Guardian' => 'from-emerald-400 to-emerald-700',
        'Crusader' => 'from-sky-400 to-sky-700',
        'Archon' => 'from-violet-400 to-violet-700',
        'Legend' => 'from-amber-400 to-amber-700',
        'Ancient' => 'from-cyan-400 to-cyan-700',
        'Divine' => 'from-fuchsia-400 to-fuchsia-700',
        'Immortal' => 'from-rose-400 to-rose-700',
        default => 'from-slate-400 to-slate-600',
    };
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-mist-400">Dota 2</span>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="truncate text-slate-300">{{ $player['nickname'] }}</span>
        </nav>

        <section class="card relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-r from-dota-500/25 via-ink-800/40 to-cyan-500/10" aria-hidden="true"></div>
            <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-dota-500/10 blur-3xl" aria-hidden="true"></div>

            <div class="relative px-4 pb-5 pt-16 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-ink-800 shadow-2xl ring-4 ring-ink-900 sm:h-28 sm:w-28">
                            @if ($player['avatar'])
                                <img src="{{ $player['avatar'] }}" alt="Аватар {{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                            @else
                                <span class="grid h-full w-full place-items-center text-3xl font-black text-mist-500">
                                    {{ mb_strtoupper(mb_substr($player['nickname'], 0, 1)) }}
                                </span>
                            @endif
                        </div>

                        <div class="min-w-0 pb-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge border-dota-500/25 bg-dota-500/10 text-dota-300">Dota 2</span>
                                @if ($player['is_private'])
                                    <span class="badge border-amber-500/25 bg-amber-500/10 text-amber-300">Данные скрыты</span>
                                @endif
                                @if ($player['country_code'])
                                    <span class="badge border-white/10 bg-white/5 text-mist-300">{{ mb_strtoupper($player['country_code']) }}</span>
                                @endif
                            </div>

                            <h1 class="mt-2 truncate text-3xl font-black tracking-tight text-white sm:text-4xl">{{ $player['nickname'] }}</h1>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-mist-400">
                                <span class="font-mono text-mist-500">ID {{ $player['account_id'] }}</span>
                                <a href="{{ $player['profile_url'] ?: 'https://steamcommunity.com/profiles/'.$player['steam_id'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 transition hover:text-radar-300">
                                    Steam-профиль
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M10 5H5v14h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </a>
                                <span class="inline-flex items-center gap-1 text-mist-500">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" /></svg>
                                    Обновлено {{ \Carbon\CarbonImmutable::parse($player['cached_at'])->diffForHumans(short: true) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <span class="badge border-white/10 bg-white/5 !px-3 !py-2 text-mist-400" title="Время жизни кэша">
                            <svg class="h-3.5 w-3.5 text-radar-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3" stroke-linecap="round" /><circle cx="12" cy="12" r="4" /></svg>
                            Кэш {{ intdiv($player['cache_ttl'], 60) }} мин
                        </span>

                        <form method="POST" action="{{ route('dota.player.refresh', ['accountId' => $player['account_id']]) }}" x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            <button type="submit" class="btn-ghost !py-2" :disabled="loading">
                                <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M20 11a8 8 0 1 0-2.3 6.3" stroke-linecap="round" />
                                    <path d="M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span x-text="loading ? 'Обновляем…' : 'Обновить профиль'">Обновить профиль</span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-white/[0.06] bg-white/[0.06] sm:grid-cols-4">
                    <div class="bg-ink-900/95 px-4 py-3">
                        <p class="label">Ранг</p>
                        <div class="mt-1.5 flex items-center gap-2">
                            @if ($player['rank_label'])
                                <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br {{ $rankTone }} text-[10px] font-black text-ink-950 shadow-inner">
                                    {{ $player['rank_stars'] ?: '★' }}
                                </span>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $player['rank_label'] }}</p>
                                    @if ($player['rank_stars'])
                                        <p class="text-[10px] tracking-[0.18em] text-amber-300">{{ str_repeat('★', $player['rank_stars']) }}</p>
                                    @else
                                        <p class="text-[10px] text-mist-500">Leaderboard</p>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm font-semibold text-mist-500">Не откалиброван</p>
                            @endif
                        </div>
                    </div>

                    <div class="bg-ink-900/95 px-4 py-3">
                        <p class="label">Матчи</p>
                        <p class="mt-1.5 text-xl font-black text-white">{{ number_format($player['games'], 0, '.', ' ') }}</p>
                        <p class="text-[10px] text-mist-500">{{ number_format($player['wins'], 0, '.', ' ') }} побед · {{ number_format($player['losses'], 0, '.', ' ') }} поражений</p>
                    </div>

                    <div class="bg-ink-900/95 px-4 py-3">
                        <p class="label">Винрейт</p>
                        <p class="mt-1.5 text-xl font-black {{ ($winrate ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">
                            {{ $winrate !== null ? number_format($winrate, 1, '.', '') : '—' }}@if ($winrate !== null)<span class="text-sm font-bold text-mist-500">%</span>@endif
                        </p>
                        <p class="text-[10px] text-mist-500">За всё время</p>
                    </div>

                    <div class="bg-ink-900/95 px-4 py-3">
                        <p class="label">MMR (оценка)</p>
                        <p class="mt-1.5 text-xl font-black text-white">{{ $player['mmr_estimate'] ? number_format($player['mmr_estimate'], 0, '.', ' ') : '—' }}</p>
                        <p class="text-[10px] text-mist-500">OpenDota estimate</p>
                    </div>
                </div>
            </div>
        </section>

        @if ($player['is_private'])
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/[0.08] p-4 text-sm text-amber-100">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="5" y="10" width="14" height="10" rx="2" />
                    <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round" />
                </svg>
                <div>
                    <p class="font-semibold">Профиль Steam закрыт</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-100/70">
                        Игрок скрыл данные профиля. OpenDota может показывать только матчи, попавшие в открытый индекс.
                        Метрики ниже могут быть неполными.
                    </p>
                </div>
            </div>
        @endif

        <div
            class="mt-6"
            x-data="{
                tab: window.location.hash === '#matches' ? 'matches' : 'overview',
                setTab(value) {
                    this.tab = value;
                    history.replaceState(null, '', value === 'matches' ? '#matches' : window.location.pathname);
                },
                move(direction, element) {
                    this.setTab(direction > 0 ? 'matches' : 'overview');
                    const tabs = element.closest('[role=tablist]').querySelectorAll('[role=tab]');
                    tabs[direction > 0 ? 1 : 0].focus();
                }
            }"
            @hashchange.window="tab = window.location.hash === '#matches' ? 'matches' : 'overview'"
        >
            <div class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-white/[0.06] bg-ink-900/60 p-1.5" role="tablist" aria-label="Разделы профиля" @keydown.arrow-right.prevent="move(1, $event.target)" @keydown.arrow-left.prevent="move(-1, $event.target)">
                <button type="button" id="tab-overview" aria-controls="panel-overview" @click="setTab('overview')" :class="{ 'tab-button-active': tab === 'overview' }" class="tab-button" role="tab" :aria-selected="(tab === 'overview').toString()" :tabindex="tab === 'overview' ? 0 : -1">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5" /><rect x="13" y="4" width="7" height="7" rx="1.5" /><rect x="4" y="13" width="7" height="7" rx="1.5" /><rect x="13" y="13" width="7" height="7" rx="1.5" /></svg>
                    Обзор
                </button>
                <button type="button" id="tab-matches" aria-controls="panel-matches" @click="setTab('matches')" :class="{ 'tab-button-active': tab === 'matches' }" class="tab-button" role="tab" :aria-selected="(tab === 'matches').toString()" :tabindex="tab === 'matches' ? 0 : -1">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 7h14M5 12h14M5 17h9" stroke-linecap="round" /></svg>
                    Последние матчи
                    <span class="rounded-md bg-white/[0.06] px-1.5 py-0.5 text-[10px] font-bold text-mist-400">{{ count($player['matches']) }}</span>
                </button>
            </div>

            <div class="mt-4">
                <div id="panel-overview" aria-labelledby="tab-overview" x-show="tab === 'overview'" x-transition.opacity.duration.200ms role="tabpanel" tabindex="0" aria-label="Обзор">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <x-stat label="Средний KDA" :value="number_format($player['averages']['kda'] ?? 0, 2, '.', '')" hint="K + A / D за все матчи" />
                        <x-stat label="GPM" :value="number_format($player['averages']['gpm'] ?? 0, 0, '.', ' ')" hint="Золото в минуту" />
                        <x-stat label="XPM" :value="number_format($player['averages']['xpm'] ?? 0, 0, '.', ' ')" hint="Опыт в минуту" />
                        <x-stat label="K / D / A" :value="number_format($player['averages']['kills'] ?? 0, 1).' / '.number_format($player['averages']['deaths'] ?? 0, 1).' / '.number_format($player['averages']['assists'] ?? 0, 1)" hint="Средние значения" />
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div class="card p-4 sm:p-5 lg:col-span-2">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="label">Любимые герои</p>
                                    <h2 class="mt-1 text-lg font-bold text-white">Топ-5 по количеству игр</h2>
                                </div>
                                <span class="text-[11px] text-mist-500">Винрейт по герою</span>
                            </div>

                            @if ($player['heroes'] === [])
                                <div class="mt-5 rounded-xl border border-dashed border-white/10 p-8 text-center">
                                    <p class="text-sm text-mist-400">OpenDota ещё не проиндексировала матчи этого игрока.</p>
                                </div>
                            @else
                                <div class="mt-4 space-y-2">
                                    @foreach ($player['heroes'] as $index => $hero)
                                        <div class="group flex items-center gap-3 rounded-xl border border-transparent bg-white/[0.02] p-2 transition hover:border-white/[0.06] hover:bg-white/[0.04]">
                                            <span class="w-4 shrink-0 text-center text-[10px] font-bold text-mist-500">{{ $index + 1 }}</span>
                                            <div class="h-10 w-16 shrink-0 overflow-hidden rounded-lg bg-ink-800 ring-1 ring-white/10">
                                                @if ($hero['image'])
                                                    <img src="{{ $hero['image'] }}" alt="{{ $hero['name'] }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="truncate text-sm font-semibold text-white">{{ $hero['name'] }}</p>
                                                    <span class="shrink-0 text-xs font-bold {{ ($hero['winrate'] ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">
                                                        {{ $hero['winrate'] !== null ? number_format($hero['winrate'], 1, '.', '') : '—' }}%
                                                    </span>
                                                </div>
                                                <div class="mt-1.5 flex items-center gap-3">
                                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/[0.06]">
                                                        <div class="h-full rounded-full {{ ($hero['winrate'] ?? 0) >= 50 ? 'bg-emerald-400' : 'bg-rose-400' }}" style="width: {{ min(100, max(2, $hero['winrate'] ?? 0)) }}%"></div>
                                                    </div>
                                                    <span class="w-16 shrink-0 text-right text-[10px] text-mist-500">{{ $hero['games'] }} игр</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="card flex flex-col items-center justify-center p-5 sm:p-6">
                            <p class="label self-start">Соотношение побед</p>

                            <div class="relative mt-5 grid h-40 w-40 place-items-center rounded-full" style="background: conic-gradient(#34d399 {{ $winrate ?? 0 }}%, rgba(255,255,255,0.06) 0);">
                                <div class="grid h-[8.5rem] w-[8.5rem] place-items-center rounded-full bg-ink-900 text-center shadow-inner">
                                    <div>
                                        <p class="text-3xl font-black text-white">{{ $winrate !== null ? number_format($winrate, 1, '.', '') : '—' }}@if ($winrate !== null)<span class="text-base text-mist-500">%</span>@endif</p>
                                        <p class="mt-1 text-[10px] uppercase tracking-[0.18em] text-mist-500">Winrate</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 grid w-full grid-cols-2 gap-2">
                                <div class="rounded-xl border border-emerald-500/15 bg-emerald-500/[0.08] p-3 text-center">
                                    <p class="text-lg font-bold text-emerald-300">{{ number_format($player['wins'], 0, '.', ' ') }}</p>
                                    <p class="text-[10px] uppercase tracking-wider text-emerald-300/60">Победы</p>
                                </div>
                                <div class="rounded-xl border border-rose-500/15 bg-rose-500/[0.08] p-3 text-center">
                                    <p class="text-lg font-bold text-rose-300">{{ number_format($player['losses'], 0, '.', ' ') }}</p>
                                    <p class="text-[10px] uppercase tracking-wider text-rose-300/60">Поражения</p>
                                </div>
                            </div>

                            <div class="mt-2 w-full rounded-xl border border-white/[0.06] bg-white/[0.03] p-3 text-center">
                                <p class="text-lg font-bold text-white">{{ number_format($player['games'], 0, '.', ' ') }}</p>
                                <p class="text-[10px] uppercase tracking-wider text-mist-500">Всего матчей</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="panel-matches" aria-labelledby="tab-matches" x-show="tab === 'matches'" x-cloak x-transition.opacity.duration.200ms role="tabpanel" tabindex="0" aria-label="Последние матчи">
                    <div class="card overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-4 sm:px-5">
                            <div>
                                <p class="label">Match history</p>
                                <h2 class="mt-1 text-lg font-bold text-white">Последние матчи</h2>
                            </div>
                            <div class="flex items-center gap-3 text-[11px]">
                                <span class="inline-flex items-center gap-1.5 text-emerald-300"><span class="h-2 w-2 rounded-full bg-emerald-400"></span> Победа</span>
                                <span class="inline-flex items-center gap-1.5 text-rose-300"><span class="h-2 w-2 rounded-full bg-rose-400"></span> Поражение</span>
                            </div>
                        </div>

                        @if ($player['matches'] === [])
                            <div class="p-10 text-center">
                                <p class="text-sm text-mist-400">Матчи не найдены. OpenDota индексирует только открытые профили.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-sm">
                                    <thead class="table-head">
                                        <tr>
                                            <th class="px-4 py-3 font-semibold sm:px-5">Герой</th>
                                            <th class="px-4 py-3 font-semibold">Результат</th>
                                            <th class="px-4 py-3 font-semibold">K / D / A</th>
                                            <th class="px-4 py-3 font-semibold">GPM / XPM</th>
                                            <th class="px-4 py-3 font-semibold">Длительность</th>
                                            <th class="px-4 py-3 text-right font-semibold sm:px-5">Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/[0.04]">
                                        @foreach ($player['matches'] as $match)
                                            <tr @class([
                                                'transition',
                                                'bg-emerald-500/[0.055] hover:bg-emerald-500/[0.10]' => $match['win'],
                                                'bg-rose-500/[0.055] hover:bg-rose-500/[0.10]' => ! $match['win'],
                                            ])>
                                                <td @class([
                                                    'px-4 py-2.5 sm:px-5',
                                                    'border-l-2 border-emerald-400/70' => $match['win'],
                                                    'border-l-2 border-rose-400/70' => ! $match['win'],
                                                ])>
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10">
                                                            @if ($match['hero_image'])
                                                                <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                            @endif
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="truncate text-[13px] font-semibold text-white">{{ $match['hero_name'] }}</p>
                                                            @if ($match['match_id'])
                                                                <p class="font-mono text-[10px] text-mist-500">#{{ $match['match_id'] }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <span @class([
                                                        'badge !px-2.5 !py-1 !text-[10px]',
                                                        'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $match['win'],
                                                        'border-rose-500/25 bg-rose-500/10 text-rose-300' => ! $match['win'],
                                                    ])>
                                                        {{ $match['win'] ? 'WIN' : 'LOSS' }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[13px] tabular-nums">
                                                    <span class="text-emerald-300">{{ $match['kills'] }}</span>
                                                    <span class="text-mist-500/60">/</span>
                                                    <span class="text-rose-300">{{ $match['deaths'] }}</span>
                                                    <span class="text-mist-500/60">/</span>
                                                    <span class="text-sky-300">{{ $match['assists'] }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[13px] tabular-nums text-mist-300">
                                                    {{ $match['gpm'] }} <span class="text-mist-500/60">/</span> {{ $match['xpm'] }}
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[13px] tabular-nums text-mist-300">{{ $match['duration_label'] }}</td>
                                                <td class="px-4 py-2.5 text-right text-[11px] text-mist-400 sm:px-5">
                                                    @if ($match['started_at'])
                                                        <time datetime="{{ $match['started_at'] }}" title="{{ \Carbon\CarbonImmutable::parse($match['started_at'])->format('d.m.Y H:i') }}">
                                                            {{ \Carbon\CarbonImmutable::parse($match['started_at'])->diffForHumans(short: true) }}
                                                        </time>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
