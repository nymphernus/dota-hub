@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - '.__('ui.records').' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.records'))

@section('profile-content')
    <section class="mt-4" x-data="{ tab: 'all' }">
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <div>
                <h2 class="section-heading">{{ __('ui.records') }}</h2>
                <p class="mt-0.5 text-[11px] text-mist-400">{{ __('ui.best_player_records') }}</p>
            </div>
            <span class="badge text-mist-400" x-text="tab === 'ranked' ? '{{ count($rankedRecords) }} {{ __('ui.records_count') }}' : '{{ count($records) }} {{ __('ui.records_count') }}'"></span>
        </div>

        <div class="mt-3 inline-flex rounded-none bg-ink-900/40 p-1" role="tablist" aria-label="{{ __('ui.records') }}">
            <button type="button" role="tab" aria-controls="records-all" :aria-selected="(tab === 'all').toString()" @click="tab = 'all'"
                class="rounded-none px-3 py-2 text-sm font-semibold transition"
                :class="tab === 'all' ? 'bg-[#2d313e] text-white' : 'text-mist-400 hover:text-mist-200'"
            >{{ __('ui.all_time_tab') }}</button>
            <button type="button" role="tab" aria-controls="records-ranked" :aria-selected="(tab === 'ranked').toString()" @click="tab = 'ranked'"
                class="rounded-none px-3 py-2 text-sm font-semibold transition"
                :class="tab === 'ranked' ? 'bg-[#2d313e] text-white' : 'text-mist-400 hover:text-mist-200'"
            >{{ __('ui.ranked') }}</button>
        </div>

        <div id="records-all" role="tabpanel" x-show="tab === 'all'" x-cloak class="mt-0">
            @include('dota.partials.records-grid', ['records' => $records])
        </div>

        <div id="records-ranked" role="tabpanel" x-show="tab === 'ranked'" x-cloak class="mt-0">
            @include('dota.partials.records-grid', ['records' => $rankedRecords])
        </div>
    </section>
@endsection
