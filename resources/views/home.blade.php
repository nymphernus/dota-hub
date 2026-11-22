@extends('layouts.app')

@section('title', $pageTitle ?? 'DOTAHUB - '.__('ui.home_subtitle'))
@section('description', __('ui.home_subtitle'))

@php
    $isDashboard = is_array($dashboard ?? null);
@endphp

@section('content')
    <section class="relative flex min-h-[calc(100vh-4rem)] flex-col justify-center overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        </div>

        @if ($isDashboard)
            <div class="mx-auto w-full max-w-5xl px-4 pb-10 pt-10 sm:px-6 lg:px-8">
                <section class="card overflow-hidden p-5 sm:p-4">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <a href="{{ route('dota.player', ['accountId' => $dashboard['account_id']]) }}" class="group flex min-w-0 items-center gap-4">
                            <span class="h-16 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800 transition">
                                @if (! empty($dashboard['avatar']))
                                    <img src="{{ $dashboard['avatar'] }}" alt="{{ $dashboard['nickname'] }}" class="h-full w-full object-cover">
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-mist-500">{{ __('ui.dashboard') }}</span>
                                <span class="mt-1 block truncate text-2xl font-semibold text-gray-900 dark:text-gray-900 dark:text-mist-200 transition group-hover:text-accent-300">
                                    {{ __('ui.welcome_back', ['name' => $dashboard['nickname']]) }}
                                </span>
                                <span class="mt-1 block text-xs text-mist-400">
                                    {{ $dashboard['rank_label'] ?: __('ui.dota2_profile') }}
                                </span>
                            </span>
                        </a>

                        <div class="grid min-w-[220px] grid-cols-2 gap-2">
                            <div class="rounded-none bg-ink-900/30 px-4 py-3">
                                <p class="label">MMR</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-mist-200">
                                    {{ $dashboard['mmr_estimate'] !== null ? number_format((int) $dashboard['mmr_estimate'], 0, '.', ' ') : '—' }}
                                </p>
                                <p class="mt-1 text-[10px] text-mist-500">
                                    {{ $dashboard['mmr_is_estimated'] ? __('ui.estimate') : __('ui.open') }}
                                </p>
                            </div>
                            <a href="{{ route('dota.player', ['accountId' => $dashboard['account_id']]) }}" class="group grid place-items-center rounded-none bg-accent-500/10 px-4 py-3 text-center transition hover:bg-accent-500/[0.16]">
                                <span class="text-xs font-bold text-accent-300">{{ __('ui.my_profile') }}</span>
                                <span class="mt-1 text-[10px] text-mist-400 transition group-hover:text-accent-300">{{ __('ui.open_action') }}</span>
                            </a>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="section-heading">{{ __('ui.recent_matches') }}</h2>
                            <a href="{{ route('dota.player', ['accountId' => $dashboard['account_id']]) }}" class="text-[11px] font-semibold text-mist-400 transition hover:text-accent-300">
                                {{ __('ui.view_all') }}
                            </a>
                        </div>

                        @if (! ($dashboard['available'] ?? false))
                            <div class="mt-3 rounded-none border border-dashed border-line px-4 py-6 text-center text-sm text-mist-400">
                                {{ __('ui.profile_load_failed') }}
                            </div>
                        @elseif (($dashboard['recent_matches'] ?? []) === [])
                            <div class="mt-3 rounded-none border border-dashed border-line px-4 py-6 text-center text-sm text-mist-400">
                                {{ __('ui.no_matches_yet') }}
                            </div>
                        @else
                            <div class="mt-3 grid gap-3 md:grid-cols-3">
                                @foreach ($dashboard['recent_matches'] as $match)
                                    @php
                                        $matchUrl = ! empty($match['match_id'])
                                            ? route('dota.match', ['matchId' => $match['match_id'], 'player_id' => $dashboard['account_id']])
                                            : route('dota.player', ['accountId' => $dashboard['account_id']]);
                                    @endphp
                                    <a href="{{ $matchUrl }}" @class([
                                        'group rounded-none p-3 transition',
                                        'bg-emerald-500/[0.08] hover:bg-emerald-500/[0.14]' => $match['win'] ?? false,
                                        'bg-rose-500/[0.08] hover:bg-rose-500/[0.14]' => ! ($match['win'] ?? false),
                                    ])>
                                        <div class="flex items-center gap-3">
                                            <span class="h-10 w-[70px] shrink-0 overflow-hidden rounded-none bg-ink-800">
                                                @if (! empty($match['hero_image']))
                                                    <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                @endif
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[12px] font-bold text-gray-900 dark:text-mist-200">{{ $match['hero_name'] }}</span>
                                                <span @class([
                                                    'mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide',
                                                    'bg-emerald-500/15 text-emerald-300' => $match['win'] ?? false,
                                                    'bg-rose-500/15 text-rose-300' => ! ($match['win'] ?? false),
                                                ])>{{ ($match['win'] ?? false) ? __('ui.win') : __('ui.loss') }}</span>
                                            </span>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between text-[11px]">
                                            <span class="font-mono font-bold text-gray-900 dark:text-mist-200">
                                                {{ $match['kills'] ?? 0 }}/<span class="text-rose-300">{{ $match['deaths'] ?? 0 }}</span>/{{ $match['assists'] ?? 0 }}
                                            </span>
                                            <span class="text-mist-400">{{ $match['duration_label'] ?? '' }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                <div class="mt-6">
                    @include('partials.search-form')
                </div>
            </div>
        @else
            <div class="mx-auto w-full max-w-4xl px-4 pb-8 pt-12 text-center sm:px-6 lg:px-8">
                <h1 class="text-5xl font-semibold tracking-tight text-gray-900 dark:text-mist-200 sm:text-7xl">
                    DOTAHUB
                </h1>
            </div>

            <div id="search" class="mx-auto w-full max-w-3xl px-4 pb-16 sm:px-6 lg:px-8">
                @include('partials.search-form')
            </div>
        @endif
    </section>
@endsection
