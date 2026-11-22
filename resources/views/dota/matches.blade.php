@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Матчи - DOTAHUB'))
@section('description', 'Полный список матчей игрока '.$player['nickname'].' с фильтрами по герою, результату, режиму, роли и дате.')

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
            <h2 class="section-heading">Матчи</h2>
            <p class="mt-1 text-sm text-mist-400">История игр с фильтрами по герою, результату, режиму, роли и дате.</p>
        </div>
        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $matches->total() }} матчей</span>
    </div>

    <form method="GET" action="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="flex flex-row flex-wrap items-end gap-2">
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
                <a href="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="button-ghost h-10 px-4">Сбросить</a>
            @endif
        </div>
    </form>

    @if ($matches->isEmpty())
        <div class="mt-6 p-10 text-center text-sm text-mist-400">Матчи не найдены по текущим фильтрам.</div>
    @else
        <div class="mt-4 overflow-hidden border border-[#2d313e] bg-[#1c1e26]">
            <table class="w-full text-sm">
                <thead class="table-head">
                    <tr>
                        <th class="px-2 py-1 text-left font-semibold">Герой</th>
                        <th class="px-2 py-1 text-left font-semibold">KDA</th>
                        <th class="px-2 py-1 text-left font-semibold">GPM</th>
                        <th class="px-2 py-1 text-left font-semibold">Предметы</th>
                    </tr>
                </thead>
                <tbody class="table-rows">
                    @foreach ($matches as $match)
                        @php
                            $matchUrl = ! empty($match['match_id'])
                                ? route('dota.match', ['matchId' => $match['match_id'], 'player_id' => $player['account_id']])
                                : null;
                            $heroUrl = ! empty($match['hero_id'])
                                ? route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $match['hero_id']])
                                : null;
                            $items = $match['items'] ?? [];
                        @endphp
                        <tr class="group transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60"
                            @if ($matchUrl)
                                data-href="{{ $matchUrl }}"
                                onclick="window.location.href = this.dataset.href"
                                tabindex="0"
                            @endif
                        >
                            <td @class([
                                'px-2 py-1',
                                'border-l-4 border-green-500' => $match['win'] ?? false,
                                'border-l-4 border-red-500' => ! ($match['win'] ?? false),
                            ])>
                                <div class="flex items-center gap-2">
                                    @if ($heroUrl)
                                        <a href="{{ $heroUrl }}" onclick="event.stopPropagation()" class="h-9 w-16 shrink-0 overflow-hidden bg-ink-800" aria-label="{{ $match['hero_name'] ?? 'Герой' }}">
                                            @if (! empty($match['hero_image']))
                                                <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] ?? 'Герой' }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </a>
                                    @else
                                        <span class="h-9 w-16 shrink-0 overflow-hidden bg-ink-800">
                                            @if (! empty($match['hero_image']))
                                                <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] ?? 'Герой' }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="truncate text-[12px] font-bold text-mist-200">{{ $match['hero_name'] ?? '—' }}</div>
                                        <div class="text-[10px] text-mist-500">{{ $match['duration_label'] ?? '' }} @if(!empty($match['game_mode_label'] ?? $match['match_type_label'])) · {{ $match['game_mode_label'] ?? $match['match_type_label'] }} @endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] tabular-nums">
                                <span class="text-green-500">{{ (int) ($match['kills'] ?? 0) }}</span><span class="text-mist-500/60">/</span><span class="text-red-500">{{ (int) ($match['deaths'] ?? 0) }}</span><span class="text-mist-500/60">/</span><span class="text-mist-400">{{ (int) ($match['assists'] ?? 0) }}</span>
                                <span class="ml-1 text-[10px] text-mist-500">({{ number_format((float) ($match['kda'] ?? 0), 2, '.', '') }})</span>
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ (int) ($match['gpm'] ?? 0) ?: '—' }}</td>
                            <td class="px-2 py-1">
                                <div class="flex items-center gap-0.5">
                                    @for ($slot = 0; $slot < 6; $slot++)
                                        @php $item = $items[$slot] ?? null; @endphp
                                        @if (is_array($item) && ! empty($item['image']))
                                            <span class="block h-6 w-6 overflow-hidden rounded bg-ink-800" title="{{ $item['name'] ?? '' }}">
                                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? '' }}" class="h-full w-full object-cover" loading="lazy">
                                            </span>
                                        @elseif (is_array($item))
                                            <span class="grid h-6 w-6 place-items-center rounded bg-ink-800 text-[8px] font-bold text-mist-400" title="{{ $item['name'] ?? '' }}">?</span>
                                        @else
                                            <span class="block h-6 w-6 rounded border border-dashed border-line bg-ink-900/15"></span>
                                        @endif
                                    @endfor
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($matches->hasPages())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4 text-sm">
                <span class="text-mist-400">Страница {{ $matches->currentPage() }} из {{ $matches->lastPage() }}</span>
                <div class="flex items-center gap-2">
                    @if ($matches->onFirstPage())
                        <span class="button-ghost pointer-events-none opacity-50">Назад</span>
                    @else
                        <a href="{{ $matches->previousPageUrl() }}" class="button-ghost">Назад</a>
                    @endif

                    @if ($matches->hasMorePages())
                        <a href="{{ $matches->nextPageUrl() }}" class="button-primary">Далее</a>
                    @else
                        <span class="button-primary pointer-events-none opacity-50">Далее</span>
                    @endif
                </div>
            </div>
        @endif
    @endif
</section>
@endsection
