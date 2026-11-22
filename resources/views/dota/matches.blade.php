@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - '.__('ui.matches').' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.matches'))

@section('profile-content')
@php
    $resultFilterOptions = ['' => __('ui.all_results'), 'win' => __('ui.win'), 'loss' => __('ui.loss')];
    $modeFilterOptions = ['' => __('ui.all_modes'), 'ranked' => 'Ranked', 'turbo' => 'Turbo', 'normal' => 'Normal'];
    $roleFilterOptions = ['' => __('ui.all_roles'), 'carry' => 'Carry', 'mid' => 'Mid', 'offlane' => 'Offlane', 'support' => 'Support', 'hard_support' => 'Hard Support'];
    $selectedHeroId = (int) ($filters['hero_id'] ?? 0);
    $selectedHero = collect($heroOptions ?? [])->firstWhere('hero_id', $selectedHeroId);
@endphp

<section class="card mt-4 p-4 sm:p-5" x-data="{ heroModal: false }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="section-heading">{{ __('ui.matches') }}</h2>
        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $matches->total() }} {{ __('ui.matches_word') }}</span>
    </div>

    <form id="matches-filter-form" method="GET" action="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="flex flex-row flex-wrap items-end gap-2">
        <div class="min-w-[160px] flex-1">
            <span class="label mb-1 block">{{ __('ui.hero') }}</span>
            <input type="hidden" name="hero_id" id="hero_id_input" value="{{ $selectedHeroId }}">
            <button type="button" @click="heroModal = true" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-left text-sm text-mist-200 hover:border-accent-400/50 focus:outline-none">
                {{ $selectedHero['localized_name'] ?? $selectedHero['name'] ?? __('ui.pick_hero') }}
            </button>
        </div>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">{{ __('ui.result') }}</span>
            <select name="result" onchange="this.form.submit()" class="h-10 w-full rounded border border-[#2d313e] bg-white text-gray-900 dark:bg-[#1c1e26] px-3 text-sm dark:text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($resultFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['result'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">{{ __('ui.hero') }}</span>
            <select name="mode" onchange="this.form.submit()" class="h-10 w-full rounded border border-[#2d313e] bg-white text-gray-900 dark:bg-[#1c1e26] px-3 text-sm dark:text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($modeFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['mode'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">{{ __('ui.role') }}</span>
            <select name="role" onchange="this.form.submit()" class="h-10 w-full rounded border border-[#2d313e] bg-white text-gray-900 dark:bg-[#1c1e26] px-3 text-sm dark:text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($roleFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[150px] flex-1">
            <span class="label mb-1 block">{{ __('ui.date') }}</span>
            <div class="relative">
                <input type="text" id="date_filter" name="date" value="{{ $filters['date'] ?? '' }}" placeholder="{{ __('ui.pick_date') }}" autocomplete="off"
                    class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 pr-9 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none" />
                <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-mist-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </span>
            </div>
        </label>

        @if ($selectedHeroId > 0 || ($filters['result'] ?? '') !== '' || ($filters['mode'] ?? '') !== '' || ($filters['role'] ?? '') !== '' || ($filters['date'] ?? '') !== '')
            <a href="{{ route('dota.player.matches', ['accountId' => $player['account_id']]) }}" class="button-ghost h-10 px-4">{{ __('ui.reset') }}</a>
        @endif
    </form>

    <!-- Hero picker modal -->
    <div x-show="heroModal" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" @click.self="heroModal=false">
        <div class="w-full max-w-3xl border border-line bg-white dark:bg-[#1c1e26] p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-mist-200">{{ __('ui.pick_hero') }}</h3>
                <button type="button" @click="heroModal=false" class="text-mist-400 hover:text-white">?</button>
            </div>
            <div class="mb-3">
                <button type="button" class="rounded border border-[#2d313e] px-2 py-1 text-xs text-mist-300 hover:bg-ink-900/40"
                    @click="document.getElementById('hero_id_input').value='0'; document.getElementById('matches-filter-form').submit()">{{ __('ui.all_heroes') }}</button>
            </div>
            <div class="grid grid-cols-8 gap-1 max-h-[60vh] overflow-y-auto pr-1">
                @foreach (($heroOptions ?? []) as $hero)
                    @php $hid = (int) ($hero['hero_id'] ?? 0); $himg = $hero['image'] ?? $hero['icon'] ?? null; $hname = $hero['localized_name'] ?? $hero['name'] ?? __('ui.hero'); @endphp
                    <button type="button" title="{{ $hname }}"
                        class="aspect-square overflow-hidden border {{ $selectedHeroId === $hid ? 'border-accent-400' : 'border-[#2d313e]' }} bg-ink-800 hover:border-accent-400"
                        @click="document.getElementById('hero_id_input').value='{{ $hid }}'; document.getElementById('matches-filter-form').submit()">
                        @if ($himg)
                            <img src="{{ $himg }}" alt="{{ $hname }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <span class="grid h-full w-full place-items-center text-[10px] text-mist-400">{{ mb_substr($hname, 0, 1) }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if ($matches->isEmpty())
        <div class="mt-6 p-10 text-center text-sm text-mist-400">{{ __('ui.no_matches_filter') }}</div>
    @else
        <div class="mt-4 overflow-hidden border border-line bg-white dark:bg-[#1c1e26]">
            <table class="w-full text-sm">
                <thead class="table-head">
                    <tr>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.hero') }}</th>
                        <th class="px-2 py-1 text-left font-semibold">KDA</th>
                        <th class="px-2 py-1 text-left font-semibold">GPM</th>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.duration') }}</th>
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
                        <tr class="group transition odd:bg-white even:bg-slate-50 hover:bg-slate-100 text-gray-900 dark:text-mist-200 dark:odd:bg-[#1c1e26] dark:even:bg-[#111318] dark:hover:bg-ink-700/60"
                            @if ($matchUrl)
                                data-href="{{ $matchUrl }}"
                                onclick="window.location.href = this.dataset.href"
                                tabindex="0"
                            @endif
                        >
                            <td @class([
                                'px-2 py-1',
                                'border-l-[3px] border-l-green-500/70' => $match['win'],
                                'border-l-[3px] border-l-red-500/70' => ! $match['win'],
                            ])>
                                <div class="flex items-center gap-2">
                                    @if ($heroUrl)
                                        <a href="{{ $heroUrl }}" onclick="event.stopPropagation()" class="h-8 w-8 shrink-0 overflow-hidden rounded bg-ink-800" aria-label="{{ $match['hero_name'] ?? __('ui.hero') }}">
                                            @if (!empty($match['hero_image']))
                                                <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] ?? __('ui.hero') }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </a>
                                    @else
                                        <span class="h-8 w-8 shrink-0 overflow-hidden rounded bg-ink-800">
                                            @if (!empty($match['hero_image']))
                                                <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] ?? __('ui.hero') }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        @if ($heroUrl)
                                            <a href="{{ $heroUrl }}" onclick="event.stopPropagation()" class="block truncate text-[13px] font-bold leading-tight text-mist-200 transition hover:text-accent-300">
                                                {{ $match['hero_name'] ?? __('ui.hero') }}
                                            </a>
                                        @else
                                            <span class="block truncate text-[13px] font-bold leading-tight text-mist-200">{{ $match['hero_name'] ?? __('ui.hero') }}</span>
                                        @endif
                                        <div class="text-[10px] text-mist-500">{{ $match['duration_label'] ?? '' }} @if(!empty($match['game_mode_label'] ?? $match['match_type_label'])) ? {{ $match['game_mode_label'] ?? $match['match_type_label'] }} @endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] tabular-nums">
                                <span class="text-green-500">{{ (int) ($match['kills'] ?? 0) }}</span><span class="text-mist-500/60">/</span><span class="text-red-500">{{ (int) ($match['deaths'] ?? 0) }}</span><span class="text-mist-500/60">/</span><span class="text-mist-400">{{ (int) ($match['assists'] ?? 0) }}</span>
                                <span class="ml-1 text-[10px] text-mist-500">({{ number_format((float) ($match['kda'] ?? 0), 2, '.', '') }})</span>
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ (int) ($match['gpm'] ?? 0) ?: '-' }}</td>
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
            <div class="mt-4 border-t border-line pt-4">
                {{ $matches->onEachSide(1)->links('vendor.pagination.dotahub') }}
            </div>
        @endif
    @endif
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.flatpickr) {
        flatpickr('#date_filter', {
            dateFormat: 'Y-m-d',
            allowInput: true,
            onChange: function(_, dateStr) {
                const form = document.getElementById('matches-filter-form');
                if (form) form.submit();
            }
        });
    }
});
</script>
@endsection
