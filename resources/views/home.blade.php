@extends('layouts.app')

@section('title', 'Stats Radar — статистика игроков Dota 2 и CS2')
@section('description', 'Ищите игроков Dota 2 и CS2 по ссылке на Steam, SteamID64 или никнейму. KDA, GPM, XPM, топ героев, последние матчи, VAC-баны и часы в CS2.')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute left-1/2 top-[-24rem] h-[46rem] w-[46rem] -translate-x-1/2 rounded-full bg-radar-500/[0.10] blur-3xl"></div>
            <div class="absolute right-[-8rem] top-24 h-80 w-80 rounded-full bg-cyan-500/[0.07] blur-3xl"></div>
            <div class="absolute left-[-10rem] top-64 h-72 w-72 rounded-full bg-dota-500/[0.05] blur-3xl"></div>
        </div>

        <div class="mx-auto w-full max-w-5xl px-4 pb-10 pt-14 text-center sm:px-6 sm:pt-20 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full border border-radar-500/20 bg-radar-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-radar-300">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-radar-400 opacity-70"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-radar-400"></span>
                </span>
                OpenDota · Steam · Redis cache
            </span>

            <h1 class="mx-auto mt-6 max-w-3xl text-balance text-4xl font-black leading-[1.05] tracking-tight text-white sm:text-6xl">
                Статистика игроков
                <span class="block bg-gradient-to-r from-radar-300 via-emerald-200 to-cyan-300 bg-clip-text text-transparent">под радаром</span>
            </h1>

            <p class="mx-auto mt-5 max-w-2xl text-pretty text-sm leading-relaxed text-mist-400 sm:text-base">
                Профиль, ранг, KDA, GPM и последние матчи для Dota 2. VAC-баны и часы в игре для CS2.
                Один поиск — и весь срез по игроку на одном экране.
            </p>
        </div>

        <div id="search" class="mx-auto w-full max-w-3xl px-4 sm:px-6 lg:px-8">
            @include('partials.search-form')
        </div>
    </section>

    @if ($recentSearches->isNotEmpty())
        <section class="mx-auto mt-12 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="label">История</p>
                    <h2 class="mt-1 text-xl font-bold text-white">Недавние поиски</h2>
                </div>
                <span class="text-[11px] text-mist-500">Последние {{ $recentSearches->count() }} игроков</span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($recentSearches as $search)
                    <a href="{{ $search->profileUrl() }}" class="card card-hover group flex items-center gap-3 p-3">
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

    <section id="how-it-works" class="mx-auto mt-16 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-4 md:grid-cols-3">
            <article class="card card-hover p-5">
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-radar-500/20 bg-radar-500/10 text-radar-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" />
                        <path d="M16 16l4.5 4.5" stroke-linecap="round" />
                    </svg>
                </span>
                <h2 class="mt-4 text-base font-bold text-white">Один запрос — любой формат</h2>
                <p class="mt-2 text-sm leading-relaxed text-mist-400">
                    Ссылка на Steam, SteamID64, Dota-аккаунт или никнейм. Для Dota 2 никнейм ищется через OpenDota,
                    для CS2 достаточно ссылки на профиль.
                </p>
            </article>

            <article class="card card-hover p-5">
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-cyan-500/20 bg-cyan-500/10 text-cyan-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 3l7 4v5c0 4.2-2.9 7.6-7 9-4.1-1.4-7-4.8-7-9V7l7-4z" stroke-linejoin="round" />
                        <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <h2 class="mt-4 text-base font-bold text-white">Кэш вместо лимитов</h2>
                <p class="mt-2 text-sm leading-relaxed text-mist-400">
                    Профили живут в Redis 30 минут, поэтому повторные просмотры не расходуют дневной лимит OpenDota.
                    Кнопка «Обновить» сбрасывает кэш точечно.
                </p>
            </article>

            <article class="card card-hover p-5">
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-dota-500/20 bg-dota-500/10 text-dota-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V5M4 19h16" stroke-linecap="round" />
                        <path d="M8 15l3-4 3 2 4-6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <h2 class="mt-4 text-base font-bold text-white">Только важные метрики</h2>
                <p class="mt-2 text-sm leading-relaxed text-mist-400">
                    KDA, GPM, XPM, винрейт, топ-5 героев и последние 20 матчей с цветовым выделением результата.
                    Для CS2 — часы, приватность и статус блокировок.
                </p>
            </article>
        </div>

        <div class="mt-10 flex flex-col items-start justify-between gap-4 rounded-2xl border border-white/[0.06] bg-gradient-to-r from-ink-900/90 to-ink-850/60 p-5 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm font-semibold text-white">Готовы проверить чей-нибудь винрейт?</p>
                <p class="mt-1 text-xs text-mist-400">Кэширование уже включено — можно начинать с бесплатного OpenDota API.</p>
            </div>
            <a href="#search" class="btn-primary shrink-0">Начать поиск</a>
        </div>
    </section>
@endsection
