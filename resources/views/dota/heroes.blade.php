@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Герои - DotaHub'))
@section('description', 'Герои игрока '.$player['nickname'].': матчи, винрейт и KDA по каждому герою.')

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
                apply() {
                    const params = new URLSearchParams();
                    if (this.hero) params.set('hero_id', this.hero);
                    if (this.result) params.set('result', this.result);
                    if (this.mode) params.set('mode', this.mode);
                    if (this.role) params.set('role', this.role);
                    if (this.date) params.set('date', this.date);
                    const query = params.toString();
                    window.location.href = '{{ route('dota.player.heroes', ['accountId' => $player['account_id']]) }}' + (query ? '?' + query : '');
                },
                get filteredHeroPickerHeroes() {
                    const query = this.heroPickerQuery.trim().toLowerCase();
                    return this.heroPickerHeroes.filter((hero) => (hero.localized_name || hero.name || '').toLowerCase().includes(query));
                },
                selectHero(heroId) {
                    this.hero = heroId ? String(heroId) : '';
                    this.heroPickerQuery = '';
                    this.heroPickerOpen = false;
                    this.apply();
                }
            }"
            aria-label="Фильтры героев"
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
                        <span class="truncate" x-text="hero ? 'Герой выбран' : 'Все герои'">Все герои</span>
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
                <div>
                    <label for="filter-date" class="label mb-1.5 block">Дата</label>
                    <div class="relative">
                        <input
                            id="filter-date"
                            type="date"
                            x-model="date"
                            @change="apply()"
                            class="w-full bg-[#1c1e26] border border-[#2d313e] rounded px-3 py-2.5 text-sm text-white focus:outline-none"
                            data-filter="date"
                        >
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-mist-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" /><path d="M8 3v4M16 3v4M4 10h16" stroke-linecap="round" /></svg>
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
                    <div id="filter-hero-grid" class="grid max-h-[60vh] grid-cols-8 gap-2 overflow-y-auto p-3 sm:grid-cols-10 lg:grid-cols-12">
                        <button
                            type="button"
                            class="grid h-8 w-8 place-items-center border border-line bg-ink-900/40 text-[10px] font-semibold text-mist-400 transition hover:border-accent-400 hover:text-white"
                            :class="!hero ? 'border-accent-400 bg-accent-400/10 text-white' : ''"
                            @click="selectHero(0)"
                            title="Все герои"
                        >ALL</button>
                        <template x-for="heroOption in filteredHeroPickerHeroes" :key="heroOption.hero_id">
                            <button
                                type="button"
                                class="h-8 w-8 overflow-hidden border border-line bg-ink-900/40 transition hover:border-accent-400"
                                :class="String(heroOption.hero_id) === String(hero) ? 'border-accent-400 bg-accent-400/10' : ''"
                                :title="heroOption.localized_name || heroOption.name"
                                @click="selectHero(heroOption.hero_id)"
                            >
                                <img :src="heroOption.icon || heroOption.image" :alt="heroOption.localized_name || heroOption.name" class="h-full w-full object-cover" loading="lazy">
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            @if ($filters['hero_id'] || $filters['result'] || $filters['mode'] || $filters['role'] || ($filters['date'] ?? ''))
                <div class="mt-3 flex justify-end">
                    <a href="{{ route('dota.player.heroes', ['accountId' => $player['account_id']]) }}" class="btn-ghost !py-2">Сбросить фильтры</a>
                </div>
            @endif
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                <p class="text-[11px] text-mist-500">
                    @if ($heroes->total() > 0)
                        Показаны {{ $heroes->firstItem() }}–{{ $heroes->lastItem() }} из {{ $heroes->total() }}
                    @else
                        Ничего не найдено
                    @endif
                </p>
                <span class="badge border-line bg-ink-900/50 text-mist-400">{{ number_format($heroes->total(), 0, '.', ' ') }} героев</span>
            </div>

            @if ($heroes->isEmpty())
                <div class="p-10 text-center text-sm text-mist-400">По выбранным фильтрам герои не найдены.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-2 py-1 text-left font-semibold">Герой</th>
                            <th class="px-2 py-1 text-left font-semibold">Матчи</th>
                            <th class="px-2 py-1 text-left font-semibold">Винрейт</th>
                            <th class="px-2 py-1 text-left font-semibold">KDA</th>
                        </tr>
                    </thead>
                    <tbody class="table-rows">
                        @foreach ($heroes as $hero)
                            @php
                                $heroId = (int) ($hero['hero_id'] ?? 0);
                                $heroUrl = route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $heroId]);
                                $winrate = $hero['winrate'] ?? $hero['win_rate'] ?? null;
                                $games = (int) ($hero['games'] ?? 0);
                                $kda = $hero['kda'] ?? null;
                            @endphp
                            <tr>
                                <td class="px-2 py-1">
                                    <a href="{{ $heroUrl }}" class="group flex items-center gap-2" aria-label="Статистика героя {{ $hero['name'] ?? $hero['localized_name'] ?? 'Герой' }}">
                                        <img src="{{ $hero['icon'] ?? $hero['image'] ?? '' }}" alt="{{ $hero['name'] ?? $hero['localized_name'] ?? 'Герой' }}" class="h-6 w-6 shrink-0 object-cover" loading="lazy">
                                        <span class="truncate text-[12px] font-semibold text-mist-200 transition group-hover:text-white">{{ $hero['name'] ?? $hero['localized_name'] ?? 'Герой' }}</span>
                                    </a>
                                </td>
                                <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ $games }}</td>
                                <td class="px-2 py-1 font-mono text-[12px] font-semibold tabular-nums {{ $winrate === null ? 'text-mist-500' : ($winrate >= 50 ? 'text-green-500' : 'text-red-500') }}">{{ $winrate === null ? '—' : number_format((float) $winrate, 1, '.', '').'%' }}</td>
                                <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ $kda === null ? '—' : number_format((float) $kda, 2, '.', '') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        @if ($heroes->hasPages())
            <div class="mt-4">{{ $heroes->links('vendor.pagination.dotahub') }}</div>
        @endif
@endsection
