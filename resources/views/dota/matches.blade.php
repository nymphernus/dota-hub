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
                rank: @js((string) $filters['rank']),
                apply() {
                    const params = new URLSearchParams();
                    if (this.hero) params.set('hero_id', this.hero);
                    if (this.result) params.set('result', this.result);
                    if (this.rank) params.set('rank', this.rank);
                    const query = params.toString();
                    window.location.href = '{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}' + (query ? '?' + query : '');
                }
            }"
            aria-label="Фильтры матчей"
        >
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[200px] flex-1">
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
                <div class="min-w-[170px]">
                    <label for="filter-result" class="label mb-1.5 block">Результат</label>
                    <select id="filter-result" x-model="result" @change="apply()" class="input !py-2.5" data-filter="result">
                        <option value="">Все результаты</option>
                        <option value="win" @selected($filters['result'] === 'win')>Победа</option>
                        <option value="loss" @selected($filters['result'] === 'loss')>Поражение</option>
                    </select>
                </div>
                <div class="min-w-[170px]">
                    <label for="filter-rank" class="label mb-1.5 block">Тип игры</label>
                    <select id="filter-rank" x-model="rank" @change="apply()" class="input !py-2.5" data-filter="rank">
                        <option value="">Рейтинг и обычные</option>
                        <option value="ranked" @selected($filters['rank'] === 'ranked')>Рейтинг</option>
                        <option value="unranked" @selected($filters['rank'] === 'unranked')>Обычные</option>
                    </select>
                </div>
                @if ($filters['hero_id'] || $filters['result'] || $filters['rank'])
                    <a href="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="btn-ghost !py-2.5">Сбросить</a>
                @endif
            </div>
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-3 sm:px-5">
                <h2 class="text-base font-bold text-white">Все матчи</h2>
                <span class="text-[11px] text-mist-500">
                    @if ($matches->total() > 0)
                        {{ $matches->firstItem() }}–{{ $matches->lastItem() }} из {{ $matches->total() }}
                    @else
                        Ничего не найдено
                    @endif
                </span>
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
            <div class="mt-4">{{ $matches->links() }}</div>
        @endif
    </div>
@endsection
