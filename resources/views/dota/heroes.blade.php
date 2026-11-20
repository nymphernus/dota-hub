@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Герои - DotaHub'))
@section('description', 'Герои игрока '.$player['nickname'].': матчи, винрейт и KDA по каждому герою.')

@section('profile-content')
        @if ($player['heroes'] === [])
            <div class="card mt-4 p-10 text-center text-sm text-mist-400">Нет данных</div>
        @else
            <div class="card mt-4 overflow-hidden" x-data="{
                search: '',
                sortKey: 'games',
                sortDir: 'desc',
                perPage: 50,
                page: 1,
                heroes: @js($player['heroes']),
                heroUrlTemplate: @js(route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => '__HERO__'])),
                heroUrl(heroId) { return this.heroUrlTemplate.replace('__HERO__', heroId); },
                init() {
                    this.$watch('search', () => this.page = 1);
                    this.$watch('sortKey', () => this.page = 1);
                    this.$watch('sortDir', () => this.page = 1);
                },
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
                get pageCount() { return Math.max(1, Math.ceil(this.filteredHeroes.length / this.perPage)); },
                get pagedHeroes() {
                    const start = (this.page - 1) * this.perPage;
                    return this.filteredHeroes.slice(start, start + this.perPage);
                },
                goTo(target) { this.page = Math.min(Math.max(1, target), this.pageCount); },
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
            }" x-init="init()">
                <div class="flex flex-col gap-3 border-b border-line px-4 py-4 sm:px-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="section-heading">Герои</h2>
                        <div class="relative w-full sm:w-72">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" stroke-linecap="round" /></svg>
                            <input type="search" x-model="search" class="input !py-2.5 !pl-10" placeholder="Поиск героя..." aria-label="Поиск героя по имени" autocomplete="off">
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <label for="heroes-sort" class="label">Фильтр</label>
                        <select id="heroes-sort" x-model="sortKey" class="select-control max-w-xs" aria-label="Сортировка героев">
                            <option value="games">По количеству игр</option>
                            <option value="win_rate">По винрейту</option>
                            <option value="kda">По KDA</option>
                        </select>
                        <button type="button" class="btn-ghost !py-2" @click="sortDir = sortDir === 'desc' ? 'asc' : 'desc'" x-text="sortDir === 'desc' ? 'По убыванию' : 'По возрастанию'"></button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-1 font-semibold sm:px-5">Герой</th>
                                <th class="px-4 py-1 font-semibold" :aria-sort="sortKey === 'games' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                    <button type="button" @click="sortBy('games')" class="inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-mist-200">Матчи <span class="text-[10px]" x-text="indicator('games')"></span></button>
                                </th>
                                <th class="px-4 py-1 font-semibold" :aria-sort="sortKey === 'win_rate' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                    <button type="button" @click="sortBy('win_rate')" class="inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-mist-200">Винрейт <span class="text-[10px]" x-text="indicator('win_rate')"></span></button>
                                </th>
                                <th class="px-4 py-1 text-right font-semibold sm:px-5" :aria-sort="sortKey === 'kda' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
                                    <button type="button" @click="sortBy('kda')" class="ml-auto inline-flex items-center gap-1 uppercase tracking-[0.16em] transition hover:text-mist-200">KDA <span class="text-[10px]" x-text="indicator('kda')"></span></button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="table-rows">
                            <template x-for="hero in pagedHeroes" :key="hero.hero_id">
                                <tr class="transition hover:bg-ink-900/30">
                                    <td class="px-2 py-1 sm:px-5">
                                        <a :href="heroUrl(hero.hero_id)" class="group flex items-center gap-3" :aria-label="'Статистика героя ' + hero.localized_name">
                                            <span class="h-9 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800   transition ">
                                                <img :src="hero.image" :alt="hero.localized_name" class="h-full w-full object-cover" loading="lazy" x-show="hero.image">
                                            </span>
                                            <span class="truncate text-[13px] font-semibold text-mist-200 transition group-hover:text-gold-200" x-text="hero.localized_name"></span>
                                        </a>
                                    </td>
                                    <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300" x-text="hero.games"></td>
                                    <td class="px-2 py-1 font-mono text-[13px] tabular-nums" :class="hero.win_rate == null ? 'text-mist-500' : (hero.win_rate >= 50 ? 'text-emerald-300' : 'text-rose-300')" x-text="hero.win_rate == null ? '—' : hero.win_rate.toFixed(1) + '%'"></td>
                                    <td class="px-2 py-1 text-right font-mono text-[13px] tabular-nums text-mist-300 sm:px-5" x-text="hero.kda == null ? '—' : hero.kda.toFixed(2)"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredHeroes.length === 0"><td colspan="4" class="px-4 py-10 text-center text-sm text-mist-400">Герои с таким именем не найдены.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-1 sm:px-5">
                    <p class="text-[11px] text-mist-400">
                        Страница <span class="font-semibold text-mist-200" x-text="page"></span> из <span class="font-semibold text-mist-200" x-text="pageCount"></span>
                        <span class="text-mist-500">·</span>
                        <span x-text="filteredHeroes.length"></span> героев
                    </p>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-ghost !px-3 !py-1.5" @click="goTo(page - 1)" :disabled="page === 1">Назад</button>
                        <template x-for="number in pageCount" :key="number">
                            <button
                                type="button"
                                class="rounded-none border border-line px-2.5 py-1.5 text-[11px] font-semibold transition"
                                :class="page === number ? 'bg-gold-400/15 text-gold-200' : 'text-mist-400 hover:bg-ink-700 hover:text-mist-200'"
                                @click="goTo(number)"
                                x-text="number"
                            ></button>
                        </template>
                        <button type="button" class="btn-ghost !px-3 !py-1.5" @click="goTo(page + 1)" :disabled="page === pageCount">Вперед</button>
                    </div>
                </div>
            </div>
        @endif
@endsection
