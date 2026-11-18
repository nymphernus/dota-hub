@extends('layouts.app')

@section('title', 'Stats Radar — статистика игроков Dota 2 и CS2')
@section('description', 'Ищите игроков Dota 2 и CS2 по ссылке на Steam, SteamID64 или никнейму. KDA, GPM, XPM, топ героев, последние матчи, VAC-баны и часы в CS2.')

@section('content')
    <section class="relative flex min-h-[calc(100vh-4rem)] flex-col justify-center overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute left-1/2 top-1/2 h-[42rem] w-[42rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-radar-500/[0.10] blur-3xl"></div>
            <div class="absolute right-[-8rem] top-24 h-80 w-80 rounded-full bg-cyan-500/[0.07] blur-3xl"></div>
            <div class="absolute bottom-0 left-[-10rem] h-72 w-72 rounded-full bg-dota-500/[0.05] blur-3xl"></div>
        </div>

        <div class="mx-auto w-full max-w-4xl px-4 pb-8 pt-12 text-center sm:px-6 lg:px-8">
            <h1 class="bg-gradient-to-r from-white via-radar-200 to-cyan-200 bg-clip-text text-5xl font-black tracking-tight text-transparent sm:text-7xl">
                Stats Radar
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-mist-400 sm:text-base">
                Статистика игроков Dota 2 и CS2 — профиль, матчи и герои в одном поиске.
            </p>
        </div>

        <div id="search" class="mx-auto w-full max-w-3xl px-4 pb-16 sm:px-6 lg:px-8">
            @include('partials.search-form')
        </div>
    </section>

    @if ($recentSearches->isNotEmpty())
        <section class="mx-auto mt-4 w-full max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="label">История</p>
                    <h2 class="mt-1 text-xl font-bold text-white">Последние поиски</h2>
                </div>
                <span class="text-[11px] text-mist-500">Последние {{ $recentSearches->count() }} игроков</span>
            </div>

            <div class="mt-4 flex gap-3 overflow-x-auto pb-2 [scrollbar-width:thin]">
                @foreach ($recentSearches as $search)
                    <a href="{{ $search->profileUrl() }}" class="card card-hover group flex min-w-[230px] max-w-xs shrink-0 snap-start items-center gap-3 p-3">
                        <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-ink-800 ring-1 ring-white/10">
                            @if ($search->avatar)
                                <img src="{{ $search->avatar }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <span class="grid h-full w-full place-items-center text-sm font-black text-mist-400">
                                    {{ mb_strtoupper(mb_substr($search->nickname ?: $search->identifier, 0, 1)) }}
                                </span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white transition group-hover:text-radar-200">
                                {{ $search->nickname ?: 'Игрок #'.$search->identifier }}
                            </p>
                            <div class="mt-1 flex items-center gap-2">
                                <span @class([
                                    'badge !px-2 !py-0.5 !text-[10px]',
                                    'border-dota-500/20 bg-dota-500/10 text-dota-300' => $search->game === 'dota',
                                    'border-cs2-500/20 bg-cs2-500/10 text-cs2-300' => $search->game === 'cs2',
                                ])>
                                    {{ $search->game === 'dota' ? 'Dota 2' : 'CS2' }}
                                </span>
                                <span class="truncate text-[10px] text-mist-500">
                                    {{ $search->last_searched_at?->diffForHumans(short: true) }}
                                </span>
                            </div>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-mist-500 transition group-hover:translate-x-0.5 group-hover:text-radar-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
