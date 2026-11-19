@extends('layouts.app')

@section('title', $pageTitle ?? ($player['nickname'].' - Рекорды - DotaHub'))
@section('description', 'Личные рекорды игрока '.$player['nickname'].' в Dota 2: самый длинный матч, максимум убийств, GPM и другие достижения.')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <a href="{{ route('dota.player', ['accountId' => $player['account_id']]) }}" class="transition hover:text-white">{{ $player['nickname'] }}</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-slate-300">Рекорды</span>
        </nav>

        <x-player-header :player="$player" :steamProfile="$steamProfile" />

        <x-player-tabs :player="$player" active="records" />

        <section class="mt-4">
            <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                <div>
                    <h2 class="text-lg font-bold text-white">Рекорды</h2>
                    <p class="mt-0.5 text-[11px] text-mist-500">Лучшие результаты игрока в отдельных матчах</p>
                </div>
                @if ($records !== [])
                    <span class="badge border-white/10 bg-white/5 text-mist-400">{{ count($records) }} достижений</span>
                @endif
            </div>

            @if ($records === [])
                <div class="card mt-3 p-10 text-center text-sm text-mist-400">
                    Рекорды пока недоступны. Данные OpenDota могут отсутствовать для этого игрока.
                </div>
            @else
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($records as $record)
                        <article class="card relative overflow-hidden p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="label uppercase tracking-[0.14em] text-mist-500">{{ $record['label'] }}</p>
                                    <p class="mt-2 text-2xl font-black tracking-tight text-white">{{ $record['value_label'] }}</p>
                                </div>
                                @if ($record['hero_image'] || $record['hero_icon'])
                                    <span class="h-12 w-20 shrink-0 overflow-hidden rounded-lg bg-ink-800 ring-1 ring-white/10">
                                        <img src="{{ $record['hero_image'] ?? $record['hero_icon'] }}" alt="{{ $record['hero_name'] ?? 'Герой' }}" class="h-full w-full object-cover" loading="lazy">
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-mist-500">
                                @if ($record['hero_name'])
                                    <span class="font-semibold text-mist-300">{{ $record['hero_name'] }}</span>
                                @endif
                                @if ($record['played_at'])
                                    <span>{{ \Carbon\CarbonImmutable::parse($record['played_at'])->format('d.m.Y') }}</span>
                                @endif
                                @if ($record['match_id'])
                                    <a href="{{ route('dota.match', ['matchId' => $record['match_id']]) }}" class="transition hover:text-radar-300">
                                        Матч #{{ $record['match_id'] }}
                                    </a>
                                @endif
                                @if ($record['is_team'])
                                    <span class="badge border-white/10 bg-white/5 text-mist-400">Командный</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
