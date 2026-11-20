@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Герои - DotaHub'))
@section('description', 'Герои игрока '.$player['nickname'].': матчи, винрейт и KDA.')

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

<section class="card mt-4 p-4 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="section-heading">Герои</h2>
            <p class="mt-1 text-sm text-mist-400">Статистика по героям: матчи, винрейт и KDA.</p>
        </div>
        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $heroes->total() }} героев</span>
    </div>

    <form method="GET" action="{{ route('dota.player.heroes', ['accountId' => $player['account_id']]) }}" class="flex flex-row flex-wrap items-end gap-2">
        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">Герой</span>
            <select name="hero_id" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                <option value="0">Все герои</option>
                @foreach (($heroOptions ?? []) as $hero)
                    <option value="{{ $hero['hero_id'] }}" @selected((int) ($filters['hero_id'] ?? 0) === (int) $hero['hero_id'])>{{ $hero['localized_name'] ?? $hero['name'] ?? 'Герой' }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">Результат</span>
            <select name="result" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($resultFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['result'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">Режим</span>
            <select name="mode" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($modeFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['mode'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">Роль</span>
            <select name="role" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($roleFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[150px] flex-1">
            <span class="label mb-1 block">Дата</span>
            <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none" />
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="button-primary h-10 px-4">Применить</button>
            @if (($filters['hero_id'] ?? 0) > 0 || ($filters['result'] ?? '') !== '' || ($filters['mode'] ?? '') !== '' || ($filters['role'] ?? '') !== '' || ($filters['date'] ?? '') !== '')
                <a href="{{ route('dota.player.heroes', ['accountId' => $player['account_id']]) }}" class="button-ghost h-10 px-4">Сбросить</a>
            @endif
        </div>
    </form>

    @if ($heroes->isEmpty())
        <div class="mt-6 p-10 text-center text-sm text-mist-400">Герои не найдены по текущим фильтрам.</div>
    @else
        <div class="mt-4 overflow-hidden border border-[#2d313e] bg-[#1c1e26]">
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
                            $heroUrl = $heroId > 0 ? route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $heroId]) : null;
                            $name = $hero['localized_name'] ?? $hero['name'] ?? 'Герой';
                            $icon = $hero['icon'] ?? $hero['image'] ?? null;
                            $games = (int) ($hero['games'] ?? 0);
                            $winrate = $hero['win_rate'] ?? $hero['winrate'] ?? null;
                            $kda = $hero['kda'] ?? null;
                        @endphp
                        <tr class="transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60">
                            <td class="px-2 py-1">
                                <div class="flex items-center gap-2">
                                    @if ($heroUrl)
                                        <a href="{{ $heroUrl }}" class="h-8 w-8 shrink-0 overflow-hidden rounded bg-ink-800">
                                            @if ($icon)
                                                <img src="{{ $icon }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </a>
                                    @else
                                        <span class="h-8 w-8 shrink-0 overflow-hidden rounded bg-ink-800">
                                            @if ($icon)
                                                <img src="{{ $icon }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        @if ($heroUrl)
                                            <a href="{{ $heroUrl }}" class="truncate text-[12px] font-bold text-mist-200 transition hover:text-accent-300">{{ $name }}</a>
                                        @else
                                            <span class="truncate text-[12px] font-bold text-mist-200">{{ $name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] text-mist-200">{{ $games }}</td>
                            <td class="px-2 py-1 font-mono text-[12px] font-semibold {{ ($winrate ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $winrate !== null ? number_format((float) $winrate, 1, '.', '').'%' : '—' }}
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] text-mist-300">
                                {{ $kda !== null ? number_format((float) $kda, 2, '.', '') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($heroes->hasPages())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4 text-sm">
                <span class="text-mist-400">Страница {{ $heroes->currentPage() }} из {{ $heroes->lastPage() }}</span>
                <div class="flex items-center gap-2">
                    @if ($heroes->onFirstPage())
                        <span class="button-ghost pointer-events-none opacity-50">Назад</span>
                    @else
                        <a href="{{ $heroes->previousPageUrl() }}" class="button-ghost">Назад</a>
                    @endif

                    @if ($heroes->hasMorePages())
                        <a href="{{ $heroes->nextPageUrl() }}" class="button-primary">Далее</a>
                    @else
                        <span class="button-primary pointer-events-none opacity-50">Далее</span>
                    @endif
                </div>
            </div>
        @endif
    @endif
</section>
@endsection
