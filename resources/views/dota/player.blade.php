@extends('layouts.app')

@section('title', $player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Статистика Dota 2 игрока '.$player['nickname'].': ранг, винрейт, KDA, GPM, XPM, топ героев и последние матчи.')

@php
    $topHeroes = $player['top_heroes'] ?? array_slice($player['heroes'], 0, 5);
    $heroUsageTotal = array_sum(array_column($heroUsage, 'games'));
    $activityTotal = array_sum(array_column($activityHeatmap, 'count'));
    $nameHistory = $player['name_history'] ?? [];
    $steamCreatedAt = $steamProfile['account_created_at'] ?? null;
    $winShare = $player['games'] > 0 ? round($player['wins'] / $player['games'] * 100, 1) : 0;
    $lossShare = $player['games'] > 0 ? round($player['losses'] / $player['games'] * 100, 1) : 0;
    $heatmapTones = [
        'empty' => 'bg-white/[0.04] ring-1 ring-inset ring-white/[0.03] text-transparent',
        'win' => 'bg-emerald-500 ring-1 ring-inset ring-emerald-300/40 text-emerald-950',
        'loss' => 'bg-rose-500 ring-1 ring-inset ring-rose-300/40 text-rose-950',
        'mixed' => 'bg-amber-500 ring-1 ring-inset ring-amber-300/40 text-amber-950',
    ];
    $chartData = [
        'heroUsage' => $heroUsage,
    ];
    $rankTier = (int) ($player['rank_tier'] ?? 0);
    $rankIconBase = asset('images/dota/ranks');
    $rankIcon = $rankIconBase.'/rank_icon_0.png';
    $rankStarIcon = null;

    if ($rankTier > 0) {
        $rankTierBand = intdiv($rankTier, 10);
        $rankIcon = $rankIconBase.'/rank_icon_'.$rankTierBand.'.png';

        if ($rankTierBand >= 1 && $rankTierBand <= 7) {
            $rankStars = max(1, min(7, $rankTier % 10));
            $rankStarIcon = $rankIconBase.'/rank_star_'.$rankStars.'.png';
        }
    }
    $steamAvailable = (bool) ($steamProfile['available'] ?? false);
    $steamPublic = $steamProfile['is_public'] ?? null;
    $dotaPublic = (bool) ($player['has_public_matches'] ?? false);
    $steamTone = match (true) {
        $steamPublic === true => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
        $steamPublic === false => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
        default => 'border-white/10 bg-white/5 text-mist-300',
    };
    $dotaTone = $dotaPublic
        ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300'
        : 'border-amber-500/25 bg-amber-500/10 text-amber-300';
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
            <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-r from-dota-500/20 via-ink-800/40 to-cyan-500/10" aria-hidden="true"></div>
            <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-dota-500/10 blur-3xl" aria-hidden="true"></div>

            <div class="relative px-4 pb-5 pt-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-ink-800 shadow-2xl ring-4 ring-ink-900 sm:h-24 sm:w-24">
                            @if ($player['avatar'])
                                <img src="{{ $player['avatar'] }}" alt="Аватар {{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                            @else
                                <span class="grid h-full w-full place-items-center text-3xl font-black text-mist-500">{{ mb_strtoupper(mb_substr($player['nickname'], 0, 1)) }}</span>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-2.5 py-1.5" title="Ранг: {{ $player['rank_label'] ?: 'Не откалиброван' }}">
                                    <span class="relative inline-flex h-9 w-9 items-center justify-center">
                                        <img src="{{ $rankIcon }}" alt="Иконка ранга {{ $player['rank_label'] ?: 'без ранга' }}" class="h-9 w-9 object-contain">
                                        @if ($rankStarIcon)
                                            <img src="{{ $rankStarIcon }}" alt="" class="absolute -bottom-1 left-1/2 h-3 w-8 -translate-x-1/2 object-contain">
                                        @endif
                                    </span>
                                    <span class="text-xs font-bold text-white">{{ $player['rank_label'] ?: 'Без ранга' }}</span>
                                </span>
                                @if ($player['mmr_estimate'] !== null)
                                    <span class="badge border-amber-500/25 bg-amber-500/10 text-amber-200" title="{{ $player['mmr_is_estimated'] ? 'Оценка по последним рейтинговым матчам' : 'Оценка OpenDota' }}">
                                        {{ number_format($player['mmr_estimate'], 0, '.', ' ') }} MMR
                                    </span>
                                @endif
                                @if ($player['is_offline_cache'] ?? false)
                                    <span class="badge border-sky-500/25 bg-sky-500/10 text-sky-300">Данные из кэша (Офлайн)</span>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-2" x-data="{ open: false }">
                                <h1 class="min-w-0 truncate text-3xl font-black tracking-tight text-white sm:text-4xl">{{ $player['nickname'] }}</h1>
                                @if ($nameHistory !== [])
                                    <div class="relative">
                                        <button type="button" @click="open = !open" class="grid h-7 w-7 place-items-center rounded-lg border border-white/10 bg-white/[0.04] text-mist-400 transition hover:text-white" :aria-expanded="open.toString()" aria-label="История никнеймов">
                                            <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        </button>
                                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click.outside="open = false" class="absolute left-0 top-9 z-20 w-56 rounded-xl border border-white/10 bg-ink-950/95 p-2 shadow-2xl backdrop-blur">
                                            <p class="px-2 py-1 text-[10px] uppercase tracking-[0.16em] text-mist-500">История никнеймов</p>
                                            <div class="mt-1 max-h-56 space-y-1 overflow-y-auto">
                                                @foreach ($nameHistory as $entry)
                                                    <div class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-[11px] hover:bg-white/[0.04]">
                                                        <span class="truncate font-semibold text-mist-200">{{ $entry['name'] }}</span>
                                                        <span class="shrink-0 text-[9px] text-mist-500">{{ $entry['changed_at'] ? \Carbon\CarbonImmutable::parse($entry['changed_at'])->format('d.m.y') : '' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-mist-500">
                                <span class="font-mono">Dota ID {{ $player['account_id'] }}</span>
                                <span class="font-mono">SteamID64 {{ $steamProfile['steam_id'] ?? $player['steam_id'] }}</span>
                                @if ($steamCreatedAt)
                                    <span>Регистрация {{ \Carbon\CarbonImmutable::parse($steamCreatedAt)->format('d.m.Y') }}</span>
                                @endif
                                <a href="{{ $player['profile_url'] ?: 'https://steamcommunity.com/profiles/'.$player['steam_id'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 transition hover:text-radar-300">
                                    Steam
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M10 5H5v14h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </a>
                                <span>Обновлено {{ \Carbon\CarbonImmutable::parse($player['cached_at'])->diffForHumans(short: true) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('dota.player.refresh', ['accountId' => $player['account_id']]) }}" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="loading = false">
                            @csrf
                            <button type="submit" class="btn-ghost !py-2" :disabled="loading">
                                <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M20 11a8 8 0 1 0-2.3 6.3" stroke-linecap="round" />
                                    <path d="M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span x-text="loading ? 'Обновляем…' : 'Обновить'">Обновить</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-4 flex flex-wrap items-center gap-2 rounded-2xl border border-white/[0.06] bg-ink-900/60 px-4 py-3 text-[11px]">
            <span class="uppercase tracking-[0.16em] text-mist-500">Приватность</span>
            <span @class(['badge', $steamTone])>Steam: {{ $steamProfile['visibility'] ?? 'Неизвестно' }}</span>
            <span @class(['badge', $dotaTone])>Dota: {{ $dotaPublic ? 'Публичные матчи доступны' : 'Матчи скрыты или отсутствуют' }}</span>
            @if (! $steamAvailable)
                <span class="text-amber-300/80">{{ $steamProfile['error'] ?? 'Steam API недоступна' }}</span>
            @endif
            @if ($player['is_private'])
                <span class="badge border-amber-500/25 bg-amber-500/10 text-amber-300">Данные скрыты</span>
            @endif
        </div>

        @if ($player['games'] === 0)
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-sky-500/25 bg-sky-500/[0.08] p-4 text-sm text-sky-100">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5M12 16.2v.3" stroke-linecap="round" /></svg>
                <div>
                    <p class="font-semibold">Нет публичных матчей</p>
                    <p class="mt-1 text-xs leading-relaxed text-sky-100/70">Убедитесь, что в настройках Dota 2 включена опция «Expose Public Match Data».</p>
                </div>
            </div>
        @endif

        <div
            class="mt-5"
            x-data="{
                tabs: ['overview', 'matches', 'heroes', 'peers'],
                tab: ['overview', 'heroes', 'peers'].includes(window.location.hash.slice(1))
                    ? window.location.hash.slice(1)
                    : 'overview',
                matchesUrl: @js(route('dota.player.matches', ['accountId' => $player['account_id']])),
                setTab(value) {
                    if (value === 'matches') {
                        window.location.href = this.matchesUrl;
                        return;
                    }
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
                    Обзор
                </button>
                <a id="tab-matches" href="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" @click.prevent="setTab('matches')" class="tab-button" role="tab" aria-selected="false" tabindex="0">
                    Матчи
                </a>
                <button type="button" id="tab-heroes" aria-controls="panel-heroes" @click="setTab('heroes')" :class="{ 'tab-button-active': tab === 'heroes' }" class="tab-button" role="tab" :aria-selected="(tab === 'heroes').toString()" :tabindex="tab === 'heroes' ? 0 : -1">
                    Герои
                    <span class="rounded-md bg-white/[0.06] px-1.5 py-0.5 text-[10px] font-bold text-mist-400">{{ count($player['heroes']) }}</span>
                </button>
                <button type="button" id="tab-peers" aria-controls="panel-peers" @click="setTab('peers')" :class="{ 'tab-button-active': tab === 'peers' }" class="tab-button" role="tab" :aria-selected="(tab === 'peers').toString()" :tabindex="tab === 'peers' ? 0 : -1">
                    Товарищи
                </button>
            </div>

            <div class="mt-4">
                <div id="panel-overview" aria-labelledby="tab-overview" x-show="tab === 'overview'" x-transition.opacity.duration.200ms role="tabpanel" tabindex="0" aria-label="Обзор">
                    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,7fr)_minmax(280px,3fr)]">
                        <div class="min-w-0 space-y-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <section class="card p-4 sm:p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="label">Матчи</p>
                                            <p class="mt-1 text-3xl font-black tracking-tight text-white">{{ number_format($player['games'], 0, '.', ' ') }}</p>
                                        </div>
                                        <span class="badge border-amber-500/25 bg-amber-500/10 text-amber-200">Всего игр</span>
                                    </div>
                                    <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-white/[0.05]">
                                        <div class="h-full bg-amber-400" style="width: {{ $winShare }}%"></div>
                                        <div class="h-full bg-rose-500" style="width: {{ $lossShare }}%"></div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[11px]">
                                        <span class="font-semibold text-amber-300">{{ number_format($player['wins'], 0, '.', ' ') }} побед</span>
                                        <span class="font-semibold text-rose-300">{{ number_format($player['losses'], 0, '.', ' ') }} поражений</span>
                                    </div>
                                </section>

                                <section class="card p-4 sm:p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="label">Винрейт</p>
                                            <p class="mt-1 text-3xl font-black tracking-tight {{ ($winrate ?? 0) >= 50 ? 'text-amber-300' : 'text-rose-300' }}">{{ $winrate !== null ? number_format($winrate, 1, '.', '').'%' : '—' }}</p>
                                        </div>
                                        <span class="badge border-white/10 bg-white/5 text-mist-300">За всё время</span>
                                    </div>
                                    <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white/[0.05]">
                                        <div class="h-full rounded-full {{ ($winrate ?? 0) >= 50 ? 'bg-gradient-to-r from-amber-500 to-amber-300' : 'bg-gradient-to-r from-rose-600 to-rose-400' }}" style="width: {{ $winrate ?? 0 }}%"></div>
                                    </div>
                                    <p class="mt-2 text-[11px] text-mist-500">{{ number_format($player['wins'], 0, '.', ' ') }} из {{ number_format($player['games'], 0, '.', ' ') }} игр завершились победой</p>
                                </section>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <x-stat label="Средний KDA" :value="$player['games'] > 0 ? number_format($player['averages']['kda'] ?? 0, 2, '.', '') : '—'" hint="K + A / D" />
                                <x-stat label="MMR (оценка)" :value="$player['mmr_estimate'] !== null ? number_format($player['mmr_estimate'], 0, '.', ' ') : '—'" :hint="$player['mmr_is_estimated'] ? 'Медаль + последние '.$player['mmr_games_considered'].' рейтинговых игр' : 'Данные OpenDota'" />
                            </div>

                            <section class="card overflow-hidden">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-3 sm:px-5">
                                    <div>
                                        <p class="label">Последние игры</p>
                                        <h2 class="mt-1 text-base font-bold text-white">Лента матчей</h2>
                                    </div>
                                </div>

                                @if ($recentMatches === [])
                                    <div class="p-10 text-center text-sm text-mist-400">Матчи не найдены. OpenDota индексирует только открытые профили.</div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-[820px] text-sm">
                                            <thead class="table-head">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-semibold sm:px-4">Герой / режим</th>
                                                    <th class="px-2 py-2.5 text-left font-semibold">K / D / A</th>
                                                    <th class="px-2 py-2.5 text-right font-semibold">Нетворс</th>
                                                    <th class="px-2 py-2.5 text-left font-semibold">GPM / XPM</th>
                                                    <th class="px-2 py-2.5 text-left font-semibold">Предметы</th>
                                                    <th class="px-3 py-2.5 text-right font-semibold sm:px-4">Дата</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-white/[0.04]">
                                                @foreach ($recentMatches as $match)
                                                    @include('dota.partials.match-row', ['match' => $match, 'accountId' => $player['account_id']])
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </section>

                            <section class="card overflow-hidden">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-3 sm:px-5">
                                    <div>
                                        <p class="label">Hero pool</p>
                                        <h2 class="mt-1 text-base font-bold text-white">Самые играемые герои</h2>
                                    </div>
                                    <span class="badge border-white/10 bg-white/5 text-mist-400">За всё время</span>
                                </div>

                                @if ($topHeroes === [])
                                    <div class="p-8 text-center text-sm text-mist-400">OpenDota ещё не проиндексировала матчи этого игрока.</div>
                                @else
                                    <div class="grid gap-2 p-3 sm:grid-cols-2 sm:p-4">
                                        @foreach ($topHeroes as $index => $hero)
                                            @php $heroUrl = route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $hero['hero_id']]); @endphp
                                            <a href="{{ $heroUrl }}" class="group flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] p-2 transition hover:border-radar-500/30 hover:bg-radar-500/[0.06]">
                                                <span class="w-4 text-center font-mono text-[10px] text-mist-500">{{ $index + 1 }}</span>
                                                <span class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10 transition group-hover:ring-radar-400/50">
                                                    @if ($hero['image'])
                                                        <img src="{{ $hero['image'] }}" alt="{{ $hero['name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                    @endif
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-[12px] font-bold text-white transition group-hover:text-radar-200">{{ $hero['name'] }}</span>
                                                    <span class="block text-[10px] text-mist-500">{{ $hero['games'] }} игр</span>
                                                </span>
                                                <span class="font-mono text-[12px] font-bold {{ ($hero['winrate'] ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">
                                                    {{ $hero['winrate'] !== null ? number_format($hero['winrate'], 1, '.', '').'%' : '—' }}
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        </div>

                        <aside class="min-w-0 space-y-4">
                            <section class="card p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="label">Hero wheel</p>
                                        <h2 class="mt-1 text-sm font-bold text-white">Герои последних 25 матчей</h2>
                                    </div>
                                    <span class="badge border-white/10 bg-white/5 text-mist-400">{{ count($heroUsage) }}</span>
                                </div>

                                @if ($heroUsage === [])
                                    <div class="mt-4 grid h-48 place-items-center rounded-xl border border-dashed border-white/10 text-xs text-mist-500">Нет данных</div>
                                @else
                                    <div class="relative mx-auto mt-4 h-56 w-56">
                                        <canvas id="heroUsageChart" role="img" aria-label="Круговая диаграмма всех героев за последние 25 матчей"></canvas>
                                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                            <span class="text-2xl font-black text-white">{{ $heroUsageTotal }}</span>
                                            <span class="text-[9px] uppercase tracking-[0.18em] text-mist-500">матчей</span>
                                        </div>
                                    </div>
                                @endif
                            </section>

                            <section class="card p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="label">Frequently played with</p>
                                        <h2 class="mt-1 text-sm font-bold text-white">Товарищи</h2>
                                    </div>
                                    <span class="badge border-white/10 bg-white/5 text-mist-400">{{ min(5, count($player['peers'] ?? [])) }}</span>
                                </div>

                                @php $topPeers = array_slice($player['peers'] ?? [], 0, 5); @endphp
                                @if ($topPeers === [])
                                    <div class="mt-3 rounded-xl border border-dashed border-white/10 px-3 py-5 text-center text-[11px] text-mist-500">Нет данных о совместных матчах</div>
                                @else
                                    <div class="mt-3 space-y-2">
                                        @foreach ($topPeers as $peer)
                                            <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="group flex items-center gap-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] p-2 transition hover:border-radar-500/30 hover:bg-radar-500/[0.06]">
                                                <span class="h-8 w-8 shrink-0 overflow-hidden rounded-lg bg-ink-800 ring-1 ring-white/10">
                                                    @if ($peer['avatar'])
                                                        <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] }}" class="h-full w-full object-cover" loading="lazy">
                                                    @endif
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-[11px] font-bold text-white transition group-hover:text-radar-200">{{ $peer['nickname'] }}</span>
                                                    <span class="block text-[9px] text-mist-500">{{ $peer['games'] }} совм. игр · {{ $peer['winrate'] !== null ? number_format($peer['winrate'], 1, '.', '').'%' : '—' }}</span>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </section>

                            <section class="card p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="label">Активность</p>
                                        <h2 class="mt-1 text-sm font-bold text-white">Календарь матчей</h2>
                                    </div>
                                    <span class="text-[10px] text-mist-500">{{ number_format($activityTotal, 0, '.', ' ') }} игр</span>
                                </div>

                                <div class="mt-4 flex justify-center">
                                    <div class="w-full max-w-[220px]">
                                        <div class="grid aspect-square w-full grid-flow-col grid-rows-7 gap-[3px]" style="grid-template-columns: repeat(13, minmax(0, 1fr));" aria-label="Активность за последние 90 дней">
                                            @foreach ($activityHeatmap as $day)
                                                <div
                                                    class="flex aspect-square items-center justify-center rounded-[2px] text-[6px] font-black leading-none {{ $heatmapTones[$day['level']] }} transition hover:scale-125 hover:ring-white/30"
                                                    title="{{ $day['label'] }}: {{ $day['count'] }} игр, {{ $day['wins'] }} побед, {{ $day['losses'] }} поражений"
                                                    aria-label="{{ $day['label'] }}: {{ $day['count'] }} игр, {{ $day['wins'] }} побед, {{ $day['losses'] }} поражений"
                                                    data-date="{{ $day['date'] }}"
                                                    data-count="{{ $day['count'] }}"
                                                >{{ $day['count'] > 0 ? $day['count'] : '' }}</div>
                                            @endforeach
                                        </div>
                                        <div class="mt-2 flex items-center justify-between text-[10px] text-mist-500">
                                            <span>90 дней назад</span>
                                            <span>Сегодня</span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>

                <div id="panel-heroes" aria-labelledby="tab-heroes" x-show="tab === 'heroes'" x-cloak x-transition.opacity.duration.200ms role="tabpanel" tabindex="0" aria-label="Герои">
                    @if ($player['heroes'] === [])
                        <div class="card p-10 text-center text-sm text-mist-400">OpenDota ещё не проиндексировала героев этого игрока.</div>
                    @else
                        <div class="card overflow-hidden" x-data="{
                            search: '',
                            sortKey: 'games',
                            sortDir: 'desc',
                            heroes: @js($player['heroes']),
                            heroUrlTemplate: @js(route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => '__HERO__'])),
                            heroUrl(heroId) { return this.heroUrlTemplate.replace('__HERO__', heroId); },
                            get filteredHeroes() {
                                const query = this.search.trim().toLowerCase();
                                const direction = this.sortDir === 'asc' ? 1 : -1;
                                return this.heroes
                                    .filter((hero) => (hero.localized_name || hero.name || '').toLowerCase().includes(query))
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
                                </div>
                                <div class="relative w-full sm:w-72">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" stroke-linecap="round" /></svg>
                                    <input type="search" x-model="search" class="input !py-2.5 !pl-10" placeholder="Поиск героя..." aria-label="Поиск героя по имени" autocomplete="off">
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] text-sm">
                                    <thead class="table-head">
                                        <tr>
                                            <th class="px-4 py-3 font-semibold sm:px-5">Герой</th>
                                            <th class="px-4 py-3 font-semibold" :aria-sort="sortKey === 'games' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                                <button type="button" @click="sortBy('games')" class="inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-white">Матчи <span class="text-[10px]" x-text="indicator('games')"></span></button>
                                            </th>
                                            <th class="px-4 py-3 font-semibold" :aria-sort="sortKey === 'win_rate' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                                <button type="button" @click="sortBy('win_rate')" class="inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-white">Винрейт <span class="text-[10px]" x-text="indicator('win_rate')"></span></button>
                                            </th>
                                            <th class="px-4 py-3 text-right font-semibold sm:px-5" :aria-sort="sortKey === 'kda' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                                <button type="button" @click="sortBy('kda')" class="ml-auto inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-white">KDA <span class="text-[10px]" x-text="indicator('kda')"></span></button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/[0.04]">
                                        <template x-for="hero in filteredHeroes" :key="hero.hero_id">
                                            <tr class="transition hover:bg-white/[0.03]">
                                                <td class="px-4 py-2.5 sm:px-5">
                                                    <a :href="heroUrl(hero.hero_id)" class="group flex items-center gap-3" :aria-label="'Статистика героя ' + hero.localized_name">
                                                        <span class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10 transition group-hover:ring-radar-400/50">
                                                            <img :src="hero.image" :alt="hero.localized_name" class="h-full w-full object-cover" loading="lazy" x-show="hero.image">
                                                        </span>
                                                        <span class="truncate text-[13px] font-semibold text-white transition group-hover:text-radar-200" x-text="hero.localized_name"></span>
                                                    </a>
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-[13px] tabular-nums text-mist-300" x-text="hero.games"></td>
                                                <td class="px-4 py-2.5 font-mono text-[13px] tabular-nums" :class="hero.win_rate == null ? 'text-mist-500' : (hero.win_rate >= 50 ? 'text-emerald-300' : 'text-rose-300')" x-text="hero.win_rate == null ? '—' : hero.win_rate.toFixed(1) + '%'"></td>
                                                <td class="px-4 py-2.5 text-right font-mono text-[13px] tabular-nums text-mist-300 sm:px-5" x-text="hero.kda == null ? '—' : hero.kda.toFixed(2)"></td>
                                            </tr>
                                        </template>
                                        <tr x-show="filteredHeroes.length === 0"><td colspan="4" class="px-4 py-10 text-center text-sm text-mist-400">Герои с таким именем не найдены.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div id="panel-peers" aria-labelledby="tab-peers" x-show="tab === 'peers'" x-cloak x-transition.opacity.duration.200ms role="tabpanel" tabindex="0" aria-label="Товарищи">
                    <div class="card overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-4 sm:px-5">
                            <div>
                                <p class="label">Совместные матчи</p>
                                <h2 class="mt-1 text-lg font-bold text-white">Товарищи</h2>
                            </div>
                            <span class="badge border-white/10 bg-white/5 text-mist-400">{{ count($player['peers'] ?? []) }}</span>
                        </div>

                        @if (($player['peers'] ?? []) === [])
                            <div class="p-10 text-center text-sm text-mist-400">OpenDota не нашла игроков, с которыми вы часто играете.</div>
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
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="application/json" id="stats-radar-chart-data">@json($chartData)</script>
    @vite('resources/js/charts.js')
@endpush
