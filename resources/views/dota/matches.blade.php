@extends('layouts.app')

@section('title', 'Матчи '.$player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Полный список матчей игрока '.$player['nickname'].' с фильтрами по герою, результату и типу игры.')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <a href="{{ route('dota.player', ['accountId' => $player['account_id']]) }}" class="transition hover:text-white">{{ $player['nickname'] }}</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-slate-300">Матчи</span>
        </nav>

        <section class="card relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-r from-dota-500/20 via-ink-800/40 to-cyan-500/10" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4 px-4 pb-5 pt-7 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-ink-800 ring-2 ring-ink-900">
                        @if ($player['avatar'])
                            <img src="{{ $player['avatar'] }}" alt="{{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="truncate text-2xl font-black text-white">Матчи · {{ $player['nickname'] }}</h1>
                            @if ($player['rank_label'])
                                <span class="badge border-white/10 bg-white/5 text-mist-300">{{ $player['rank_label'] }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-mist-500">{{ number_format($matches->total(), 0, '.', ' ') }} матчей в истории · ID {{ $player['account_id'] }}</p>
                    </div>
                </div>
                <a href="{{ route('dota.player', ['accountId' => $player['account_id']]) }}" class="btn-ghost !py-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" /></svg>
                    К профилю
                </a>
            </div>
        </section>

        <section
            class="card mt-4 p-4 sm:p-5"
            x-data="{
                hero: @js((string) ($filters['hero_id'] ?: '')),
                result: @js((string) $filters['result']),
                mode: @js((string) $filters['mode']),
                role: @js((string) $filters['role']),
                apply() {
                    const params = new URLSearchParams();
                    if (this.hero) params.set('hero_id', this.hero);
                    if (this.result) params.set('result', this.result);
                    if (this.mode) params.set('mode', this.mode);
                    if (this.role) params.set('role', this.role);
                    const query = params.toString();
                    window.location.href = '{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}' + (query ? '?' + query : '');
                }
            }"
            aria-label="Фильтры матчей"
        >
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="filter-result" class="label mb-1.5 block">Результат</label>
                    <select id="filter-result" x-model="result" @change="apply()" class="input !py-2.5" data-filter="result">
                        <option value="">Все результаты</option>
                        <option value="win" @selected($filters['result'] === 'win')>Победа</option>
                        <option value="loss" @selected($filters['result'] === 'loss')>Поражение</option>
                    </select>
                </div>
                <div>
                    <label for="filter-mode" class="label mb-1.5 block">Режим игры</label>
                    <select id="filter-mode" x-model="mode" @change="apply()" class="input !py-2.5" data-filter="mode">
                        <option value="">Все режимы</option>
                        <option value="ranked" @selected($filters['mode'] === 'ranked')>Ranked</option>
                        <option value="turbo" @selected($filters['mode'] === 'turbo')>Turbo</option>
                        <option value="normal" @selected($filters['mode'] === 'normal')>Normal</option>
                    </select>
                </div>
                <div>
                    <label for="filter-role" class="label mb-1.5 block">Роль</label>
                    <select id="filter-role" x-model="role" @change="apply()" class="input !py-2.5" data-filter="role">
                        <option value="">Все роли</option>
                        <option value="carry" @selected($filters['role'] === 'carry')>Carry</option>
                        <option value="mid" @selected($filters['role'] === 'mid')>Mid</option>
                        <option value="offlane" @selected($filters['role'] === 'offlane')>Offlane</option>
                        <option value="support" @selected($filters['role'] === 'support')>Support</option>
                        <option value="hard_support" @selected($filters['role'] === 'hard_support')>Hard Support</option>
                    </select>
                </div>
                <div>
                    <label for="filter-hero" class="label mb-1.5 block">Герой</label>
                    <select id="filter-hero" x-model="hero" @change="apply()" class="input !py-2.5" data-filter="hero_id">
                        <option value="">Все герои</option>
                        @foreach ($heroOptions as $hero)
                            <option value="{{ $hero['hero_id'] }}" @selected((int) $filters['hero_id'] === (int) $hero['hero_id'])>
                                {{ $hero['localized_name'] ?? $hero['name'] }} · {{ $hero['games'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if ($filters['hero_id'] || $filters['result'] || $filters['mode'] || $filters['role'])
                <div class="mt-3 flex justify-end">
                    <a href="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="btn-ghost !py-2">Сбросить фильтры</a>
                </div>
            @endif
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-3 sm:px-5">
                <p class="text-[11px] text-mist-500">
                    @if ($matches->total() > 0)
                        Показаны {{ $matches->firstItem() }}–{{ $matches->lastItem() }} из {{ $matches->total() }}
                    @else
                        Ничего не найдено
                    @endif
                </p>
                <span class="badge border-white/10 bg-white/5 text-mist-400">{{ number_format($matches->total(), 0, '.', ' ') }} матчей</span>
            </div>

            @if ($matches->isEmpty())
                <div class="p-10 text-center text-sm text-mist-400">По выбранным фильтрам матчи не найдены.</div>
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
                            @foreach ($matches as $match)
                                @include('dota.partials.match-row', ['match' => $match, 'accountId' => $player['account_id']])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($matches->hasPages())
            <div class="mt-4">{{ $matches->links('vendor.pagination.radar') }}</div>
        @endif
    </div>
@endsection
