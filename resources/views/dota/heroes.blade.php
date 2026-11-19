@extends('layouts.app')

@section('title', 'Герои '.$player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Герои игрока '.$player['nickname'].': матчи, винрейт и KDA по каждому герою.')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <a href="{{ route('dota.player', ['accountId' => $player['account_id']]) }}" class="transition hover:text-white">{{ $player['nickname'] }}</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-slate-300">Герои</span>
        </nav>

        <x-player-header :player="$player" :steamProfile="$steamProfile" />

        <x-player-tabs :player="$player" active="heroes" />

        @if ($player['heroes'] === [])
            <div class="card mt-4 p-10 text-center text-sm text-mist-400">Нет данных</div>
        @else
            <div class="card mt-4 overflow-hidden" x-data="{
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
@endsection
