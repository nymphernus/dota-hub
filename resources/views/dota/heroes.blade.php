@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Герои - DotaHub'))
@section('description', 'Герои игрока '.$player['nickname'].': матчи, винрейт и KDA по каждому герою.')

@section('profile-content')
        @if ($player['heroes'] === [])
            <div class="card mt-4 p-10 text-center text-sm text-mist-400">Нет данных</div>
        @else
            <section class="card mt-4 overflow-hidden" x-data="{
                heroId: 0,
                heroPickerOpen: false,
                heroPickerQuery: '',
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
                get filteredHeroPickerHeroes() {
                    const query = this.heroPickerQuery.trim().toLowerCase();
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
                    this.heroPickerQuery = '';
                    this.heroPickerOpen = false;
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
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <h2 class="section-heading">Герои</h2>

                        <div class="w-full sm:w-72">
                            <span class="label mb-1.5 block">Герой</span>
                            <button
                                type="button"
                                id="heroes-hero-filter"
                                class="select-control flex w-full items-center justify-between gap-2 text-left"
                                :aria-expanded="heroPickerOpen.toString()"
                                aria-controls="heroes-hero-grid"
                                @click="heroPickerOpen = true"
                            >
                                <span class="truncate" x-text="selectedHeroName">Все герои</span>
                                <svg class="h-4 w-4 shrink-0 text-mist-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
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
                    <table class="w-full min-w-[620px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-2 py-1 text-left font-semibold sm:px-5">ГЕРОЙ</th>
                                <th class="px-2 py-1 text-left font-semibold">МАТЧИ</th>
                                <th class="px-2 py-1 text-left font-semibold">ПРОЦЕНТ ПОБЕД</th>
                                <th class="px-2 py-1 text-left font-semibold">KDA</th>
                            </tr>
                        </thead>
                        <tbody class="table-rows">
                            <template x-for="hero in pagedHeroes" :key="hero.hero_id">
                                <tr class="odd:bg-[#1b1f2e] even:bg-[#0f111a]">
                                    <td class="px-2 py-1 sm:px-5">
                                        <a :href="heroUrl(hero.hero_id)" class="group flex items-center gap-2" :aria-label="'Статистика героя ' + (hero.localized_name || hero.name)">
                                            <img :src="hero.icon || hero.image" :alt="hero.localized_name || hero.name" class="h-6 w-6 shrink-0 object-cover" loading="lazy">
                                            <span class="truncate text-[12px] font-semibold text-mist-200 transition group-hover:text-white" x-text="hero.localized_name || hero.name"></span>
                                        </a>
                                    </td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300" x-text="hero.games"></td>
                                    <td class="px-2 py-1 font-mono text-[12px] font-semibold tabular-nums" :class="hero.win_rate == null ? 'text-mist-500' : (hero.win_rate >= 50 ? 'text-emerald-300' : 'text-rose-300')" x-text="hero.win_rate == null ? '—' : hero.win_rate.toFixed(1) + '%'"></td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300" x-text="hero.kda == null ? '—' : Number(hero.kda).toFixed(2)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div x-show="filteredHeroes.length === 0" class="px-4 py-10 text-center text-sm text-mist-400" x-cloak>Герои с таким именем не найдены.</div>

                @include('dota.partials.hero-pagination')

                <div
                    x-show="heroPickerOpen"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="heroPickerOpen = false"
                    @keydown.escape.window="heroPickerOpen = false"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Выбор героя"
                >
                    <div class="w-full max-w-3xl border border-line bg-[#1c1e26]">
                        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-2">
                            <h3 class="section-heading">Выберите героя</h3>
                            <button type="button" class="btn-ghost !px-2 !py-1" @click="heroPickerOpen = false" aria-label="Закрыть выбор героя">✕</button>
                        </div>
                        <div class="border-b border-line p-3">
                            <input
                                type="search"
                                x-model="heroPickerQuery"
                                class="input !py-1.5 !pl-8 !text-[12px]"
                                placeholder="Поиск героя..."
                                aria-label="Поиск героя"
                                autocomplete="off"
                            >
                            <svg class="pointer-events-none absolute left-6 top-[74px] h-3.5 w-3.5 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" stroke-linecap="round" /></svg>
                        </div>
                        <div id="heroes-hero-grid" class="grid max-h-[60vh] grid-cols-6 gap-2 overflow-y-auto p-3 sm:grid-cols-8 lg:grid-cols-10">
                            <button type="button" class="flex flex-col items-center gap-1 border border-line bg-ink-900/40 p-1 text-[9px] text-mist-400 transition hover:border-accent-400 hover:text-white" @click="selectHero(0)" title="Все герои">
                                <span class="grid h-8 w-8 place-items-center bg-ink-800 text-[10px] font-semibold text-mist-500">ALL</span>
                                <span class="w-full truncate text-center">Все</span>
                            </button>
                            <template x-for="hero in filteredHeroPickerHeroes" :key="hero.hero_id">
                                <button
                                    type="button"
                                    class="flex flex-col items-center gap-1 border border-line bg-ink-900/40 p-1 text-[9px] text-mist-400 transition hover:border-accent-400 hover:text-white"
                                    :class="Number(heroId) === Number(hero.hero_id) ? 'border-accent-400 bg-accent-400/10 text-white' : ''"
                                    :title="hero.localized_name || hero.name"
                                    @click="selectHero(hero.hero_id)"
                                >
                                    <span class="h-8 w-8 overflow-hidden bg-ink-800">
                                        <img :src="hero.icon || hero.image" :alt="hero.localized_name || hero.name" class="h-full w-full object-cover" loading="lazy">
                                    </span>
                                    <span class="w-full truncate text-center" x-text="hero.localized_name || hero.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        @endif
@endsection
