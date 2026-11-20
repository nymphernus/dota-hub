@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Матчи - DotaHub'))
@section('description', 'Полный список матчей игрока '.$player['nickname'].' с фильтрами по герою, результату и типу игры.')

@section('profile-content')
        @php
            $resultFilterOptions = [
                '' => 'Все результаты',
                'win' => 'Победа',
                'loss' => 'Поражение',
            ];
            $modeFilterOptions = [
                '' => 'Все режимы',
                'ranked' => 'Ranked',
                'turbo' => 'Turbo',
                'normal' => 'Normal',
            ];
            $roleFilterOptions = [
                '' => 'Все роли',
                'carry' => 'Carry',
                'mid' => 'Mid',
                'offlane' => 'Offlane',
                'support' => 'Support',
                'hard_support' => 'Hard Support',
            ];
        @endphp

        <section
            class="card mt-4 p-4 sm:p-5"
            x-data="{
                hero: @js((string) ($filters['hero_id'] ?: '')),
                result: @js((string) $filters['result']),
                mode: @js((string) $filters['mode']),
                role: @js((string) $filters['role']),
                date: @js((string) ($filters['date'] ?? '')),
                heroPickerOpen: false,
                heroPickerQuery: '',
                heroPickerHeroes: @js($heroOptions),
                calendarOpen: false,
                calendarMonth: new Date(),
                init() {
                    if (this.date) {
                        const parts = this.date.split('-').map(Number);
                        this.calendarMonth = new Date(parts[0], parts[1] - 1, 1);
                    }
                },
                get monthLabel() {
                    return this.calendarMonth.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
                },
                get calendarDays() {
                    const first = new Date(this.calendarMonth.getFullYear(), this.calendarMonth.getMonth(), 1);
                    const offset = (first.getDay() + 6) % 7;
                    const days = [];

                    for (let index = 0; index < 42; index++) {
                        const day = new Date(first);
                        day.setDate(1 - offset + index);
                        const iso = [
                            day.getFullYear(),
                            String(day.getMonth() + 1).padStart(2, '0'),
                            String(day.getDate()).padStart(2, '0'),
                        ].join('-');

                        days.push({
                            iso,
                            number: day.getDate(),
                            inMonth: day.getMonth() === first.getMonth(),
                            selected: iso === this.date,
                        });
                    }

                    return days;
                },
                toggleCalendar() { this.calendarOpen = ! this.calendarOpen; },
                shiftMonth(step) {
                    this.calendarMonth = new Date(this.calendarMonth.getFullYear(), this.calendarMonth.getMonth() + step, 1);
                },
                chooseDate(day) {
                    this.date = day;
                    this.calendarOpen = false;
                    this.apply();
                },
                clearDate() {
                    this.date = '';
                    this.calendarOpen = false;
                    this.apply();
                },
                get selectedHeroName() {
                    if (! this.hero) return 'Все герои';
                    const hero = this.heroPickerHeroes.find((item) => String(item.hero_id) === String(this.hero));
                    return hero ? (hero.localized_name || hero.name) : 'Герой #' + this.hero;
                },
                get filteredHeroPickerHeroes() {
                    const query = this.heroPickerQuery.trim().toLowerCase();
                    return this.heroPickerHeroes.filter((hero) => (hero.localized_name || hero.name || '').toLowerCase().includes(query));
                },
                selectHero(heroId) {
                    this.hero = heroId ? String(heroId) : '';
                    this.heroPickerQuery = '';
                    this.heroPickerOpen = false;
                },
                apply() {
                    const params = new URLSearchParams();
                    if (this.hero) params.set('hero_id', this.hero);
                    if (this.result) params.set('result', this.result);
                    if (this.mode) params.set('mode', this.mode);
                    if (this.role) params.set('role', this.role);
                    if (this.date) params.set('date', this.date);
                    const query = params.toString();
                    window.location.href = '{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}' + (query ? '?' + query : '');
                }
            }"
            aria-label="Фильтры матчей"
        >
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <span class="label mb-1.5 block">Герой</span>
                    <button
                        type="button"
                        id="filter-hero"
                        data-filter="hero_id"
                        class="select-control flex w-full items-center justify-between gap-2 text-left"
                        :aria-expanded="heroPickerOpen.toString()"
                        aria-controls="filter-hero-grid"
                        @click="heroPickerOpen = true"
                    >
                        <span class="truncate">Выбрать героя</span>
                        <span class="max-w-[120px] truncate text-[10px] text-mist-500" x-text="selectedHeroName">Все герои</span>
                        <svg class="h-4 w-4 shrink-0 text-mist-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>
                @include('dota.partials.filter-select', [
                    'id' => 'filter-result',
                    'label' => 'Результат',
                    'model' => 'result',
                    'filterKey' => 'result',
                    'selected' => (string) $filters['result'],
                    'options' => $resultFilterOptions,
                ])
                @include('dota.partials.filter-select', [
                    'id' => 'filter-mode',
                    'label' => 'Режим игры',
                    'model' => 'mode',
                    'filterKey' => 'mode',
                    'selected' => (string) $filters['mode'],
                    'options' => $modeFilterOptions,
                ])
                @include('dota.partials.filter-select', [
                    'id' => 'filter-role',
                    'label' => 'Роль',
                    'model' => 'role',
                    'filterKey' => 'role',
                    'selected' => (string) $filters['role'],
                    'options' => $roleFilterOptions,
                ])
                <div class="relative" @click.outside="calendarOpen = false" @keydown.escape.window="calendarOpen = false">
                    <label for="filter-date" class="label mb-1.5 block">Дата</label>
                    <button
                        id="filter-date"
                        type="button"
                        name="date"
                        @click="toggleCalendar()"
                        :aria-expanded="calendarOpen.toString()"
                        aria-controls="filter-calendar"
                        class="flex w-full items-center justify-between bg-[#1c1e26] text-white border-[#2d313e] rounded px-3 py-2"
                        data-filter="date"
                    >
                        <span x-text="date || 'За всё время'">{{ $filters['date'] ?? 'За всё время' }}</span>
                        <svg class="h-4 w-4 text-mist-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" /><path d="M8 3v4M16 3v4M4 10h16" stroke-linecap="round" /></svg>
                    </button>

                    <div
                        id="filter-calendar"
                        x-show="calendarOpen"
                        x-cloak
                        class="absolute left-0 top-full z-40 mt-1 w-64 border border-[#2d313e] bg-[#1c1e26] p-2"
                        role="dialog"
                        aria-label="Календарь"
                    >
                        <div class="flex items-center justify-between gap-2 border-b border-line pb-2">
                            <button type="button" class="grid h-7 w-7 place-items-center text-mist-400 transition hover:bg-[#2d313e] hover:text-white" @click="shiftMonth(-1)" aria-label="Предыдущий месяц">‹</button>
                            <span class="text-xs font-semibold capitalize text-mist-200" x-text="monthLabel"></span>
                            <button type="button" class="grid h-7 w-7 place-items-center text-mist-400 transition hover:bg-[#2d313e] hover:text-white" @click="shiftMonth(1)" aria-label="Следующий месяц">›</button>
                        </div>
                        <div class="mt-2 grid grid-cols-7 gap-0.5 text-center text-[9px] font-semibold uppercase text-mist-500">
                            <template x-for="weekday in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="weekday">
                                <span x-text="weekday"></span>
                            </template>
                        </div>
                        <div class="mt-1 grid grid-cols-7 gap-0.5">
                            <template x-for="day in calendarDays" :key="day.iso">
                                <button
                                    type="button"
                                    class="grid h-8 place-items-center text-[11px] transition"
                                    :class="day.selected ? 'bg-[#2d313e] text-white' : (day.inMonth ? 'text-mist-300 hover:bg-[#2d313e] hover:text-white' : 'text-mist-600 hover:bg-[#2d313e]/60')"
                                    @click="chooseDate(day.iso)"
                                    :aria-label="day.iso"
                                    x-text="day.number"
                                ></button>
                            </template>
                        </div>
                        <button type="button" class="mt-2 w-full border-t border-line pt-2 text-[11px] font-semibold text-mist-400 transition hover:text-white" @click="clearDate()">За всё время</button>
                    </div>
                </div>
            </div>

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
                <div class="w-full max-w-3xl border border-line bg-[#1c1e26]" @click.outside="heroPickerOpen = false">
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
                    <div id="filter-hero-grid" class="grid max-h-[60vh] grid-cols-6 gap-2 overflow-y-auto p-3 sm:grid-cols-8 lg:grid-cols-10">
                        <button type="button" class="flex flex-col items-center gap-1 border border-line bg-ink-900/40 p-1 text-[9px] text-mist-400 transition hover:border-accent-400 hover:text-white" @click="selectHero(0)" title="Все герои">
                            <span class="grid h-8 w-8 place-items-center bg-ink-800 text-[10px] font-semibold text-mist-500">ALL</span>
                            <span class="w-full truncate text-center">Все</span>
                        </button>
                        <template x-for="hero in filteredHeroPickerHeroes" :key="hero.hero_id">
                            <button
                                type="button"
                                class="flex flex-col items-center gap-1 border border-line bg-ink-900/40 p-1 text-[9px] text-mist-400 transition hover:border-accent-400 hover:text-white"
                                :class="String(hero.hero_id) === String(hero) ? 'border-accent-400 bg-accent-400/10 text-white' : ''"
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

            @if ($filters['hero_id'] || $filters['result'] || $filters['mode'] || $filters['role'] || ($filters['date'] ?? ''))
                <div class="mt-3 flex justify-end">
                    <a href="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="btn-ghost !py-2">Сбросить фильтры</a>
                </div>
            @endif
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                <p class="text-[11px] text-mist-500">
                    @if ($matches->total() > 0)
                        Показаны {{ $matches->firstItem() }}–{{ $matches->lastItem() }} из {{ $matches->total() }}
                    @else
                        Ничего не найдено
                    @endif
                </p>
                <span class="badge border-line bg-ink-900/50 text-mist-400">{{ number_format($matches->total(), 0, '.', ' ') }} матчей</span>
            </div>

            @if ($matches->isEmpty())
                <div class="p-10 text-center text-sm text-mist-400">По выбранным фильтрам матчи не найдены.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-3 py-1 text-left font-semibold sm:px-4">Герой / режим</th>
                                <th class="px-2 py-1 text-left font-semibold">Роль</th>
                                <th class="px-2 py-1 text-left font-semibold">K / D / A</th>
                                <th class="px-2 py-1 text-left font-semibold">GPM / XPM</th>
                                <th class="px-2 py-1 text-left font-semibold">Предметы</th>
                                <th class="px-3 py-1 text-right font-semibold sm:px-4">Дата</th>
                            </tr>
                        </thead>
                        <tbody class="table-rows">
                            @foreach ($matches as $match)
                                @include('dota.partials.match-row', ['match' => $match, 'accountId' => $player['account_id']])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($matches->hasPages())
            <div class="mt-4">{{ $matches->links('vendor.pagination.dotahub') }}</div>
        @endif
@endsection
