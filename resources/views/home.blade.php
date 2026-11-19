@extends('layouts.app')

@section('title', $pageTitle ?? 'DotaHub — статистика игроков Dota 2')
@section('description', 'Ищите игроков Dota 2 по ссылке на Steam, SteamID64 или никнейму. KDA, GPM, XPM, топ героев, активность и последние матчи.')

@php
    $isDashboard = is_array($dashboard ?? null);
@endphp

@section('content')
    <section class="relative flex min-h-[calc(100vh-4rem)] flex-col justify-center overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute left-1/2 top-1/2 h-[42rem] w-[42rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-500/[0.10] blur-3xl"></div>
            <div class="absolute right-[-8rem] top-24 h-80 w-80 rounded-full bg-accent-500/[0.07] blur-3xl"></div>
            <div class="absolute bottom-0 left-[-10rem] h-72 w-72 rounded-full bg-dota-500/[0.05] blur-3xl"></div>
        </div>

        @if ($isDashboard)
            <div class="mx-auto w-full max-w-5xl px-4 pb-10 pt-10 sm:px-6 lg:px-8">
                <section class="card overflow-hidden p-5 sm:p-6">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <a href="{{ route('dota.player', ['accountId' => $dashboard['account_id']]) }}" class="group flex min-w-0 items-center gap-4">
                            <span class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-ink-800 ring-1 ring-white/10 transition group-hover:ring-gold-500/40">
                                @if (! empty($dashboard['avatar']))
                                    <img src="{{ $dashboard['avatar'] }}" alt="{{ $dashboard['nickname'] }}" class="h-full w-full object-cover">
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-mist-500">Дашборд</span>
                                <span class="mt-1 block truncate text-2xl font-black text-white transition group-hover:text-gold-200">
                                    С возвращением, {{ $dashboard['nickname'] }}
                                </span>
                                <span class="mt-1 block text-xs text-mist-400">
                                    {{ $dashboard['rank_label'] ?: 'Dota 2 профиль' }}
                                </span>
                            </span>
                        </a>

                        <div class="grid min-w-[220px] grid-cols-2 gap-2">
                            <div class="rounded-lg bg-white/[0.03] px-4 py-3">
                                <p class="label">MMR</p>
                                <p class="mt-1 text-xl font-black text-white">
                                    {{ $dashboard['mmr_estimate'] !== null ? number_format((int) $dashboard['mmr_estimate'], 0, '.', ' ') : '—' }}
                                </p>
                                <p class="mt-1 text-[10px] text-mist-500">
                                    {{ $dashboard['mmr_is_estimated'] ? 'Оценка' : 'Открыт' }}
                                </p>
                            </div>
                            <a href="{{ route('dota.player', ['accountId' => $dashboard['account_id']]) }}" class="group grid place-items-center rounded-lg bg-gold-500/10 px-4 py-3 text-center transition hover:bg-gold-500/[0.16]">
                                <span class="text-xs font-bold text-gold-200">Мой профиль</span>
                                <span class="mt-1 text-[10px] text-mist-400 transition group-hover:text-gold-100">Открыть →</span>
                            </a>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-base font-bold text-white">Последние матчи</h2>
                            <a href="{{ route('dota.player', ['accountId' => $dashboard['account_id']]) }}" class="text-[11px] font-semibold text-mist-400 transition hover:text-gold-200">
                                Все матчи
                            </a>
                        </div>

                        @if (! ($dashboard['available'] ?? false))
                            <div class="mt-3 rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-sm text-mist-400">
                                Не удалось загрузить профиль сейчас. Попробуйте открыть страницу профиля чуть позже.
                            </div>
                        @elseif (($dashboard['recent_matches'] ?? []) === [])
                            <div class="mt-3 rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-sm text-mist-400">
                                Матчи пока не найдены.
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
                                        'group rounded-lg p-3 transition',
                                        'bg-emerald-500/[0.08] hover:bg-emerald-500/[0.14]' => $match['win'] ?? false,
                                        'bg-rose-500/[0.08] hover:bg-rose-500/[0.14]' => ! ($match['win'] ?? false),
                                    ])>
                                        <div class="flex items-center gap-3">
                                            <span class="h-10 w-[70px] shrink-0 overflow-hidden rounded-lg bg-ink-800 ring-1 ring-white/10">
                                                @if (! empty($match['hero_image']))
                                                    <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                @endif
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[12px] font-bold text-white">{{ $match['hero_name'] }}</span>
                                                <span @class([
                                                    'mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide',
                                                    'bg-emerald-500/15 text-emerald-300' => $match['win'] ?? false,
                                                    'bg-rose-500/15 text-rose-300' => ! ($match['win'] ?? false),
                                                ])>{{ ($match['win'] ?? false) ? 'Победа' : 'Поражение' }}</span>
                                            </span>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between text-[11px]">
                                            <span class="font-mono font-bold text-white">
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
                <h1 class="bg-gradient-to-r from-white via-gold-200 to-accent-300 bg-clip-text text-5xl font-black tracking-tight text-transparent sm:text-7xl">
                    DotaHub
                </h1>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-mist-400 sm:text-base">
                    Статистика игроков Dota 2 — профиль, матчи и герои в одном поиске.
                </p>
            </div>

            <div id="search" class="mx-auto w-full max-w-3xl px-4 pb-16 sm:px-6 lg:px-8">
                @include('partials.search-form')
            </div>
        @endif
    </section>
@endsection
