@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Матчи - DotaHub'))
@section('description', 'Полный список матчей игрока '.$player['nickname'].' с фильтрами по герою, результату и типу игры.')

@section('profile-content')
        @php
            $heroFilterOptions = ['' => 'Все герои'] + collect($heroOptions)
                ->mapWithKeys(fn (array $hero) => [
                    (string) $hero['hero_id'] => ($hero['localized_name'] ?? $hero['name']).' · '.$hero['games'],
                ])
                ->all();
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
            $timeFilterOptions = [
                '' => 'Любое время',
                'today' => 'Сегодня',
                'week' => 'Неделя',
                'month' => 'Месяц',
            ];
        @endphp

        <section
            class="card mt-4 p-4 sm:p-5"
            x-data="{
                hero: @js((string) ($filters['hero_id'] ?: '')),
                result: @js((string) $filters['result']),
                mode: @js((string) $filters['mode']),
                role: @js((string) $filters['role']),
                time: @js((string) ($filters['time'] ?? '')),
                apply() {
                    const params = new URLSearchParams();
                    if (this.hero) params.set('hero_id', this.hero);
                    if (this.result) params.set('result', this.result);
                    if (this.mode) params.set('mode', this.mode);
                    if (this.role) params.set('role', this.role);
                    if (this.time) params.set('time', this.time);
                    const query = params.toString();
                    window.location.href = '{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}' + (query ? '?' + query : '');
                }
            }"
            aria-label="Фильтры матчей"
        >
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @include('dota.partials.filter-select', [
                    'id' => 'filter-hero',
                    'label' => 'Герой',
                    'model' => 'hero',
                    'filterKey' => 'hero_id',
                    'selected' => (string) ($filters['hero_id'] ?: ''),
                    'options' => $heroFilterOptions,
                ])
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
                @include('dota.partials.filter-select', [
                    'id' => 'filter-time',
                    'label' => 'Время',
                    'model' => 'time',
                    'filterKey' => 'time',
                    'selected' => (string) ($filters['time'] ?? ''),
                    'options' => $timeFilterOptions,
                ])
            </div>
            @if ($filters['hero_id'] || $filters['result'] || $filters['mode'] || $filters['role'] || ($filters['time'] ?? ''))
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
