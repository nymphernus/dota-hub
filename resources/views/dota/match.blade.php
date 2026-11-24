@extends('layouts.app')

@section('title', $pageTitle ?? (__('ui.match').' #'.$match['match_id'].' - DOTAHUB'))
@section('description', __('ui.match_details_title', ['id' => $match['match_id']]))

@php
    $profilePlayerId = request()->integer('player_id') ?: null;
    $radiantWin = $match['radiant_win'];
    $winner = $radiantWin ? 'Radiant' : 'Dire';
    $startedAt = $match['started_at'] ? \Carbon\CarbonImmutable::parse($match['started_at']) : null;
    $allPlayers = collect($match['players']['radiant'] ?? [])->merge($match['players']['dire'] ?? []);
    $profilePlayer = $profilePlayerId !== null
        ? $allPlayers->first(fn (array $player): bool => (int) ($player['account_id'] ?? 0) === $profilePlayerId)
        : null;
    $profileNickname = $profilePlayer['nickname'] ?? null;
    $showTowerDamage = $allPlayers->contains(fn (array $player) => (int) ($player['tower_damage'] ?? 0) > 0);
    $showHealing = $allPlayers->contains(fn (array $player) => (int) ($player['hero_healing'] ?? 0) > 0);
    $showPurchases = $allPlayers->contains(fn (array $player) => ! empty($player['purchases']));
    $showImpact = $allPlayers->contains(fn (array $player) => ($player['impact'] ?? null) !== null);
    $columnCount = 7 + ($showTowerDamage ? 1 : 0) + ($showHealing ? 1 : 0) + ($showPurchases ? 1 : 0);
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
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 lg:px-8">
        <section class="relative py-2">
            <div class="relative px-0 py-1">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge border-dota-500/25 bg-dota-500/10 text-dota-300">Dota 2</span>
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
                            @if ($match['game_mode_label'])
                                <span class="badge border-line bg-ink-900/50 text-mist-300">{{ $match['game_mode_label'] }}</span>
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
                            <th class="px-2 py-1 font-semibold">GPM</th>
                            <th class="px-2 py-1 font-semibold">XPM</th>
                            <th class="px-2 py-1 font-semibold">{{ __('ui.damage') }}</th>
                            @if ($showTowerDamage)
                                <th class="px-2 py-1 font-semibold">{{ __('ui.towers') }}</th>
                            @endif
                            @if ($showHealing)
                                <th class="px-2 py-1 font-semibold">HEAL</th>
                            @endif
                            @if ($showPurchases)
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
                            @forelse ($team['players'] as $player)
                                    @php
                                        $roleLabel = $player['role']['label'] ?? null;
                                        $roleName = $player['role']['name'] ?? null;
                                        $roleSource = $player['role']['source'] ?? null;
                                        $roleClasses = match ($roleLabel) {
                                            'Safe' => 'border-green-500/25 bg-green-500/10 text-green-500',
                                            'Mid' => 'border-blue-500/25 bg-blue-500/10 text-blue-500',
                                            'Off' => 'border-red-500/25 bg-red-500/10 text-red-500',
                                            'Soft' => 'border-purple-500/25 bg-purple-500/10 text-purple-500',
                                            'Hard' => 'border-orange-500/25 bg-orange-500/10 text-orange-500',
                                            default => 'border-line bg-ink-900/50 text-mist-400',
                                        };
                                        $isCurrentPlayer = $profilePlayerId !== null
                                            && (int) ($player['account_id'] ?? 0) === $profilePlayerId;
                                        $playerIndex = $player['view_index'];
                                        $accountId = (int) ($player['account_id'] ?? 0);
                                    @endphp
                                    <tr @class([
                                        'transition hover:bg-ink-900/30',
                                        'odd:bg-[#1c1e26] even:bg-[#111318]',
                                        'bg-[#2d313e]' => $isCurrentPlayer,
                                    ])>
                                        <td class="px-2 py-1">
                                            <div class="flex items-center gap-3">
                                                <div class="h-9 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800  ">
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
                                                        <a href="{{ route('player', ['accountId' => $accountId]) }}" class="block truncate text-lg font-bold text-mist-200 transition hover:text-accent-300">
                                                            {{ $player['nickname'] ?: __('ui.player_hash', ['id' => $accountId]) }}
                                                        </a>
                                                    @else
                                                        <p class="block truncate text-lg font-bold text-mist-200">{{ $player['nickname'] ?: __('ui.unknown_player') }}</p>
                                                    @endif
                                                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 truncate text-gray-400 text-sm">
                                                        <span>{{ $player['hero_name'] }}</span>
                                                        @if (! empty($player['rank_label']))
                                                            <span class="rounded bg-accent-500/10 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-accent-300" title="{{ __('ui.rank') }}">{{ $player['rank_label'] }}</span>
                                                        @endif
                                                        @if ($player['level'] > 0)
                                                            <span>{{ __('ui.lvl') }} {{ $player['level'] }}</span>
                                                        @endif
                                                        @if ($roleLabel)
                                                            <span class="badge !px-1.5 !py-0.5 !text-[9px] {{ $roleClasses }}" title="{{ $roleName }}{{ $roleSource ? ' · '.$roleSource : '' }}">{{ $roleLabel }}</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">
                                            {{ $showImpact && ($player['impact'] ?? null) !== null ? number_format($player['impact'], 1, '.', '') : '—' }}
                                        </td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums">
                                            <span class="text-green-500">{{ $player['kills'] }}</span>
                                            <span class="text-mist-500/60">/</span>
                                            <span class="text-red-500">{{ $player['deaths'] }}</span>
                                            <span class="text-mist-500/60">/</span>
                                            <span class="text-mist-400">{{ $player['assists'] }}</span>
                                            <span class="ml-1.5 text-[10px] text-mist-500">({{ number_format($player['kda'], 2, '.', '') }})</span>
                                        </td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($player['gpm'] ?? 0), 0, '.', ' ') }}</td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($player['xpm'] ?? 0), 0, '.', ' ') }}</td>
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format((int) ($player['hero_damage'] ?? 0), 0, '.', ' ') }}</td>
                                        @if ($showTowerDamage)
                                            <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ isset($player['tower_damage']) ? number_format((int) $player['tower_damage'], 0, '.', ' ') : '—' }}</td>
                                        @endif
                                        @if ($showHealing)
                                            <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ isset($player['hero_healing']) ? number_format((int) $player['hero_healing'], 0, '.', ' ') : '—' }}</td>
                                        @endif
                                        @if ($showPurchases)
                                            <td class="px-2 py-1 text-right">
                                                @if ($player['purchases'] !== [])
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1.5 rounded-none border border-line bg-ink-900/40 px-2.5 py-1.5 text-[11px] font-semibold text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-white"
                                                        @click="openPlayer = openPlayer === {{ $playerIndex }} ? null : {{ $playerIndex }}"
                                                        :aria-expanded="(openPlayer === {{ $playerIndex }}).toString()"
                                                        aria-controls="purchases-{{ $teamKey }}-{{ $playerIndex }}"
                                                    >
                                                        {{ __('ui.items') }}
                                                        <span class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[10px] text-mist-400">{{ count($player['purchases']) }}</span>
                                                        <svg class="h-3 w-3 transition" :class="openPlayer === {{ $playerIndex }} ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                    </button>
                                                @else
                                                    <span class="text-[11px] text-mist-500">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>

                                    @if ($showPurchases && $player['purchases'] !== [])
                                        <tr id="purchases-{{ $teamKey }}-{{ $playerIndex }}" x-show="openPlayer === {{ $playerIndex }}" x-cloak x-transition.opacity.duration.150ms class="bg-ink-950/60">
                                            <td colspan="{{ $columnCount }}" class="px-2 py-1">
                                                <div class="rounded-none bg-ink-900/70 p-3">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="label">{{ __('ui.items') }} · {{ $player['hero_name'] }}</p>
                                                        <p class="text-[10px] text-mist-500">{{ $player['purchase_log_count'] }} {{ __('ui.purchases') }}</p>
                                                    </div>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($player['purchases'] as $item)
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

        <p class="mt-4 text-[11px] text-mist-500">{{ __('ui.match_cache_note') }}</p>
    </div>
@endsection
