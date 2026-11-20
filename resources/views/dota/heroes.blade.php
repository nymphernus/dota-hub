@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Герои - DotaHub'))
@section('description', 'Герои игрока '.$player['nickname'].': матчи, винрейт и KDA по каждому герою.')

@section('profile-content')
        @if ($player['heroes'] === [])
            <div class="card mt-4 p-10 text-center text-sm text-mist-400">Нет данных</div>
        @else
            <section class="card mt-4 overflow-hidden" x-data="{
                heroId: 0,
                heroQuery: '',
                heroOpen: false,
                sortKey: 'games',
                sortDir: 'desc',
                perPage: 24,
                page: 1,
                heroes: @js($player['heroes']),
                heroUrlTemplate: @js(route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => '__HERO__'])),
                heroUrl(heroId) { return this.heroUrlTemplate.replace('__HERO__', heroId); },
                init() {
                    this.$watch('heroId', () => this.page = 1);
                    this.$watch('sortKey', () => this.page = 1);
                    this.$watch('sortDir', () => this.page = 1);
                },
                get filteredHeroOptions() {
                    const query = this.heroQuery.trim().toLowerCase();
                    return this.heroes
                        .filter((hero) => (hero.localized_name || hero.name || '').toLowerCase().includes(query))
                        .slice()
                        .sort((left, right) => String(left.localized_name || left.name).localeCompare(String(right.localized_name || right.name)));
                },
                get selectedHeroName() {
                    if (! this.heroId) return 'Все герои';
                    const hero = this.heroes.find((item) => Number(item.hero_id) === Number(this.heroId));
                    return hero ? (hero.localized_name || hero.name) : 'Герой #' + this.heroId;
                },
                get filteredHeroes() {
                    const direction = this.sortDir === 'asc' ? 1 : -1;
                    return this.heroes
                        .filter((hero) => ! this.heroId || Number(hero.hero_id) === Number(this.heroId))
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
                selectHero(heroId) {
                    this.heroId = Number(heroId) || 0;
                    this.heroQuery = '';
                    this.heroOpen = false;
                    this.page = 1;
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
            }" x-init="init()">
                <div class="flex flex-col gap-3 border-b border-line px-4 py-3 sm:px-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="section-heading">Герои</h2>

                        <div class="relative w-full sm:w-72" @click.outside="heroOpen = false">
                            <button
                                type="button"
                                id="heroes-hero-filter"
                                class="select-control flex w-full items-center justify-between gap-2 text-left"
                                :aria-expanded="heroOpen.toString()"
                                aria-controls="heroes-hero-options"
                                @click="heroOpen = !heroOpen"
                            >
                                <span class="truncate" x-text="selectedHeroName">Все герои</span>
                                <svg class="h-4 w-4 shrink-0 text-mist-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>

                            <div
                                id="heroes-hero-options"
                                x-show="heroOpen"
                                x-cloak
                                x-transition.opacity.duration.100ms
                                class="absolute left-0 right-0 top-full z-30 mt-1 border border-line bg-[#1c1e26]"
                            >
                                <div class="border-b border-line p-2">
                                    <input
                                        type="search"
                                        x-model="heroQuery"
                                        class="input !py-1.5 !pl-8 !text-[12px]"
                                        placeholder="Поиск героя..."
                                        aria-label="Поиск героя в списке"
                                        autocomplete="off"
                                    >
                                    <svg class="pointer-events-none absolute left-5 top-[19px] h-3.5 w-3.5 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" stroke-linecap="round" /></svg>
                                </div>
                                <div class="max-h-64 overflow-y-auto">
                                    <button type="button" class="flex w-full items-center justify-between px-2 py-1.5 text-left text-[12px] text-mist-300 transition hover:bg-ink-700 hover:text-white" @click="selectHero(0)">
                                        <span>Все герои</span>
                                        <span class="text-[10px] text-mist-500" x-show="! heroId">✓</span>
                                    </button>
                                    <template x-for="hero in filteredHeroOptions" :key="hero.hero_id">
                                        <button type="button" class="flex w-full items-center gap-2 px-2 py-1.5 text-left transition hover:bg-ink-700" @click="selectHero(hero.hero_id)">
                                            <span class="h-6 w-10 shrink-0 overflow-hidden bg-ink-800">
                                                <img :src="hero.icon || hero.image" :alt="hero.localized_name || hero.name" class="h-full w-full object-cover" loading="lazy">
                                            </span>
                                            <span class="min-w-0 flex-1 truncate text-[12px] text-mist-300" x-text="hero.localized_name || hero.name"></span>
                                            <span class="font-mono text-[10px] text-mist-500" x-text="hero.games"></span>
                                            <span class="text-[10px] text-accent-300" x-show="heroId === hero.hero_id">✓</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
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

                <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 lg:grid-cols-6">
                    <template x-for="hero in pagedHeroes" :key="hero.hero_id">
                        <a :href="heroUrl(hero.hero_id)" class="group block min-w-0 border border-line bg-ink-900/30 transition hover:border-accent-400 hover:bg-accent-400/10" :aria-label="'Статистика героя ' + (hero.localized_name || hero.name)">
                            <span class="block h-16 w-full overflow-hidden bg-ink-800">
                                <img :src="hero.image" :alt="hero.localized_name || hero.name" class="h-full w-full object-cover object-top" loading="lazy">
                            </span>
                            <span class="block px-2 py-1">
                                <span class="block truncate text-[12px] font-semibold text-mist-200 transition group-hover:text-white" x-text="hero.localized_name || hero.name"></span>
                                <span class="mt-0.5 flex items-center justify-between font-mono text-[10px] text-mist-500">
                                    <span x-text="hero.games + ' игр'"></span>
                                    <span :class="hero.win_rate == null ? 'text-mist-500' : (hero.win_rate >= 50 ? 'text-emerald-300' : 'text-rose-300')" x-text="hero.win_rate == null ? '—' : hero.win_rate.toFixed(1) + '%'"></span>
                                </span>
                            </span>
                        </a>
                    </template>
                </div>

                <div x-show="filteredHeroes.length === 0" class="px-4 py-10 text-center text-sm text-mist-400" x-cloak>Герои с таким именем не найдены.</div>

                <nav role="navigation" aria-label="Пагинация героев" class="flex flex-wrap items-center justify-center gap-2 border-t border-line py-2">
                    <p class="mr-auto text-[11px] text-mist-400">Страница <span class="font-semibold text-mist-200" x-text="page"></span> из <span class="font-semibold text-mist-200" x-text="pageCount"></span></p>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" :disabled="page === 1" @click="goTo(page - 1)" aria-label="Предыдущая страница">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                    <template x-for="number in pageCount" :key="number">
                        <button
                            type="button"
                            class="grid h-9 min-w-9 place-items-center rounded-none border border-line bg-ink-900/40 px-3 text-xs font-semibold text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200"
                            :class="page === number ? 'border-mist-400 bg-mist-400 text-ink-950' : ''"
                            :aria-current="page === number ? 'page' : null"
                            @click="goTo(number)"
                            x-text="number"
                        ></button>
                    </template>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" :disabled="page === pageCount" @click="goTo(page + 1)" aria-label="Следующая страница">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </nav>
            </section>
        @endif
@endsection
