@extends('layouts.app')

@section('title', $player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Статистика Dota 2 игрока '.$player['nickname'].': ранг, винрейт, KDA, GPM, XPM, топ героев и последние матчи.')

@php
    $topHeroes = $player['top_heroes'] ?? array_slice($player['heroes'], 0, 5);

    $chartMatches = collect($recentMatches)
        ->reverse()
        ->values()
        ->map(fn (array $match) => [
            'label' => $match['started_at']
                ? \Carbon\CarbonImmutable::parse($match['started_at'])->format('d.m')
                : '—',
            'win' => (bool) $match['win'],
            'hero' => $match['hero_name'] ?? 'Неизвестный герой',
        ])
        ->all();

    $chartData = [
        'winrate' => $winrate,
        'wins' => $player['wins'],
        'losses' => $player['losses'],
        'matches' => $chartMatches,
    ];

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
                tabs: ['overview', 'heroes', 'matches'],
                tab: window.location.hash === '#heroes'
                    ? 'heroes'
                    : (window.location.hash === '#matches' ? 'matches' : 'overview'),
                setTab(value) {
                    this.tab = value;
                    history.replaceState(null, '', value === 'overview' ? window.location.pathname : '#' + value);
                },
                syncTab() {
                    this.tab = this.tabs.includes(window.location.hash.slice(1)) ? window.location.hash.slice(1) : 'overview';
                },
                move(direction, element) {
                    const index = this.tabs.indexOf(this.tab);
                    const next = (index + direction + this.tabs.length) % this.tabs.length;
                    this.setTab(this.tabs[next]);
                    element.closest('[role=tablist]').querySelectorAll('[role=tab]')[next].focus();
                }
            }"
            @hashchange.window="syncTab()"
        >
            <div class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-white/[0.06] bg-ink-900/60 p-1.5" role="tablist" aria-label="Разделы профиля" @keydown.arrow-right.prevent="move(1, $event.target)" @keydown.arrow-left.prevent="move(-1, $event.target)">
                <button type="button" id="tab-overview" aria-controls="panel-overview" @click="setTab('overview')" :class="{ 'tab-button-active': tab === 'overview' }" class="tab-button" role="tab" :aria-selected="(tab === 'overview').toString()" :tabindex="tab === 'overview' ? 0 : -1">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5" /><rect x="13" y="4" width="7" height="7" rx="1.5" /><rect x="4" y="13" width="7" height="7" rx="1.5" /><rect x="13" y="13" width="7" height="7" rx="1.5" /></svg>
                    Обзор
                </button>
                <button type="button" id="tab-heroes" aria-controls="panel-heroes" @click="setTab('heroes')" :class="{ 'tab-button-active': tab === 'heroes' }" class="tab-button" role="tab" :aria-selected="(tab === 'heroes').toString()" :tabindex="tab === 'heroes' ? 0 : -1">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3z" stroke-linejoin="round" /></svg>
                    Герои
                    <span class="rounded-md bg-white/[0.06] px-1.5 py-0.5 text-[10px] font-bold text-mist-400">{{ count($player['heroes']) }}</span>
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

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="card p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="label">Винрейт</p>
                                    <h2 class="mt-1 text-lg font-bold text-white">Общий винрейт</h2>
                                </div>
                                <span class="badge border-emerald-500/20 bg-emerald-500/10 text-emerald-300">За всё время</span>
                            </div>

                            <div class="relative mx-auto mt-4 h-56 w-full max-w-64">
                                <canvas id="winrateChart" role="img" aria-label="Круговая диаграмма общего винрейта"></canvas>
                                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-3xl font-black text-white">
                                        {{ $winrate !== null ? number_format($winrate, 1, '.', '') : '—' }}@if ($winrate !== null)<span class="text-base text-mist-500">%</span>@endif
                                    </span>
                                    <span class="mt-1 text-[10px] uppercase tracking-[0.18em] text-mist-500">Winrate</span>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div class="rounded-xl border border-emerald-500/15 bg-emerald-500/[0.08] p-3 text-center">
                                    <p class="text-lg font-bold text-emerald-300">{{ number_format($player['wins'], 0, '.', ' ') }}</p>
                                    <p class="text-[9px] uppercase tracking-wider text-emerald-300/60">Победы</p>
                                </div>
                                <div class="rounded-xl border border-rose-500/15 bg-rose-500/[0.08] p-3 text-center">
                                    <p class="text-lg font-bold text-rose-300">{{ number_format($player['losses'], 0, '.', ' ') }}</p>
                                    <p class="text-[9px] uppercase tracking-wider text-rose-300/60">Поражения</p>
                                </div>
                                <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-3 text-center">
                                    <p class="text-lg font-bold text-white">{{ number_format($player['games'], 0, '.', ' ') }}</p>
                                    <p class="text-[9px] uppercase tracking-wider text-mist-500">Всего</p>
                                </div>
                            </div>
                        </div>

                        <div class="card p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="label">Форма</p>
                                    <h2 class="mt-1 text-lg font-bold text-white">Последние матчи</h2>
                                </div>
                                <div class="flex items-center gap-3 text-[10px] text-mist-500">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-sm bg-emerald-400"></span>
                                        Победа
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-sm bg-rose-400"></span>
                                        Поражение
                                    </span>
                                </div>
                            </div>

                            <div class="relative mt-4 h-56">
                                <canvas id="recentMatchesChart" role="img" aria-label="Столбчатая диаграмма результатов последних матчей"></canvas>
                                @if ($chartMatches === [])
                                    <p class="absolute inset-0 grid place-items-center text-sm text-mist-500">
                                        Для графика пока нет матчей.
                                    </p>
                                @endif
                            </div>

                            <p class="mt-3 text-[11px] leading-relaxed text-mist-500">
                                Слева — более старые матчи, справа — последние. Наведите на столбец, чтобы увидеть героя и дату.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="card p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="label">Любимые герои</p>
                                    <h2 class="mt-1 text-lg font-bold text-white">Топ-5 по количеству игр</h2>
                                </div>
                                <span class="text-[11px] text-mist-500">Винрейт по герою</span>
                            </div>

                            @if ($topHeroes === [])
                                <div class="mt-5 rounded-xl border border-dashed border-white/10 p-8 text-center">
                                    <p class="text-sm text-mist-400">OpenDota ещё не проиндексировала матчи этого игрока.</p>
                                </div>
                            @else
                                <div class="mt-4 space-y-2">
                                    @foreach ($topHeroes as $index => $hero)
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
                    </div>
                </div>

                <div id="panel-heroes" aria-labelledby="tab-heroes" x-show="tab === 'heroes'" x-cloak x-transition.opacity.duration.200ms role="tabpanel" tabindex="0" aria-label="Герои">
                    @if ($player['heroes'] === [])
                        <div class="card p-10 text-center">
                            <p class="text-sm text-mist-400">OpenDota ещё не проиндексировала героев этого игрока.</p>
                        </div>
                    @else
                        <div class="card overflow-hidden" x-data="{
                            search: '',
                            sortKey: 'games',
                            sortDir: 'desc',
                            heroes: @js($player['heroes']),
                            get filteredHeroes() {
                                const query = this.search.trim().toLowerCase();
                                const direction = this.sortDir === 'asc' ? 1 : -1;

                                return this.heroes
                                    .filter((hero) => {
                                        const name = (hero.localized_name || hero.name || '').toLowerCase();
                                        return query === '' || name.includes(query);
                                    })
                                    .slice()
                                    .sort((left, right) => {
                                        const leftValue = left[this.sortKey];
                                        const rightValue = right[this.sortKey];

                                        if (leftValue == null) return 1;
                                        if (rightValue == null) return -1;

                                        return (leftValue - rightValue) * direction;
                                    });
                            },
                            sortBy(key) {
                                if (this.sortKey === key) {
                                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                                } else {
                                    this.sortKey = key;
                                    this.sortDir = 'desc';
                                }
                            },
                            indicator(key) {
                                if (this.sortKey !== key) return '↕';
                                return this.sortDir === 'asc' ? '▲' : '▼';
                            }
                        }">
                            <div class="flex flex-col gap-3 border-b border-white/[0.06] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div>
                                    <p class="label">Hero pool</p>
                                    <h2 class="mt-1 text-lg font-bold text-white">Герои</h2>
                                    <p class="mt-1 text-[11px] text-mist-500">KDA рассчитан по последним 20 матчам.</p>
                                </div>

                                <div class="relative w-full sm:w-72">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <circle cx="11" cy="11" r="6.5" />
                                        <path d="M16 16l4.5 4.5" stroke-linecap="round" />
                                    </svg>
                                    <input
                                        type="search"
                                        x-model="search"
                                        class="input !py-2.5 !pl-10"
                                        placeholder="Поиск героя..."
                                        aria-label="Поиск героя по имени"
                                        autocomplete="off"
                                    >
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] text-sm">
                                    <thead class="table-head">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 font-semibold sm:px-5">Герой</th>
                                            <th scope="col" class="px-4 py-3 font-semibold" :aria-sort="sortKey === 'games' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                                <button type="button" @click="sortBy('games')" class="inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-white">
                                                    Матчи
                                                    <span class="text-[10px]" :class="sortKey === 'games' ? 'text-radar-300' : 'text-mist-500'" x-text="indicator('games')"></span>
                                                </button>
                                            </th>
                                            <th scope="col" class="px-4 py-3 font-semibold" :aria-sort="sortKey === 'win_rate' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                                <button type="button" @click="sortBy('win_rate')" class="inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-white">
                                                    Винрейт
                                                    <span class="text-[10px]" :class="sortKey === 'win_rate' ? 'text-radar-300' : 'text-mist-500'" x-text="indicator('win_rate')"></span>
                                                </button>
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-right font-semibold sm:px-5" :aria-sort="sortKey === 'kda' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                                <button type="button" @click="sortBy('kda')" class="ml-auto inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-white">
                                                    KDA
                                                    <span class="text-[10px]" :class="sortKey === 'kda' ? 'text-radar-300' : 'text-mist-500'" x-text="indicator('kda')"></span>
                                                </button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/[0.04]">
                                        <template x-for="hero in filteredHeroes" :key="hero.hero_id">
                                            <tr class="transition hover:bg-white/[0.03]">
                                                <td class="px-4 py-2.5 sm:px-5">
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10">
                                                            <template x-if="hero.image">
                                                                <img :src="hero.image" :alt="hero.localized_name" class="h-full w-full object-cover" loading="lazy">
                                                            </template>
                                                            <template x-if="!hero.image">
                                                                <span class="grid h-full w-full place-items-center text-xs font-bold text-mist-500" x-text="hero.localized_name.charAt(0)"></span>
                                                            </template>
                                                        </div>
                                                        <p class="truncate text-[13px] font-semibold text-white" x-text="hero.localized_name"></p>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[13px] tabular-nums text-mist-300" x-text="hero.games"></td>
                                                <td class="px-4 py-2.5">
                                                    <div class="flex items-center gap-2.5">
                                                        <span
                                                            class="w-12 text-right font-mono text-[13px] font-semibold tabular-nums"
                                                            :class="hero.win_rate == null ? 'text-mist-500' : (hero.win_rate >= 50 ? 'text-emerald-300' : 'text-rose-300')"
                                                            x-text="hero.win_rate == null ? '—' : hero.win_rate.toFixed(1) + '%'"
                                                        ></span>
                                                        <div class="h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-white/[0.06]">
                                                            <div
                                                                class="h-full rounded-full"
                                                                :class="hero.win_rate == null || hero.win_rate >= 50 ? 'bg-emerald-400' : 'bg-rose-400'"
                                                                :style="'width: ' + Math.min(100, Math.max(2, hero.win_rate || 0)) + '%'"
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[13px] tabular-nums text-mist-300 sm:px-5" x-text="hero.kda == null ? '—' : hero.kda.toFixed(2)"></td>
                                            </tr>
                                        </template>
                                        <tr x-show="filteredHeroes.length === 0">
                                            <td colspan="4" class="px-4 py-10 text-center text-sm text-mist-400">Герои с таким именем не найдены.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-white/[0.06] px-4 py-3 text-[11px] text-mist-500 sm:px-5">
                                <span x-text="'Показано ' + filteredHeroes.length + ' из ' + heroes.length + ' героев'"></span>
                                <span>Сортировка по клику на заголовок</span>
                            </div>
                        </div>
                    @endif
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
                                            @php
                                                $matchUrl = $match['match_id']
                                                    ? route('dota.match', ['matchId' => $match['match_id'], 'player_id' => $player['account_id']])
                                                    : null;
                                            @endphp
                                            <tr @class([
                                                'cursor-pointer transition hover:ring-1 hover:ring-inset hover:ring-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-radar-400',
                                                'bg-emerald-500/[0.055] hover:bg-emerald-500/[0.10]' => $match['win'],
                                                'bg-rose-500/[0.055] hover:bg-rose-500/[0.10]' => ! $match['win'],
                                            ])
                                                @if ($matchUrl)
                                                    data-href="{{ $matchUrl }}"
                                                    onclick="window.location.href = this.dataset.href"
                                                    onkeydown="if (event.key === 'Enter') { window.location.href = this.dataset.href }"
                                                    tabindex="0"
                                                    aria-label="Открыть матч {{ $match['match_id'] }}"
                                                @endif
                                            >
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
                                                            <p class="truncate text-[13px] font-semibold text-white">
                                                                @if ($matchUrl)
                                                                    <a href="{{ $matchUrl }}" class="transition hover:text-radar-200 hover:underline" onclick="event.stopPropagation()">{{ $match['hero_name'] }}</a>
                                                                @else
                                                                    {{ $match['hero_name'] }}
                                                                @endif
                                                            </p>
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

@push('scripts')
    <script type="application/json" id="stats-radar-chart-data">@json($chartData)</script>
    @vite('resources/js/charts.js')
@endpush
