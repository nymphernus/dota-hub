@extends('layouts.app')

@section('title', $pageTitle ?? (__('ui.match').' #'.$match['match_id'].' - DOTAHUB'))
@section('description', __('ui.match_details_title', ['id' => $match['match_id']]))

@php
    $gameMode = (int) ($match['game_mode'] ?? 0);
    $gameModeLabel = in_array($gameMode, [1, 2, 4, 5, 16, 18, 22, 23], true)
        ? __('ui.mode_'.$gameMode)
        : null;
    $isRankedMatch = (int) ($match['lobby_type'] ?? 0) === 7 || $gameMode === 22;
    $radiantWin = $match['radiant_win'];
    $winner = $radiantWin ? 'Radiant' : 'Dire';
    $startedAt = $match['started_at'] ? \Carbon\CarbonImmutable::parse($match['started_at']) : null;
    $allPlayers = collect($match['players']['radiant'] ?? [])->merge($match['players']['dire'] ?? []);
    $showTowerDamage = $allPlayers->contains(fn (array $matchPlayer) => (int) ($matchPlayer['tower_damage'] ?? 0) > 0);
    $showWards = $allPlayers->contains(fn (array $matchPlayer) => (int) ($matchPlayer['obs_placed'] ?? 0) > 0 || (int) ($matchPlayer['sen_placed'] ?? 0) > 0);
    $showPurchases = $allPlayers->contains(fn (array $matchPlayer) => ! empty($matchPlayer['purchases']));
    $showItems = $allPlayers->contains(fn (array $matchPlayer) => ! empty($matchPlayer['neutral_item']) || ! empty($matchPlayer['aghanims_shard']) || ! empty($matchPlayer['aghanims_scepter'])) || $showPurchases;
    $columnCount = 7 + ($showTowerDamage ? 1 : 0) + ($showWards ? 1 : 0) + ($showItems ? 1 : 0);

    $laneBuckets = [1 => ['sum' => 0.0, 'count' => 0], 2 => ['sum' => 0.0, 'count' => 0], 3 => ['sum' => 0.0, 'count' => 0]];
    foreach ($allPlayers as $matchPlayer) {
        $lane = (int) ($matchPlayer['role']['lane_role'] ?? 0);
        $efficiency = $matchPlayer['lane_efficiency'] ?? null;

        if (! isset($laneBuckets[$lane]) || $efficiency === null) {
            continue;
        }

        $laneBuckets[$lane]['sum'] += (float) $efficiency;
        $laneBuckets[$lane]['count']++;
    }
    $laneStats = [];
    foreach ($laneBuckets as $lane => $bucket) {
        if ($bucket['count'] === 0) {
            continue;
        }

        $laneStats[] = [
            'label' => __('ui.role_'.$lane),
            'value' => round($bucket['sum'] / $bucket['count'], 1),
        ];
    }

    if ($laneStats === [] && ! empty($match['lane_efficiency_by_team'])) {
        foreach ([1, 2, 3] as $lane) {
            $share = $match['lane_efficiency_by_team'][$lane] ?? null;

            if (! is_array($share)) {
                continue;
            }

            $radiantShare = round((float) ($share['radiant'] ?? 0), 1);
            $direShare = round((float) ($share['dire'] ?? 0), 1);
            $leader = $radiantShare >= $direShare ? __('ui.radiant_short') : __('ui.dire_short');

            $laneStats[] = [
                'label' => __('ui.role_'.$lane),
                'value' => max($radiantShare, $direShare),
                'hint' => $leader.' '.number_format(max($radiantShare, $direShare), 1, '.', '').'% · '
                    .number_format(min($radiantShare, $direShare), 1, '.', '').'%',
            ];
        }
    }
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
        foreach ($team['players'] as $index => $matchPlayer) {
            $teams[$teamKey]['players'][$index]['view_index'] = $viewIndex++;
        }
    }
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 lg:px-8">
        <section class="relative py-2">
            <div class="relative px-0 py-1">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'badge',
                                'border-green-500/25 bg-green-500/10 text-green-500' => $radiantWin,
                                'border-red-500/25 bg-red-500/10 text-red-500' => ! $radiantWin,
                            ])>
                                {{ __('ui.win_of_team', ['team' => $winner]) }}
                            </span>
                            @if ($match['is_ranked'])
                                <span class="badge border-accent-500/25 bg-accent-500/10 text-accent-300">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3z" stroke-linejoin="round" /></svg>
                                    Ranked
                                </span>
                            @elseif ($match['lobby_type_label'])
                                <span class="badge border-line bg-ink-900/50 text-mist-300">{{ $match['lobby_type_label'] }}</span>
                            @endif
                            @if ($gameModeLabel)
                                <span class="badge border-line bg-ink-900/50 text-mist-300">{{ $gameModeLabel }}</span>
                            @endif
                        </div>

                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
                            {{ __('ui.match') }} <span class="font-mono text-2xl text-mist-300 sm:text-3xl">#{{ $match['match_id'] }}</span>
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
                        <div class="rounded-none border border-green-500/15 bg-green-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-green-500/70">Radiant</p>
                            <p class="text-xl font-semibold text-green-500">{{ $match['radiant_score'] }}</p>
                        </div>
                        <span class="text-xs font-bold text-mist-500">:</span>
                        <div class="rounded-none border border-red-500/15 bg-red-500/[0.07] px-4 py-2 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-red-500/70">Dire</p>
                            <p class="text-xl font-semibold text-red-500">{{ $match['dire_score'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($match['net_worth_chart']['has_data'] ?? false)
            <section class="card mt-4 p-4" x-data="netWorthChart(@js($match['net_worth_chart']))">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="section-heading">{{ __('ui.net_worth_by_minute') }}</h2>
                    <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $match['duration_label'] }}</span>
                </div>
                <div class="h-64 w-full">
                    <canvas id="netWorthChart" aria-label="{{ __('ui.net_worth_by_minute') }}"></canvas>
                </div>
            </section>
        @else
            <section class="card mt-4 p-4 text-xs text-mist-500">{{ __('ui.no_net_worth_data') }}</section>
        @endif

        <section class="card mt-4 overflow-hidden" aria-label="{{ __('ui.match_squads') }}">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-4">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.18em] text-mist-500">{{ __('ui.match_squads') }}</p>
                    <h2 class="section-heading">{{ __('ui.players_in_match') }}</h2>
                </div>
                <span class="badge text-mist-400">
                    <span class="text-green-500">{{ $match['radiant_score'] }}</span>
                    <span class="text-mist-500">:</span>
                    <span class="text-red-500">{{ $match['dire_score'] }}</span>
                </span>
            </div>

            <div>
                <table class="w-full text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-2 py-1 font-semibold">{{ __('ui.player_hero_col') }}</th>
                            <th class="px-2 py-1 font-semibold">IMP</th>
                            <th class="px-2 py-1 font-semibold">K / D / A</th>
                            <th class="px-2 py-1 font-semibold">{{ __('ui.last_hits_short') }} / {{ __('ui.denies_short') }}</th>
                            <th class="px-2 py-1 font-semibold">GPM</th>
                            <th class="px-2 py-1 font-semibold">XPM</th>
                            <th class="px-2 py-1 font-semibold">{{ __('ui.damage') }}</th>
                            @if ($showTowerDamage)
                                <th class="px-2 py-1 font-semibold">{{ __('ui.towers') }}</th>
                            @endif
                            @if ($showWards)
                                <th class="px-2 py-1 font-semibold">{{ __('ui.wards') }}</th>
                            @endif
                            @if ($showItems)
                                <th class="px-2 py-1 text-right font-semibold">{{ __('ui.items') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="table-rows" x-data="{ openPlayer: null }">
                        @foreach ($teams as $teamKey => $team)
                            <tr @class([
                                'border-l-4',
                                'border-green-500 bg-green-500/10' => $teamKey === 'radiant',
                                'border-red-500 bg-red-500/10' => $teamKey === 'dire',
                            ])>
                                <td colspan="{{ $columnCount }}" class="px-2 py-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $teamKey === 'radiant' ? 'text-green-500' : 'text-red-500' }}">{{ __('ui.team') }} {{ $team['label'] }}</span>
                                        <span class="text-[10px] font-semibold {{ $team['won'] ? 'text-green-500' : 'text-red-500' }}">{{ $team['won'] ? __('ui.win_label') : __('ui.loss_label') }} · {{ $team['score'] }}</span>
                                    </div>
                                </td>
                            </tr>
                            @forelse ($team['players'] as $matchPlayer)
                                    @php
                                        $laneRole = (int) ($matchPlayer['role']['lane_role'] ?? 0);
                                        $roleLabel = $isRankedMatch && $laneRole >= 1 && $laneRole <= 5
                                            ? __('ui.role_'.$laneRole)
                                            : null;
                                        $roleSource = $matchPlayer['role']['source'] ?? null;
                                        $roleClasses = match ($laneRole) {
                                            1 => 'border-green-500/25 bg-green-500/10 text-green-500',
                                            2 => 'border-blue-500/25 bg-blue-500/10 text-blue-500',
                                            3 => 'border-red-500/25 bg-red-500/10 text-red-500',
                                            4 => 'border-purple-500/25 bg-purple-500/10 text-purple-500',
                                            5 => 'border-orange-500/25 bg-orange-500/10 text-orange-500',
                                            default => 'border-line bg-ink-900/50 text-mist-400',
                                        };
                                        $isCurrentPlayer = $playerId !== null
                                            && (int) ($matchPlayer['account_id'] ?? 0) === $playerId;
                                        $playerIndex = $matchPlayer['view_index'];
                                        $accountId = (int) ($matchPlayer['account_id'] ?? 0);
                                    @endphp
                                    <tr @class([
                                        'transition hover:bg-ink-900/30',
                                        'odd:bg-[#1c1e26] even:bg-[#111318]' => ! $isCurrentPlayer,
                                        'bg-[#2d313e] border-l-4 border-yellow-500' => $isCurrentPlayer,
                                    ])>
                                        <td class="px-2 py-1">
                                            <div class="flex items-center gap-3">
                                                <div class="h-9 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800  ">
                                                    @if ($matchPlayer['hero_image'])
                                                        <img src="{{ $matchPlayer['hero_image'] }}" alt="{{ $matchPlayer['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                                                    @else
                                                        <span class="grid h-full w-full place-items-center text-xs font-bold text-mist-500">
                                                            {{ mb_strtoupper(mb_substr($matchPlayer['hero_name'], 0, 1)) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    @if ($accountId > 0)
                                                        <a href="{{ route('player', ['accountId' => $accountId]) }}" class="block truncate text-lg font-bold text-mist-200 transition hover:text-accent-300">
                                                            {{ $matchPlayer['nickname'] ?: __('ui.player_hash', ['id' => $accountId]) }}
                                                        </a>
                                                    @else
                                                        <p class="block truncate text-lg font-bold text-mist-200">{{ $matchPlayer['nickname'] ?: __('ui.unknown_player') }}</p>
                                                    @endif
                                                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 truncate text-gray-400 text-sm">
                                                        <span>{{ $matchPlayer['hero_name'] }}</span>
                                                        @if (! empty($matchPlayer['rank_label']))
                                                            <span class="rounded bg-accent-500/10 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-accent-300" title="{{ __('ui.rank') }}">{{ $matchPlayer['rank_label'] }}</span>
                                                        @endif
                                                        @if ($matchPlayer['level'] > 0)
                                                            <span>{{ __('ui.lvl') }} {{ $matchPlayer['level'] }}</span>
                                                        @endif
                                                        @if ($roleLabel)
                                                            <span class="badge !px-1.5 !py-0.5 !text-[9px] {{ $roleClasses }}" title="{{ $roleLabel }}{{ $roleSource ? ' · '.$roleSource : '' }}">{{ $roleLabel }}</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ $matchPlayer['imp'] ?? '—' }}</td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums">
                                            <span class="text-green-500">{{ $matchPlayer['kills'] }}</span>
                                            <span class="text-mist-500/60">/</span>
                                            <span class="text-red-500">{{ $matchPlayer['deaths'] }}</span>
                                            <span class="text-mist-500/60">/</span>
                                            <span class="text-mist-400">{{ $matchPlayer['assists'] }}</span>
                                            <span class="ml-1.5 text-[10px] text-mist-500">({{ number_format($matchPlayer['kda'], 2, '.', '') }})</span>
                                        </td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">
                                            <span class="text-mist-200">{{ (int) ($matchPlayer['last_hits'] ?? 0) }}</span>
                                            <span class="text-mist-500/60">/</span>
                                            <span class="text-mist-500">{{ (int) ($matchPlayer['denies'] ?? 0) }}</span>
                                        </td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($matchPlayer['gpm'] ?? 0), 0, '.', ' ') }}</td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($matchPlayer['xpm'] ?? 0), 0, '.', ' ') }}</td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($matchPlayer['hero_damage'] ?? 0), 0, '.', ' ') }}</td>
                                        @if ($showTowerDamage)
                                            <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ isset($matchPlayer['tower_damage']) ? number_format((int) $matchPlayer['tower_damage'], 0, '.', ' ') : '—' }}</td>
                                        @endif
                                        @if ($showWards)
                                            <td class="px-2 py-1 text-[11px] tabular-nums text-mist-300">
                                                <span title="{{ __('ui.observer_ward') }}">O: {{ (int) ($matchPlayer['obs_placed'] ?? 0) }}</span>
                                                <span class="ml-2 text-mist-500" title="{{ __('ui.sentry_ward') }}">S: {{ (int) ($matchPlayer['sen_placed'] ?? 0) }}</span>
                                            </td>
                                        @endif
                                        @if ($showItems)
                                            @php
                                                $specialItems = [
                                                    ['meta' => $matchPlayer['neutral_item'] ?? null, 'label' => __('ui.neutral_item')],
                                                    ['meta' => $matchPlayer['aghanims_shard'] ?? null, 'label' => __('ui.aghanims_shard')],
                                                    ['meta' => $matchPlayer['aghanims_scepter'] ?? null, 'label' => __('ui.aghanims_scepter')],
                                                ];
                                            @endphp
                                            <td class="px-2 py-1 text-right">
                                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                                    @foreach ($specialItems as $special)
                                                        @if (is_array($special['meta']) && (int) ($special['meta']['id'] ?? 0) > 0)
                                                            @if (! empty($special['meta']['image']))
                                                                <img src="{{ $special['meta']['image'] }}" alt="{{ $special['label'] }}" title="{{ $special['label'] }}" class="h-8 w-8 object-contain" loading="lazy" referrerpolicy="no-referrer">
                                                            @else
                                                                <span class="badge border-line bg-ink-900/50 text-mist-400" title="{{ $special['label'] }}">{{ $special['label'] }}</span>
                                                            @endif
                                                        @endif
                                                    @endforeach
                                                    @if ($showPurchases && $matchPlayer['purchases'] !== [])
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center gap-1.5 rounded-none border border-line bg-ink-900/40 px-2.5 py-1.5 text-[11px] font-semibold text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-white"
                                                            @click="openPlayer = openPlayer === {{ $playerIndex }} ? null : {{ $playerIndex }}"
                                                            :aria-expanded="(openPlayer === {{ $playerIndex }}).toString()"
                                                            aria-controls="purchases-{{ $teamKey }}-{{ $playerIndex }}"
                                                        >
                                                            {{ __('ui.items') }}
                                                            <span class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[10px] text-mist-400">{{ count($matchPlayer['purchases']) }}</span>
                                                            <svg class="h-3 w-3 transition" :class="openPlayer === {{ $playerIndex }} ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>

                                    @if ($showPurchases && $matchPlayer['purchases'] !== [])
                                        <tr id="purchases-{{ $teamKey }}-{{ $playerIndex }}" x-show="openPlayer === {{ $playerIndex }}" x-cloak x-transition.opacity.duration.150ms class="bg-ink-950/60">
                                            <td colspan="{{ $columnCount }}" class="px-2 py-1">
                                                <div class="rounded-none bg-ink-900/70 p-3">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="label">{{ __('ui.items') }} · {{ $matchPlayer['hero_name'] }}</p>
                                                        <p class="text-[10px] text-mist-500">{{ $matchPlayer['purchase_log_count'] }} {{ __('ui.purchases') }}</p>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($matchPlayer['purchases'] as $item)
                                                            <div class="flex items-center gap-2 rounded-none bg-ink-900/30 p-2 pr-3" title="{{ $item['name'] }} · {{ $item['time_label'] }}">
                                                                <div class="grid h-8 w-10 shrink-0 place-items-center overflow-hidden rounded-none bg-ink-800  ">
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
                                    <td colspan="{{ $columnCount }}" class="px-4 py-8 text-center text-sm text-mist-400">{{ __('ui.no_player_data') }}</td>
                                </tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($laneStats !== [])
            <section class="card mt-4 p-4" aria-label="{{ __('ui.lane_efficiency') }}">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="section-heading">{{ __('ui.lane_efficiency') }}</h2>
                    <span class="badge border-line bg-ink-900/50 text-mist-400">{{ __('ui.match') }} #{{ $match['match_id'] }}</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($laneStats as $lane)
                        <div>
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <div>
                                    <span class="text-mist-300">{{ $lane['label'] }}</span>
                                    @if (! empty($lane['hint']))
                                        <span class="block text-[10px] text-mist-500">{{ $lane['hint'] }}</span>
                                    @endif
                                </div>
                                <span class="font-mono tabular-nums text-mist-200">{{ number_format($lane['value'], 1, '.', '') }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden bg-ink-900">
                                <div class="h-full bg-accent-400" style="width: {{ min(100, max(0, $lane['value'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
