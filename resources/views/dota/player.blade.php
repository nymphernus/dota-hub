@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Обзор - DotaHub'))
@section('description', 'Статистика Dota 2 игрока '.$player['nickname'].': ранг, винрейт, KDA, GPM, XPM, топ героев и последние матчи.')

@php
    $topHeroes = $player['top_heroes'] ?? array_slice($player['heroes'], 0, 5);
    $heroUsageTotal = array_sum(array_column($heroUsage, 'games'));
    $activityTotal = array_sum(array_column($activityHeatmap['days'], 'count'));
@endphp

@section('profile-content')
        @if ($player['is_private'])
            <div class="mt-4 flex items-start gap-3 border border-amber-500/25 bg-amber-500/[0.08] p-3 text-sm text-amber-100">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 018 0v3" stroke-linecap="round" /></svg>
                <div>
                    <p class="font-semibold">Профиль игрока скрыт. Данные недоступны.</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-100/70">Это может быть чужой аккаунт с закрытыми матчами или профиль ещё не проиндексирован.</p>
                </div>
            </div>
        @endif

        <div class="mt-4 grid grid-cols-12 gap-4">
            <div class="col-span-12 min-w-0 space-y-4 lg:col-span-8">
                <section class="overflow-hidden border border-[#2d313e] bg-[#1c1e26] rounded-none">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#2d313e] px-3 py-2">
                        <h2 class="section-heading">Последние игры</h2>
                    </div>

                    @if ($recentMatches === [])
                        <div class="p-4 text-center text-xs text-mist-400">Матчи не найдены. Возможно, у игрока пока мало игр или данные ещё не проиндексированы.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-sm">
                                <thead class="table-head">
                                    <tr>
                                        <th class="px-3 py-1 text-left font-semibold sm:px-4">Герой / режим</th>
                                        <th class="px-2 py-1 text-left font-semibold">Роль</th>
                                        <th class="px-2 py-1 text-left font-semibold">K / D / A</th>
                                        <th class="px-2 py-1 text-left font-semibold">GPM / XPM</th>
                                        <th class="px-2 py-1 text-left font-semibold">Предметы</th>
                                        <th class="px-3 py-1 text-right font-semibold sm:px-4">Дата</th>
                                    </tr>
                                </thead>
                                <tbody class="table-rows">
                                    @foreach ($recentMatches as $match)
                                        @include('dota.partials.match-row', ['match' => $match, 'accountId' => $player['account_id']])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="overflow-hidden border border-[#2d313e] bg-[#1c1e26] rounded-none">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#2d313e] px-3 py-2">
                        <h2 class="section-heading">Самые играемые герои</h2>
                        <span class="badge border-line bg-ink-900/50 text-mist-400">За всё время</span>
                    </div>

                    @if ($topHeroes === [])
                        <div class="p-4 text-center text-xs text-mist-400">Нет данных</div>
                    @else
                        <div class="grid gap-2 p-3 sm:grid-cols-2">
                            @foreach ($topHeroes as $index => $hero)
                                @php $heroUrl = route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $hero['hero_id']]); @endphp
                                <a href="{{ $heroUrl }}" class="group flex items-center gap-2 border border-line bg-ink-900/30 p-1.5 transition hover:border-gold-500/30 hover:bg-gold-500/[0.06]">
                                    <span class="w-4 text-center font-mono text-[10px] text-mist-500">{{ $index + 1 }}</span>
                                    <span class="h-8 w-14 shrink-0 overflow-hidden bg-ink-800">
                                        @if ($hero['image'])
                                            <img src="{{ $hero['image'] }}" alt="{{ $hero['name'] }}" class="h-full w-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-bold text-mist-200 transition group-hover:text-gold-200">{{ $hero['name'] }}</span>
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

            <aside class="col-span-12 min-w-0 space-y-4 lg:col-span-4">
                <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="section-heading">Колесо героев</h2>
                        <span class="badge border-line bg-ink-900/50 text-mist-400" aria-label="Матчей в колесе: {{ $heroUsageTotal }}">{{ $heroUsageTotal }}</span>
                    </div>

                    @include('dota.partials.hero-wheel', ['heroes' => $heroUsage, 'playerId' => $player['account_id']])
                </section>

                @if (($lifetimeStats['total'] ?? 0) > 0)
                    <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="section-heading">Lifetime Stats</h2>
                                <p class="mt-0.5 text-[10px] text-mist-500">Сводка по {{ number_format($lifetimeStats['total'], 0, '.', ' ') }} матчам за всё время</p>
                            </div>
                            <span class="badge border-line bg-ink-900/50 text-mist-400">
                                {{ $lifetimeStats['winrate'] !== null ? number_format($lifetimeStats['winrate'], 1, '.', '').'%' : '—' }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-3">
                            @foreach ($lifetimeStats['groups'] as $group)
                                <div class="min-w-0">
                                    <p class="section-heading mb-1">{{ $group['title'] }}</p>
                                    @if ($group['rows'] === [])
                                        <p class="text-[10px] text-mist-500">Нет данных</p>
                                    @else
                                        <div class="overflow-hidden border border-line">
                                            <table class="w-full text-[11px]">
                                                <thead class="table-head">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left font-semibold">Категория</th>
                                                        <th class="px-2 py-1 text-right font-semibold">Матчи</th>
                                                        <th class="px-2 py-1 text-right font-semibold">Винрейт</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="table-rows">
                                                    @foreach ($group['rows'] as $row)
                                                        <tr>
                                                            <td class="px-2 py-1 font-semibold text-mist-200">{{ $row['label'] }}</td>
                                                            <td class="px-2 py-1 text-right font-mono text-mist-200">{{ $row['games'] }}</td>
                                                            <td class="px-2 py-1 text-right font-mono {{ ($row['winrate'] ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">
                                                                {{ $row['winrate'] !== null ? number_format($row['winrate'], 1, '.', '').'%' : '—' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="section-heading">Активность</h2>
                        <span id="activity-total" class="text-[10px] text-mist-500">{{ number_format($activityTotal, 0, '.', ' ') }} игр</span>
                    </div>

                    <div class="mt-3 flex gap-2" aria-label="Активность за последние 13 недель">
                        <div id="heatmap-weekdays" class="grid w-7 shrink-0 grid-rows-7 gap-[3px] pt-4 text-[9px] font-semibold leading-none text-mist-500" aria-hidden="true">
                            @foreach ($activityHeatmap['weekdays'] as $weekday)
                                <span class="flex items-center justify-end">{{ $weekday }}</span>
                            @endforeach
                        </div>

                        <div class="min-w-0 flex-1">
                            <div id="heatmap-months" class="mb-1 grid gap-[3px] text-[9px] font-semibold text-mist-400" aria-hidden="true"></div>
                            <div id="activity-heatmap" class="grid w-full gap-[3px]" role="grid" aria-label="Дни по неделям"></div>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[10px] text-mist-500">
                        <span>90 дней назад</span>
                        <span>Сегодня</span>
                    </div>
                </section>

                <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="section-heading">Союзники</h2>
                        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ min(5, count($player['peers'] ?? [])) }}</span>
                    </div>

                    @php $topPeers = array_slice($player['peers'] ?? [], 0, 5); @endphp
                    @if ($topPeers === [])
                        <div class="mt-2 border border-dashed border-line px-3 py-4 text-center text-[11px] text-mist-500">Нет данных о совместных матчах</div>
                    @else
                        <div class="mt-2 space-y-1.5">
                            @foreach ($topPeers as $peer)
                                <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="group flex items-center gap-2 border border-line bg-ink-900/30 p-1.5 transition hover:border-gold-500/30 hover:bg-gold-500/[0.06]">
                                    <span class="h-7 w-7 shrink-0 overflow-hidden bg-ink-800">
                                        @if ($peer['avatar'])
                                            <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] }}" class="h-full w-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[11px] font-bold text-mist-200 transition group-hover:text-gold-200">{{ $peer['nickname'] }}</span>
                                        <span class="block text-[9px] text-mist-500">{{ $peer['games'] }} совм. игр · {{ $peer['winrate'] !== null ? number_format($peer['winrate'], 1, '.', '').'%' : '—' }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            </aside>
        </div>
@endsection

@push('scripts')
    <script type="application/json" id="dotahub-heatmap-data">@json($activityHeatmap)</script>
    @vite('resources/js/heatmap.js')
@endpush
