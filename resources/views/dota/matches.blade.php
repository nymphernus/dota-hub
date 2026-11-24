@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - '.__('ui.matches').' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.matches'))

@section('profile-content')
@php
    $resultFilterOptions = ['' => __('ui.all_results'), 'win' => __('ui.win'), 'loss' => __('ui.loss')];
    $modeFilterOptions = ['' => __('ui.all_modes'), 'ranked' => __('ui.ranked'), 'turbo' => __('ui.turbo'), 'normal' => __('ui.normal')];
    $roleFilterOptions = ['' => __('ui.all_roles'), 'carry' => 'Carry', 'mid' => 'Mid', 'offlane' => 'Offlane', 'support' => 'Support', 'hard_support' => 'Hard Support'];
    $selectedHeroId = (int) ($filters['hero_id'] ?? 0);
    $selectedHero = collect($heroOptions ?? [])->firstWhere('hero_id', $selectedHeroId);
    $hasActiveFilters = $selectedHeroId > 0
        || ($filters['result'] ?? '') !== ''
        || ($filters['mode'] ?? '') !== ''
        || ($filters['role'] ?? '') !== ''
        || ($filters['date'] ?? '') !== '';
@endphp

<section class="card mt-4 p-4 sm:p-5" x-data="{ heroModal: false, hasActiveFilters: {{ $hasActiveFilters ? 'true' : 'false' }} }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="section-heading">{{ __('ui.matches') }}</h2>
        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $matches->total() }} {{ __('ui.matches_word') }}</span>
    </div>

    <form id="matches-filter-form" method="GET" action="{{ route('player.matches', ['accountId' => $player['account_id']]) }}" class="flex flex-row flex-wrap items-end gap-2">
        <div class="min-w-[160px] flex-1">
            <span class="label mb-1 block">{{ __('ui.hero') }}</span>
            <input type="hidden" name="hero_id" id="hero_id_input" value="{{ $selectedHeroId }}">
            <button type="button" @click="heroModal = true" class="h-10 w-full border border-[#2d313e] bg-[#1c1e26] px-3 text-left text-sm text-mist-200 hover:border-accent-400/50 focus:outline-none">
                {{ $selectedHero['localized_name'] ?? $selectedHero['name'] ?? __('ui.pick_hero') }}
            </button>
        </div>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">{{ __('ui.result') }}</span>
            <select name="result" onchange="this.form.submit()" class="h-10 w-full border border-[#2d313e] bg-[#1c1e26] text-mist-200 px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($resultFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['result'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">{{ __('ui.game_mode') }}</span>
            <select name="mode" onchange="this.form.submit()" class="h-10 w-full border border-[#2d313e] bg-[#1c1e26] text-mist-200 px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($modeFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['mode'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[140px] flex-1">
            <span class="label mb-1 block">{{ __('ui.role') }}</span>
            <select name="role" onchange="this.form.submit()" class="h-10 w-full border border-[#2d313e] bg-[#1c1e26] text-mist-200 px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                @foreach ($roleFilterOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[150px] flex-1">
            <span class="label mb-1 block">{{ __('ui.date') }}</span>
            <div class="relative">
                <input type="text" id="date_filter" name="date" value="{{ $filters['date'] ?? '' }}" placeholder="{{ __('ui.pick_date') }}" autocomplete="off"
                    class="h-10 w-full border border-[#2d313e] bg-[#1c1e26] px-3 pr-9 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none" />
                <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-mist-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </span>
            </div>
        </label>

        <button type="button"
                class="px-4 py-2 bg-ink-800 border border-line text-mist-300 rounded hover:bg-ink-700 transition"
                :class="!hasActiveFilters ? 'opacity-50 cursor-not-allowed' : ''"
                :disabled="!hasActiveFilters"
                @click="window.location.href = '{{ route('player.matches', ['accountId' => $player['account_id']]) }}'">
            {{ __('ui.reset') }}
        </button>
    </form>

    @include('dota.partials.filter-hero', [
        'heroOptions' => $heroOptions ?? [],
        'selectedHeroId' => $selectedHeroId,
        'formId' => 'matches-filter-form',
        'inputId' => 'hero_id_input',
    ])

    @if ($matches->isEmpty())
        <div class="mt-6 p-10 text-center text-sm text-mist-400">{{ __('ui.no_matches_filter') }}</div>
    @else
        <div class="mt-4 overflow-hidden border border-line bg-[#1c1e26]">
            <table class="w-full text-sm">
                <thead class="table-head">
                    <tr>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.hero_mode') }}</th>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.role') }}</th>
                        <th class="px-2 py-1 text-left font-semibold">K / D / A</th>
                        <th class="px-2 py-1 text-left font-semibold">GPM / XPM</th>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.items') }}</th>
                        <th class="px-2 py-1 text-right font-semibold">{{ __('ui.date') }}</th>
                    </tr>
                </thead>
                <tbody class="table-rows">
                    @include('dota.partials.match-row', ['matches' => $matches])
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

