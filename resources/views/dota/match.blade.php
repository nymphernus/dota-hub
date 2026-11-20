@extends('layouts.app')

@section('title', $pageTitle ?? ('Матч '.$match['match_id'].' - DotaHub'))
@section('description', 'Детальная статистика матча Dota 2 #'.$match['match_id'].': счёт, роли, закуп, длительность, герои, K/D/A, GPM, XPM и урон игроков Radiant и Dire.')

@php
    $profilePlayerId = request()->integer('player_id') ?: null;
    $radiantWin = $match['radiant_win'];
    $winner = $radiantWin ? 'Radiant' : 'Dire';
    $startedAt = $match['started_at'] ? \Carbon\CarbonImmutable::parse($match['started_at']) : null;
    $allPlayers = collect($match['players']['radiant'] ?? [])->merge($match['players']['dire'] ?? []);
    $showTowerDamage = $allPlayers->contains(fn (array $player) => (int) ($player['tower_damage'] ?? 0) > 0);
    $showHealing = $allPlayers->contains(fn (array $player) => (int) ($player['hero_healing'] ?? 0) > 0);
    $picksBans = collect($match['picks_bans'] ?? []);
    $columnCount = 7 + ($showTowerDamage ? 1 : 0) + ($showHealing ? 1 : 0);
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

    // One flat scoreboard: both teams live in the same table. Row indexes stay
    // unique across teams so the purchase toggles never collide.
    $viewIndex = 0;

    foreach ($teams as $teamKey => $team) {
        foreach ($team['players'] as $index => $player) {
            $teams[$teamKey]['players'][$index]['view_index'] = $viewIndex++;
        }
    }
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-mist-200">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-mist-400">Dota 2</span>
            @if ($profilePlayerId)
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
                <a href="{{ route('dota.player', ['accountId' => $profilePlayerId]) }}" class="transition hover:text-mist-200">Профиль игрока</a>
            @endif
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="truncate text-slate-300">Матч #{{ $match['match_id'] }}</span>
        </nav>

        <section class="card relative overflow-hidden">
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
                                <span class="badge border-line bg-ink-900/50 text-mist-300">{{ $match['lobby_type_label'] }}</span>
                            @endif
                            @if ($match['game_mode_label'])
                                <span class="badge border-line bg-ink-900/50 text-mist-300">{{ $match['game_mode_label'] }}</span>
                            @endif
                        </div>

                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
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
                        <div class="rounded-lg border border-emerald-500/15 bg-emerald-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-emerald-300/70">Radiant</p>
                            <p class="text-xl font-semibold text-emerald-300">{{ $match['radiant_score'] }}</p>
                        </div>
                        <span class="text-xs font-bold text-mist-500">:</span>
                        <div class="rounded-lg border border-rose-500/15 bg-rose-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-rose-300/70">Dire</p>
                            <p class="text-xl font-semibold text-rose-300">{{ $match['dire_score'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($picksBans->isNotEmpty())
            <section class="mt-4 card p-4 sm:p-5" aria-label="Пики и баны">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-lg font-bold text-mist-200">Пики и баны</h2>
                    <span class="text-[11px] text-mist-500">Порядок драфта</span>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach (['Radiant' => 0, 'Dire' => 1] as $teamLabel => $teamId)
                        @php
                            $teamEntries = $picksBans->where('team', $teamId);
                            $picks = $teamEntries->where('is_pick', true)->values();
                            $bans = $teamEntries->where('is_pick', false)->values();
                        @endphp
                        <div class="rounded-lg bg-ink-900/30 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] {{ $teamId === 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $teamLabel }}</p>

                            @if ($picks->isNotEmpty())
                                <div class="mt-3">
                                    <p class="text-[10px] uppercase tracking-[0.14em] text-mist-500">Пики</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($picks as $entry)
                                            <div class="flex items-center gap-2 rounded-lg bg-ink-900/60 px-2 py-1.5" title="{{ $entry['hero_name'] }}">
                                                <span class="h-7 w-12 overflow-hidden rounded bg-ink-800  ">
                                                    @if (! empty($entry['hero_image']))
                                                        <img src="{{ $entry['hero_image'] }}" alt="{{ $entry['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                    @endif
                                                </span>
                                                <span class="max-w-28 truncate text-[11px] font-semibold text-mist-200">{{ $entry['hero_name'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($bans->isNotEmpty())
                                <div class="mt-3">
                                    <p class="text-[10px] uppercase tracking-[0.14em] text-mist-500">Баны</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($bans as $entry)
                                            <div class="flex items-center gap-2 rounded-lg border border-rose-500/15 bg-rose-500/[0.05] px-2 py-1.5 opacity-80" title="{{ $entry['hero_name'] }}">
                                                <span class="h-7 w-12 overflow-hidden rounded bg-ink-800   grayscale">
                                                    @if (! empty($entry['hero_image']))
                                                        <img src="{{ $entry['hero_image'] }}" alt="{{ $entry['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                    @endif
                                                </span>
                                                <span class="max-w-28 truncate text-[11px] font-semibold text-mist-300">{{ $entry['hero_name'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($picks->isEmpty() && $bans->isEmpty())
                                <p class="mt-3 text-[11px] text-mist-500">Нет данных по драфту.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card mt-4 overflow-hidden" aria-label="Составы матча">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-4 sm:px-5">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.18em] text-mist-500">Составы</p>
                    <h2 class="text-lg font-bold text-mist-200">Игроки матча</h2>
                </div>
                <span class="badge text-mist-400">
                    <span class="text-emerald-300">{{ $match['radiant_score'] }}</span>
                    <span class="text-mist-500">:</span>
                    <span class="text-rose-300">{{ $match['dire_score'] }}</span>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-4 py-3 font-semibold sm:px-5">Игрок / герой</th>
                            <th class="px-4 py-3 font-semibold">Роль</th>
                            <th class="px-4 py-3 font-semibold">K / D / A</th>
                            <th class="px-4 py-3 font-semibold">GPM</th>
                            <th class="px-4 py-3 font-semibold">XPM</th>
                            <th class="px-4 py-3 font-semibold">Урон</th>
                            @if ($showTowerDamage)
                                <th class="px-4 py-3 font-semibold">Башни</th>
                            @endif
                            @if ($showHealing)
                                <th class="px-4 py-3 font-semibold">Лечение</th>
                            @endif
                            <th class="px-4 py-3 text-right font-semibold sm:px-5">Закуп</th>
                        </tr>
                    </thead>
                    <tbody class="table-rows" x-data="{ openPlayer: null }">
                        @foreach ($teams as $teamKey => $team)
                            <tr class="bg-ink-900/60">
                                <td colspan="{{ $columnCount }}" class="px-4 py-2 sm:px-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $teamKey === 'radiant' ? 'text-emerald-300' : 'text-rose-300' }}">Команда {{ $team['label'] }}</span>
                                        <span class="text-[10px] font-semibold {{ $team['won'] ? 'text-emerald-300' : 'text-rose-300' }}">{{ $team['won'] ? 'Победа' : 'Поражение' }} · {{ $team['score'] }}</span>
                                    </div>
                                </td>
                            </tr>
                            @forelse ($team['players'] as $player)
                                    @php
                                        $roleLabel = $player['role']['label'] ?? null;
                                        $roleName = $player['role']['name'] ?? null;
                                        $roleSource = $player['role']['source'] ?? null;
                                        $roleClasses = match ($roleLabel) {
                                            'Safe' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
                                            'Mid' => 'border-sky-500/25 bg-sky-500/10 text-sky-300',
                                            'Off' => 'border-amber-500/25 bg-amber-500/10 text-amber-300',
                                            'Soft' => 'border-orange-500/25 bg-orange-500/10 text-orange-300',
                                            'Hard' => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
                                            default => 'border-line bg-ink-900/50 text-mist-400',
                                        };
                                        $isCurrentPlayer = $profilePlayerId !== null
                                            && (int) ($player['account_id'] ?? 0) === $profilePlayerId;
                                        $playerIndex = $player['view_index'];
                                        $accountId = (int) ($player['account_id'] ?? 0);
                                    @endphp
                                    <tr @class([
                                        'transition hover:bg-ink-900/30',
                                        'bg-ink-700/60' => $isCurrentPlayer,
                                    ])>
                                        <td class="px-4 py-3 sm:px-5">
                                            <div class="flex items-center gap-3">
                                                <div class="h-9 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800  ">
                                                    @if ($player['hero_image'])
                                                        <img src="{{ $player['hero_image'] }}" alt="{{ $player['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                    @else
                                                        <span class="grid h-full w-full place-items-center text-xs font-bold text-mist-500">
                                                            {{ mb_strtoupper(mb_substr($player['hero_name'], 0, 1)) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    @if ($accountId > 0)
                                                        <a href="{{ route('dota.player', ['accountId' => $accountId]) }}" class="block truncate text-lg font-bold text-mist-200 transition hover:text-gold-200">
                                                            {{ $player['nickname'] ?: 'Игрок '.$accountId }}
                                                        </a>
                                                    @else
                                                        <p class="block truncate text-lg font-bold text-mist-200">{{ $player['nickname'] ?: 'Неизвестный игрок' }}</p>
                                                    @endif
                                                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 truncate text-gray-400 text-sm">
                                                        <span>{{ $player['hero_name'] }}</span>
                                                        @if (! empty($player['rank_label']))
                                                            <span class="rounded bg-amber-500/10 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-amber-300" title="Ранг">{{ $player['rank_label'] }}</span>
                                                        @endif
                                                        @if ($player['level'] > 0)
                                                            <span>ур. {{ $player['level'] }}</span>
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
                                                    <p class="mt-0.5 text-[10px] font-semibold text-accent-300/80" title="STRATZ impact">IMP {{ number_format($player['impact'], 1, '.', '') }}</p>
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
                                        <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($player['gpm'] ?? 0), 0, '.', ' ') }}</td>
                                        <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($player['xpm'] ?? 0), 0, '.', ' ') }}</td>
                                        <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($player['hero_damage'] ?? 0), 0, '.', ' ') }}</td>
                                        @if ($showTowerDamage)
                                            <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ isset($player['tower_damage']) ? number_format((int) $player['tower_damage'], 0, '.', ' ') : '—' }}</td>
                                        @endif
                                        @if ($showHealing)
                                            <td class="px-4 py-3 font-mono text-[13px] tabular-nums text-mist-300">{{ isset($player['hero_healing']) ? number_format((int) $player['hero_healing'], 0, '.', ' ') : '—' }}</td>
                                        @endif
                                        <td class="px-4 py-3 text-right sm:px-5">
                                            @if ($player['purchases'] !== [])
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-ink-900/40 px-2.5 py-1.5 text-[11px] font-semibold text-mist-300 transition hover:border-gold-500/30 hover:bg-gold-500/10 hover:text-gold-200"
                                                    @click="openPlayer = openPlayer === {{ $playerIndex }} ? null : {{ $playerIndex }}"
                                                    :aria-expanded="(openPlayer === {{ $playerIndex }}).toString()"
                                                    aria-controls="purchases-{{ $teamKey }}-{{ $playerIndex }}"
                                                >
                                                    Закуп
                                                    <span class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[10px] text-mist-400">{{ count($player['purchases']) }}</span>
                                                    <svg class="h-3 w-3 transition" :class="openPlayer === {{ $playerIndex }} ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                </button>
                                            @else
                                                <span class="text-[11px] text-mist-500">—</span>
                                            @endif
                                        </td>
                                    </tr>

                                    @if ($player['purchases'] !== [])
                                        <tr id="purchases-{{ $teamKey }}-{{ $playerIndex }}" x-show="openPlayer === {{ $playerIndex }}" x-cloak x-transition.opacity.duration.150ms class="bg-ink-950/60">
                                            <td colspan="{{ $columnCount }}" class="px-4 pb-4 pt-1 sm:px-5">
                                                <div class="rounded-lg bg-ink-900/70 p-3">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="label">Закуп · {{ $player['hero_name'] }}</p>
                                                        <p class="text-[10px] text-mist-500">{{ $player['purchase_log_count'] }} покупок · показаны ключевые предметы</p>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($player['purchases'] as $item)
                                                            <div class="flex items-center gap-2 rounded-lg bg-ink-900/30 p-2 pr-3" title="{{ $item['name'] }} · {{ $item['time_label'] }}">
                                                                <div class="grid h-8 w-10 shrink-0 place-items-center overflow-hidden rounded-md bg-ink-800  ">
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
                            @empty
                                <tr>
                                    <td colspan="{{ $columnCount }}" class="px-4 py-8 text-center text-sm text-mist-400">Нет данных по игрокам.</td>
                                </tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-ink-900/60 px-4 py-3 text-[11px] text-mist-500">
            <span>Данные OpenDota · кэш матча 1 час</span>
            <div class="flex items-center gap-3">
                @if ($profilePlayerId)
                    <a href="{{ route('dota.player', ['accountId' => $profilePlayerId]) }}" class="inline-flex items-center gap-1 font-semibold text-gold-300 transition hover:text-gold-200">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Вернуться к профилю
                    </a>
                @endif
                <a href="{{ route('home') }}" class="font-semibold text-mist-400 transition hover:text-mist-200">На главную</a>
            </div>
        </div>
    </div>
@endsection
