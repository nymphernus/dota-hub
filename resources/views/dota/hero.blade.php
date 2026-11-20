@extends('layouts.app')

@section('title', $pageTitle ?? ($hero['name'].' - '.$player['nickname'].' - DotaHub'))
@section('description', 'Статистика '.$player['nickname'].' на герое '.$hero['name'].': винрейт, KDA, GPM, XPM, бенчмарки OpenDota и последние матчи.')

@php
    $backUrl = route('dota.player', ['accountId' => $player['account_id']]);
    $toneClasses = [
        'emerald' => [
            'badge' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
            'text' => 'text-emerald-300',
            'marker' => 'bg-emerald-300 ',
        ],
        'sky' => [
            'badge' => 'border-sky-500/25 bg-sky-500/10 text-sky-300',
            'text' => 'text-sky-300',
            'marker' => 'bg-sky-300 ',
        ],
        'amber' => [
            'badge' => 'border-amber-500/25 bg-amber-500/10 text-amber-300',
            'text' => 'text-amber-300',
            'marker' => 'bg-amber-300 ',
        ],
        'orange' => [
            'badge' => 'border-orange-500/25 bg-orange-500/10 text-orange-300',
            'text' => 'text-orange-300',
            'marker' => 'bg-orange-300 ',
        ],
        'rose' => [
            'badge' => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
            'text' => 'text-rose-300',
            'marker' => 'bg-rose-300 ',
        ],
    ];
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-sm text-[#8b94a5]" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $backUrl }}" class="transition hover:text-white">{{ $player['nickname'] }}</a>
            <span aria-hidden="true">/</span>
            <span class="truncate font-medium text-white" aria-current="page">{{ $hero['name'] }}</span>
        </nav>

        <section class="card relative overflow-hidden">
            @if ($hero['image'])
                <img src="{{ $hero['image'] }}" alt="" class="pointer-events-none absolute -right-10 -top-8 h-56 w-auto scale-110 object-cover opacity-20 blur-[1px]" aria-hidden="true">
            @endif
            <div class="relative px-4 pb-5 pt-10 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="relative h-24 w-40 shrink-0 overflow-hidden rounded-none bg-ink-800   ">
                            @if ($hero['image'])
                                <img src="{{ $hero['image'] }}" alt="{{ $hero['name'] }}" class="h-full w-full object-cover" loading="eager">
                            @else
                                <span class="grid h-full w-full place-items-center text-3xl font-semibold text-mist-500">
                                    {{ mb_strtoupper(mb_substr($hero['name'], 0, 1)) }}
                                </span>
                            @endif
                        </div>

                        <div class="min-w-0 pb-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge border-dota-500/25 bg-dota-500/10 text-dota-300">Герой</span>
                                <span class="badge border-line bg-ink-900/50 text-mist-400">Последние 100 игр</span>
                            </div>

                            <h1 class="mt-2 truncate text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">{{ $hero['name'] }}</h1>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-mist-400">
                                <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 transition hover:text-mist-200">
                                    @if ($player['avatar'])
                                        <img src="{{ $player['avatar'] }}" alt="" class="h-5 w-5 rounded-none object-cover  " loading="lazy">
                                    @endif
                                    <span class="font-semibold text-mist-300">{{ $player['nickname'] }}</span>
                                    <span class="font-mono text-mist-500">ID {{ $player['account_id'] }}</span>
                                </a>
                                <span class="inline-flex items-center gap-1 text-mist-500">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" /></svg>
                                    Обновлено {{ \Carbon\CarbonImmutable::parse($cachedAt)->diffForHumans(short: true) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <a href="{{ $backUrl }}" class="btn-ghost !py-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        К профилю
                    </a>
                </div>
            </div>
        </section>

        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat
                label="Матчи"
                :value="number_format($summary['games'], 0, '.', ' ')"
                hint="Последние 100 игр на герое"
            />
            <x-stat
                label="Победы / Поражения"
                :value="number_format($summary['wins'], 0, '.', ' ').' / '.number_format($summary['losses'], 0, '.', ' ')"
                hint="Результаты в выборке"
            />
            <x-stat
                label="Винрейт"
                :value="$summary['winrate'] !== null ? number_format($summary['winrate'], 1, '.', '').'%' : '—'"
                :hint="number_format($summary['wins'], 0, '.', ' ').' побед из '.number_format($summary['games'], 0, '.', ' ').' матчей'"
            />
            <x-stat
                label="Средний KDA"
                :value="$summary['kda'] !== null ? number_format($summary['kda'], 2, '.', '') : '—'"
                :hint="$summary['kills'] !== null ? $summary['kills'].' / '.$summary['deaths'].' / '.$summary['assists'].' в среднем' : 'Недостаточно данных'"
            />
        </div>

        <section class="mt-4 card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-4 sm:px-5">
                <div>
                    <h2 class="section-heading">Бенчмарки</h2>
                    <p class="mt-1 text-[11px] leading-relaxed text-mist-500">
                        Перцентили OpenDota по последним матчам. Чем правее маркер, тем сильнее показатель относительно других игроков.
                    </p>
                </div>
                <span class="badge border-gold-500/25 bg-gold-500/10 text-gold-300">Dota Plus style</span>
            </div>

            @if (collect($benchmarks)->every(fn (array $benchmark) => ! $benchmark['available']))
                <div class="p-8 text-center">
                    <p class="text-sm text-mist-400">Бенчмарки для этого героя пока недоступны. OpenDota не вернула распределение.</p>
                </div>
            @else
                <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-3">
                    @foreach ($benchmarks as $benchmark)
                        @php
                            $tone = $toneClasses[$benchmark['tone'] ?? 'rose'] ?? $toneClasses['rose'];
                            $markerLeft = min(99, max(1, (float) ($benchmark['percentile'] ?? 0) * 100));
                        @endphp

                        <div class="rounded-none border border-line bg-ink-950/50 p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-mist-400">{{ $benchmark['label'] }}</p>
                                    <p class="mt-1 text-[10px] text-mist-500">
                                        {{ $benchmark['hint'] }}
                                        @if (in_array($benchmark['key'], ['gpm', 'xpm'], true) && ($summary[$benchmark['key'].'_games'] ?? 0) > 0)
                                            <span class="text-mist-500/70">· по {{ $summary[$benchmark['key'].'_games'] }} матчам</span>
                                        @endif
                                    </p>
                                </div>
                                @if ($benchmark['available'])
                                    <span class="badge {{ $tone['badge'] }}">{{ $benchmark['rank_label'] }}</span>
                                @else
                                    <span class="badge border-line bg-ink-900/50 text-mist-400">Нет данных</span>
                                @endif
                            </div>

                            <div class="mt-4 flex items-end justify-between gap-3">
                                <p class="text-3xl font-semibold tracking-tight {{ $benchmark['available'] ? $tone['text'] : 'text-mist-500' }}">
                                    {{ $benchmark['value_label'] }}
                                </p>
                                @if ($benchmark['available'])
                                    <p class="pb-1 text-right text-[10px] leading-tight text-mist-500">
                                        Сильнее<br><span class="font-mono text-sm font-bold text-mist-200">{{ $benchmark['percentile_label'] }}%</span> игроков
                                    </p>
                                @endif
                            </div>

                            @if ($benchmark['available'])
                                <div class="mt-5">
                                    <div class="relative h-2 rounded-none bg-line">
                                        <span
                                            class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-none border-2 border-ink-950 {{ $tone['marker'] }}"
                                            style="left: {{ number_format($markerLeft, 1, '.', '') }}%"
                                            aria-hidden="true"
                                        ></span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[9px] uppercase tracking-wider text-mist-500">
                                        <span>Ниже</span>
                                        <span>Среднее</span>
                                        <span>Топ</span>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-2 border-t border-line pt-3 text-[10px]">
                                    <div>
                                        <p class="text-mist-500">Среднее</p>
                                        <p class="mt-0.5 font-mono font-semibold text-mist-300">{{ $benchmark['average_label'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-mist-500">Топ 10%</p>
                                        <p class="mt-0.5 font-mono font-semibold text-mist-300">{{ $benchmark['elite_label'] }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mt-4 card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 sm:px-5">
                <h2 class="section-heading">История матчей</h2>
                <div class="flex items-center gap-3 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 text-emerald-300"><span class="h-2 w-2 rounded-none bg-emerald-400"></span> Победа</span>
                    <span class="inline-flex items-center gap-1.5 text-rose-300"><span class="h-2 w-2 rounded-none bg-rose-400"></span> Поражение</span>
                </div>
            </div>

            @if ($matches === [])
                <div class="p-10 text-center">
                    <p class="text-sm text-mist-400">У игрока нет публичных матчей на этом герое.</p>
                    <a href="{{ $backUrl }}" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-gold-300 transition hover:text-gold-200">
                        Вернуться в профиль
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-2 py-1 text-left font-semibold sm:px-3">Результат</th>
                                <th class="px-2 py-1 text-left font-semibold">K / D / A</th>
                                <th class="px-2 py-1 text-left font-semibold">KDA</th>
                                <th class="px-2 py-1 text-left font-semibold">GPM</th>
                                <th class="px-2 py-1 text-left font-semibold">XPM</th>
                                <th class="px-2 py-1 text-left font-semibold">Нетворс</th>
                                <th class="px-2 py-1 text-left font-semibold">Урон</th>
                                <th class="px-2 py-1 text-left font-semibold">Длительность</th>
                                <th class="px-2 py-1 text-right font-semibold sm:px-3">Дата</th>
                            </tr>
                        </thead>
                        <tbody class="table-rows">
                            @foreach ($matches as $match)
                                @php
                                    $matchUrl = $match['match_id']
                                        ? route('dota.match', ['matchId' => $match['match_id'], 'player_id' => $player['account_id']])
                                        : null;
                                @endphp
                                <tr
                                    @class([
                                        'transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60',
                                        'cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-gold-400' => $matchUrl,
                                    ])
                                    @if ($matchUrl)
                                        data-href="{{ $matchUrl }}"
                                        onclick="window.location.href = this.dataset.href"
                                        onkeydown="if (event.key === 'Enter') { window.location.href = this.dataset.href }"
                                        tabindex="0"
                                        aria-label="Открыть матч {{ $match['match_id'] }}"
                                    @endif
                                >
                                    <td @class([
                                        'px-2 py-1 sm:px-3',
                                        'border-l-4 border-green-500' => $match['win'],
                                        'border-l-4 border-red-500' => ! $match['win'],
                                    ])>
                                        <span @class([
                                            'badge !px-1.5 !py-0 !text-[9px]',
                                            'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $match['win'],
                                            'border-rose-500/25 bg-rose-500/10 text-rose-300' => ! $match['win'],
                                        ])>
                                            {{ $match['win'] ? 'WIN' : 'LOSS' }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums">
                                        <span class="text-emerald-300">{{ $match['kills'] }}</span>
                                        <span class="text-mist-500/60">/</span>
                                        <span class="text-rose-300">{{ $match['deaths'] }}</span>
                                        <span class="text-mist-500/60">/</span>
                                        <span class="text-sky-300">{{ $match['assists'] }}</span>
                                    </td>
                                    <td class="px-2 py-1 font-mono text-[12px] font-semibold tabular-nums text-mist-200">{{ number_format($match['kda'], 2, '.', '') }}</td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ $match['gpm'] ?: '—' }}</td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ $match['xpm'] ?: '—' }}</td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ ($match['net_worth'] ?? 0) > 0 ? number_format($match['net_worth'], 0, '.', ' ') : '—' }}</td>
                                    <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">{{ ($match['hero_damage'] ?? 0) > 0 ? number_format($match['hero_damage'], 0, '.', ' ') : '—' }}</td>
                                    <td class="px-2 py-1 font-mono text-[11px] text-mist-400">{{ $match['duration_label'] }}</td>
                                    <td class="px-2 py-1 text-right text-[10px] text-mist-400 sm:px-3">
                                        @if ($match['started_at'])
                                            <time datetime="{{ $match['started_at'] }}" title="{{ \Carbon\CarbonImmutable::parse($match['started_at'])->format('d.m.Y H:i') }}">
                                                {{ \Carbon\CarbonImmutable::parse($match['started_at'])->format('d.m.y') }}
                                            </time>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
