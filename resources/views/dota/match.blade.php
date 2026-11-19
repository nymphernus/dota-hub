@extends('layouts.app')

@section('title', 'Матч #'.$match['match_id'].' — Dota 2 — Stats Radar')
@section('description', 'Детальная статистика матча Dota 2 #'.$match['match_id'].': счёт, роли, график нетворса, закуп, длительность, герои, K/D/A, GPM, XPM и урон игроков Radiant и Dire.')

@php
    $profilePlayerId = request()->integer('player_id') ?: null;
    $radiantWin = $match['radiant_win'];
    $winner = $radiantWin ? 'Radiant' : 'Dire';
    $startedAt = $match['started_at'] ? \Carbon\CarbonImmutable::parse($match['started_at']) : null;
    $hasNetWorth = $match['net_worth_chart']['has_data'] ?? false;
    $teams = [
        'radiant' => [
            'label' => 'Radiant',
            'won' => $radiantWin,
            'score' => $match['radiant_score'],
            'players' => $match['players']['radiant'],
        ],
        'dire' => [
            'label' => 'Dire',
            'won' => ! $radiantWin,
            'score' => $match['dire_score'],
            'players' => $match['players']['dire'],
        ],
    ];
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-mist-400">Dota 2</span>
            @if ($profilePlayerId)
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
                <a href="{{ route('dota.player', ['accountId' => $profilePlayerId]) }}" class="transition hover:text-white">Профиль игрока</a>
            @endif
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="truncate text-slate-300">Матч #{{ $match['match_id'] }}</span>
        </nav>

        <section class="card relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-r {{ $radiantWin ? 'from-emerald-500/25 via-ink-800/40' : 'from-rose-500/25 via-ink-800/40' }} to-cyan-500/10" aria-hidden="true"></div>
            <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full {{ $radiantWin ? 'bg-emerald-500/10' : 'bg-rose-500/10' }} blur-3xl" aria-hidden="true"></div>

            <div class="relative px-4 pb-6 pt-14 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge border-dota-500/25 bg-dota-500/10 text-dota-300">Dota 2</span>
                            <span @class([
                                'badge',
                                'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $radiantWin,
                                'border-rose-500/25 bg-rose-500/10 text-rose-300' => ! $radiantWin,
                            ])>
                                Победа {{ $winner }}
                            </span>
                            @if ($match['is_ranked'])
                                <span class="badge border-amber-500/25 bg-amber-500/10 text-amber-300">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3z" stroke-linejoin="round" /></svg>
                                    Ranked
                                </span>
                            @elseif ($match['lobby_type_label'])
                                <span class="badge border-white/10 bg-white/5 text-mist-300">{{ $match['lobby_type_label'] }}</span>
                            @endif
                            @if ($match['game_mode_label'])
                                <span class="badge border-white/10 bg-white/5 text-mist-300">{{ $match['game_mode_label'] }}</span>
                            @endif
                        </div>

                        <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Матч <span class="font-mono text-2xl text-mist-300 sm:text-3xl">#{{ $match['match_id'] }}</span>
                        </h1>

                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-mist-400">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" /></svg>
                                {{ $match['duration_label'] }}
                            </span>
                            @if ($startedAt)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3v4M16 3v4M4 10h16" stroke-linecap="round" /></svg>
                                    <time datetime="{{ $match['started_at'] }}" title="{{ $startedAt->format('d.m.Y H:i') }}">{{ $startedAt->format('d.m.Y H:i') }}</time>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <div class="rounded-xl border border-emerald-500/15 bg-emerald-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-emerald-300/70">Radiant</p>
                            <p class="text-xl font-black text-emerald-300">{{ $match['radiant_score'] }}</p>
                        </div>
                        <span class="text-xs font-bold text-mist-500">:</span>
                        <div class="rounded-xl border border-rose-500/15 bg-rose-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-rose-300/70">Dire</p>
                            <p class="text-xl font-black text-rose-300">{{ $match['dire_score'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-4" aria-label="График нетворса">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="label">Economy</p>
                    <h2 class="mt-1 text-lg font-bold text-white">Нетворс по минутам</h2>
                </div>
                <span class="text-[11px] text-mist-500">
                    @if ($hasNetWorth)
                        OpenDota parsed data · Radiant и Dire
                    @else
                        График появится после парсинга матча
                    @endif
                </span>
            </div>

            @if ($hasNetWorth)
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-emerald-300">Radiant</h3>
                            <span class="badge border-emerald-500/20 bg-emerald-500/10 text-[10px] text-emerald-300">Золото</span>
                        </div>
                        <div class="relative mt-4 h-64">
                            <canvas id="radiantNetWorthChart" role="img" aria-label="График нетворса игроков Radiant по минутам"></canvas>
                        </div>
                    </div>

                    <div class="card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-rose-300">Dire</h3>
                            <span class="badge border-rose-500/20 bg-rose-500/10 text-[10px] text-rose-300">Золото</span>
                        </div>
                        <div class="relative mt-4 h-64">
                            <canvas id="direNetWorthChart" role="img" aria-label="График нетворса игроков Dire по минутам"></canvas>
                        </div>
                    </div>
                </div>
            @else
                <div class="card p-6 text-center text-sm text-mist-400">
                    OpenDota ещё не распарсила историю нетворса этого матча. Роли и закуп могут быть доступны частично.
                </div>
            @endif
        </section>

        @foreach ($teams as $teamKey => $team)
            <section class="card mt-4 overflow-hidden" aria-label="Состав {{ $team['label'] }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.06] px-4 py-4 sm:px-5">
                    <div class="flex items-center gap-3">
                        <span @class([
                            'grid h-9 w-9 place-items-center rounded-lg text-[10px] font-black uppercase',
                            'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/25' => $teamKey === 'radiant',
                            'bg-rose-500/15 text-rose-300 ring-1 ring-rose-500/25' => $teamKey === 'dire',
                        ])>
                            {{ mb_substr($team['label'], 0, 1) }}
                        </span>
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.18em] text-mist-500">Команда</p>
                            <h2 class="text-lg font-bold text-white">{{ $team['label'] }}</h2>
                        </div>
                    </div>
                    <span @class([
                        'badge',
                        'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $team['won'],
                        'border-rose-500/25 bg-rose-500/10 text-rose-300' => ! $team['won'],
                    ])>
                        {{ $team['won'] ? 'Победа' : 'Поражение' }} · {{ $team['score'] }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-5">Герой</th>
                                <th class="px-4 py-3 font-semibold">Роль</th>
                                <th class="px-4 py-3 font-semibold">K / D / A</th>
                                <th class="px-4 py-3 font-semibold">GPM</th>
                                <th class="px-4 py-3 font-semibold">XPM</th>
                                <th class="px-4 py-3 font-semibold">Урон</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-5">Закуп</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]" x-data="{ openPlayer: null }">
                            @foreach ($team['players'] as $player)
                                @php
                                    $roleLabel = $player['role']['label'] ?? null;
                                    $roleName = $player['role']['name'] ?? null;
                                    $roleSource = $player['role']['source'] ?? null;
                                    $roleClasses = match ($roleLabel) {
                                        'Safe' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
                                        'Mid' => 'border-sky-500/25 bg-sky-500/10 text-sky-300',
                                        'Off' => 'border-amber-500/25 bg-amber-500/10 text-amber-300',
                                        'Soft' => 'border-violet-500/25 bg-violet-500/10 text-violet-300',
                                        'Hard' => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
                                        default => 'border-white/10 bg-white/5 text-mist-400',
                                    };
                                    $isCurrentPlayer = $profilePlayerId !== null
                                        && (int) ($player['account_id'] ?? 0) === $profilePlayerId;
                                    $partyId = $player['party_id'] ?? null;
                                @endphp
                                <tr @class([
                                    'transition hover:bg-white/[0.03]',
                                    'bg-sky-500/[0.09] ring-2 ring-inset ring-sky-500/50' => $isCurrentPlayer,
                                    'border-l-2 border-sky-400/40' => ! $isCurrentPlayer && $partyId,
                                ])>
                                    <td class="px-4 py-3 sm:px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10">
                                                @if ($player['hero_image'])
                                                    <img src="{{ $player['hero_image'] }}" alt="{{ $player['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                @else
                                                    <span class="grid h-full w-full place-items-center text-xs font-bold text-mist-500">
                                                        {{ mb_strtoupper(mb_substr($player['hero_name'], 0, 1)) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-[13px] font-semibold text-white">{{ $player['hero_name'] }}</p>
                                                <p class="mt-0.5 flex items-center gap-1.5 truncate text-[10px] text-mist-500">
                                                    @if ($player['nickname'])
                                                        <span class="truncate">{{ $player['nickname'] }}</span>
                                                    @endif
                                                    @if ($player['level'] > 0)
                                                        <span>ур. {{ $player['level'] }}</span>
                                                    @endif
                                                    @if ($partyId)
                                                        <span class="rounded bg-sky-500/10 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-sky-300">Party</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($roleLabel)
                                            <span class="badge !px-2 !py-0.5 !text-[10px] {{ $roleClasses }}" title="{{ $roleName }}{{ $roleSource ? ' · '.$roleSource : '' }}">
                                                {{ $roleLabel }}
                                            </span>
                                            <p class="mt-1 text-[10px] text-mist-500">{{ $roleName }}</p>
                                            @if (($player['impact'] ?? null) !== null)
                                                <p class="mt-0.5 text-[10px] font-semibold text-cyan-300/80" title="STRATZ impact">IMP {{ number_format($player['impact'], 1, '.', '') }}</p>
                                            @endif
                                        @else
                                            <span class="text-[11px] text-mist-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums">
                                        <span class="text-emerald-300">{{ $player['kills'] }}</span>
                                        <span class="text-mist-500/60">/</span>
                                        <span class="text-rose-300">{{ $player['deaths'] }}</span>
                                        <span class="text-mist-500/60">/</span>
                                        <span class="text-sky-300">{{ $player['assists'] }}</span>
                                        <span class="ml-1.5 text-[10px] text-mist-500">({{ number_format($player['kda'], 2, '.', '') }})</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($player['gpm'], 0, '.', ' ') }}</td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($player['xpm'], 0, '.', ' ') }}</td>
                                    <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($player['hero_damage'], 0, '.', ' ') }}</td>
                                    <td class="px-4 py-3 text-right sm:px-5">
                                        @if ($player['purchases'] !== [])
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-2.5 py-1.5 text-[11px] font-semibold text-mist-300 transition hover:border-radar-500/30 hover:bg-radar-500/10 hover:text-radar-200"
                                                @click="openPlayer = openPlayer === {{ $loop->index }} ? null : {{ $loop->index }}"
                                                :aria-expanded="(openPlayer === {{ $loop->index }}).toString()"
                                                aria-controls="purchases-{{ $teamKey }}-{{ $loop->index }}"
                                            >
                                                Закуп
                                                <span class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[10px] text-mist-400">{{ count($player['purchases']) }}</span>
                                                <svg class="h-3 w-3 transition" :class="openPlayer === {{ $loop->index }} ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                            </button>
                                        @else
                                            <span class="text-[11px] text-mist-500">—</span>
                                        @endif
                                    </td>
                                </tr>

                                @if ($player['purchases'] !== [])
                                    <tr id="purchases-{{ $teamKey }}-{{ $loop->index }}" x-show="openPlayer === {{ $loop->index }}" x-cloak x-transition.opacity.duration.150ms class="bg-ink-950/60">
                                        <td colspan="7" class="px-4 pb-4 pt-1 sm:px-5">
                                            <div class="rounded-xl border border-white/[0.06] bg-ink-900/70 p-3">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <p class="label">Закуп · {{ $player['hero_name'] }}</p>
                                                    <p class="text-[10px] text-mist-500">{{ $player['purchase_log_count'] }} покупок · показаны ключевые предметы</p>
                                                </div>
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    @foreach ($player['purchases'] as $item)
                                                        <div class="flex items-center gap-2 rounded-lg border border-white/[0.06] bg-white/[0.025] p-2 pr-3" title="{{ $item['name'] }} · {{ $item['time_label'] }}">
                                                            <div class="grid h-8 w-10 shrink-0 place-items-center overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10">
                                                                <img src="{{ $item['image'] }}" alt="" class="h-full w-full object-contain" loading="lazy" referrerpolicy="no-referrer">
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="max-w-40 truncate text-[11px] font-semibold text-mist-300">{{ $item['name'] }}</p>
                                                                <p class="text-[10px] text-mist-500">
                                                                    {{ $item['time_label'] }}@if ($item['cost'] > 0) · {{ number_format($item['cost'], 0, '.', ' ') }}@endif
                                                                </p>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach

                            @if ($team['players'] === [])
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-mist-400">Нет данных по игрокам.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/[0.06] bg-ink-900/60 px-4 py-3 text-[11px] text-mist-500">
            <span>Данные OpenDota · кэш матча 1 час</span>
            <div class="flex items-center gap-3">
                @if ($profilePlayerId)
                    <a href="{{ route('dota.player', ['accountId' => $profilePlayerId]) }}" class="inline-flex items-center gap-1 font-semibold text-radar-300 transition hover:text-radar-200">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Вернуться к профилю
                    </a>
                @endif
                <a href="{{ route('home') }}" class="font-semibold text-mist-400 transition hover:text-white">На главную</a>
            </div>
        </div>
    </div>
@endsection

@if ($hasNetWorth)
    @push('scripts')
        <script type="application/json" id="stats-radar-match-chart-data">@json($match['net_worth_chart'])</script>
        @vite('resources/js/match-charts.js')
    @endpush
@endif
