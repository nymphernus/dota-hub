@extends('layouts.app')

@section('title', $player['nickname'].' — Dota 2 — Stats Radar')
@section('description', 'Статистика Dota 2 игрока '.$player['nickname'].': ранг, винрейт, KDA, GPM, XPM, топ героев и последние матчи.')

@php
    $topHeroes = $player['top_heroes'] ?? array_slice($player['heroes'], 0, 5);
    $heroUsageTotal = array_sum(array_column($heroUsage, 'games'));
    $activityTotal = array_sum(array_column($activityHeatmap, 'count'));
    $chartData = ['heroUsage' => $heroUsage];
    $heatmapTones = [
        'empty' => 'bg-white/[0.04] ring-1 ring-inset ring-white/[0.03] text-transparent',
        'win' => 'bg-emerald-500 ring-1 ring-inset ring-emerald-300/40 text-emerald-950',
        'loss' => 'bg-rose-500 ring-1 ring-inset ring-rose-300/40 text-rose-950',
        'loss_majority' => 'bg-orange-500 ring-1 ring-inset ring-orange-300/40 text-orange-950',
        'win_majority' => 'bg-yellow-400 ring-1 ring-inset ring-yellow-200/50 text-yellow-950',
        'mixed' => 'bg-amber-400 ring-1 ring-inset ring-amber-200/50 text-amber-950',
    ];
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="truncate text-slate-300">{{ $player['nickname'] }}</span>
        </nav>

        <x-player-header :player="$player" :steamProfile="$steamProfile" />

        <x-player-metrics :player="$player" />

        <x-player-tabs :player="$player" active="overview" />

        @if ($player['is_private'])
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/[0.08] p-4 text-sm text-amber-100">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 018 0v3" stroke-linecap="round" /></svg>
                <div>
                    <p class="font-semibold">Профиль игрока скрыт. Данные недоступны.</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-100/70">Это может быть чужой аккаунт с закрытыми матчами или профиль ещё не проиндексирован.</p>
                </div>
            </div>
        @endif

        <div class="mt-5 grid items-start gap-4 lg:grid-cols-[minmax(0,7fr)_minmax(280px,3fr)]">
            <div class="min-w-0 space-y-4">
                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-3 sm:px-5">
                        <p class="label uppercase tracking-[0.16em]">Последние игры</p>
                    </div>

                    @if ($recentMatches === [])
                        <div class="p-10 text-center text-sm text-mist-400">Матчи не найдены. Возможно, у игрока пока мало игр или данные ещё не проиндексированы.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-sm">
                                <thead class="table-head">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left font-semibold sm:px-4">Герой / режим</th>
                                        <th class="px-2 py-2.5 text-left font-semibold">K / D / A</th>
                                        <th class="px-2 py-2.5 text-right font-semibold">Нетворс</th>
                                        <th class="px-2 py-2.5 text-left font-semibold">GPM / XPM</th>
                                        <th class="px-2 py-2.5 text-left font-semibold">Предметы</th>
                                        <th class="px-3 py-2.5 text-right font-semibold sm:px-4">Дата</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/[0.04]">
                                    @foreach ($recentMatches as $match)
                                        @include('dota.partials.match-row', ['match' => $match, 'accountId' => $player['account_id']])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-3 sm:px-5">
                        <div>
                            <p class="label">Hero pool</p>
                            <h2 class="mt-1 text-base font-bold text-white">Самые играемые герои</h2>
                        </div>
                        <span class="badge border-white/10 bg-white/5 text-mist-400">За всё время</span>
                    </div>

                    @if ($topHeroes === [])
                        <div class="p-8 text-center text-sm text-mist-400">Нет данных</div>
                    @else
                        <div class="grid gap-2 p-3 sm:grid-cols-2 sm:p-4">
                            @foreach ($topHeroes as $index => $hero)
                                @php $heroUrl = route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $hero['hero_id']]); @endphp
                                <a href="{{ $heroUrl }}" class="group flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] p-2 transition hover:border-radar-500/30 hover:bg-radar-500/[0.06]">
                                    <span class="w-4 text-center font-mono text-[10px] text-mist-500">{{ $index + 1 }}</span>
                                    <span class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10 transition group-hover:ring-radar-400/50">
                                        @if ($hero['image'])
                                            <img src="{{ $hero['image'] }}" alt="{{ $hero['name'] }}" class="h-full w-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-bold text-white transition group-hover:text-radar-200">{{ $hero['name'] }}</span>
                                        <span class="block text-[10px] text-mist-500">{{ $hero['games'] }} игр</span>
                                    </span>
                                    <span class="font-mono text-[12px] font-bold {{ ($hero['winrate'] ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">
                                        {{ $hero['winrate'] !== null ? number_format($hero['winrate'], 1, '.', '').'%' : '—' }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <aside class="min-w-0 space-y-4">
                <section class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="label">Активность</p>
                            <h2 class="mt-1 text-sm font-bold text-white">Календарь матчей</h2>
                        </div>
                        <span class="text-[10px] text-mist-500">{{ number_format($activityTotal, 0, '.', ' ') }} игр</span>
                    </div>

                    <div class="mt-4">
                        <div class="grid aspect-square w-full grid-flow-col grid-rows-7 gap-[3px]" style="grid-template-columns: repeat(13, minmax(0, 1fr));" aria-label="Активность за последние 90 дней">
                            @foreach ($activityHeatmap as $day)
                                <div
                                    class="flex aspect-square items-center justify-center rounded-[2px] text-[11px] font-black leading-none {{ $heatmapTones[$day['level']] }} transition hover:scale-125 hover:ring-white/30"
                                    title="{{ $day['label'] }}: {{ $day['count'] }} игр, {{ $day['wins'] }} побед, {{ $day['losses'] }} поражений"
                                    aria-label="{{ $day['label'] }}: {{ $day['count'] }} игр, {{ $day['wins'] }} побед, {{ $day['losses'] }} поражений"
                                    data-date="{{ $day['date'] }}"
                                    data-count="{{ $day['count'] }}"
                                >{{ $day['count'] > 0 ? $day['count'] : '' }}</div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[10px] text-mist-500">
                            <span>90 дней назад</span>
                            <span>Сегодня</span>
                        </div>
                    </div>
                </section>

                <section class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="label">Hero wheel</p>
                            <h2 class="mt-1 text-sm font-bold text-white">Герои последних 25 матчей</h2>
                        </div>
                        <span class="badge border-white/10 bg-white/5 text-mist-400">{{ count($heroUsage) }}</span>
                    </div>

                    @if ($heroUsage === [])
                        <div class="mt-4 grid h-48 place-items-center rounded-xl border border-dashed border-white/10 text-xs text-mist-500">Нет данных</div>
                    @else
                        <div class="relative mx-auto mt-4 h-56 w-56">
                            <canvas id="heroUsageChart" role="img" aria-label="Круговая диаграмма всех героев за последние 25 матчей"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-2xl font-black text-white">{{ $heroUsageTotal }}</span>
                                <span class="text-[9px] uppercase tracking-[0.18em] text-mist-500">матчей</span>
                            </div>
                        </div>
                    @endif
                </section>

                <section class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="label">Frequently played with</p>
                            <h2 class="mt-1 text-sm font-bold text-white">Союзники</h2>
                        </div>
                        <span class="badge border-white/10 bg-white/5 text-mist-400">{{ min(5, count($player['peers'] ?? [])) }}</span>
                    </div>

                    @php $topPeers = array_slice($player['peers'] ?? [], 0, 5); @endphp
                    @if ($topPeers === [])
                        <div class="mt-3 rounded-xl border border-dashed border-white/10 px-3 py-5 text-center text-[11px] text-mist-500">Нет данных о совместных матчах</div>
                    @else
                        <div class="mt-3 space-y-2">
                            @foreach ($topPeers as $peer)
                                <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="group flex items-center gap-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] p-2 transition hover:border-radar-500/30 hover:bg-radar-500/[0.06]">
                                    <span class="h-8 w-8 shrink-0 overflow-hidden rounded-lg bg-ink-800 ring-1 ring-white/10">
                                        @if ($peer['avatar'])
                                            <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] }}" class="h-full w-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[11px] font-bold text-white transition group-hover:text-radar-200">{{ $peer['nickname'] }}</span>
                                        <span class="block text-[9px] text-mist-500">{{ $peer['games'] }} совм. игр · {{ $peer['winrate'] !== null ? number_format($peer['winrate'], 1, '.', '').'%' : '—' }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="application/json" id="stats-radar-chart-data">@json($chartData)</script>
    @vite('resources/js/charts.js')
@endpush
