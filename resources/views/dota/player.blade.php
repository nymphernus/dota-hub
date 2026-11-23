@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.overview'))

@php
    $topHeroes = $player['top_heroes'] ?? array_slice($player['heroes'], 0, 5);
    $heroUsageTotal = array_sum(array_column($heroUsage, 'games'));
    $activityTotal = (int) ($player['games'] ?? 0);
@endphp

@section('profile-content')
        @php
            $steamAvailable = (bool) ($steamProfile['available'] ?? false);
            $steamPublic = $steamProfile['is_public'] ?? null;
            $dotaPublic = (bool) ($player['has_public_matches'] ?? false);
            $steamTone = match (true) {
                $steamPublic === true => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
                $steamPublic === false => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
                default => 'border-line bg-ink-900/50 text-mist-300',
            };
            $dotaTone = $dotaPublic
                ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300'
                : 'border-accent-500/25 bg-accent-500/10 text-accent-300';
        @endphp

        <div class="mt-4 flex flex-wrap items-center gap-2 rounded-none bg-ink-900/60 px-4 py-3 text-[11px]">
            <span class="uppercase tracking-[0.16em] text-mist-500">{{ __('ui.privacy') }}</span>
            <span @class(['badge', $steamTone])>Steam: {{ $steamProfile['visibility'] ?? __('ui.steam_unknown') }}</span>
            <span @class(['badge', $dotaTone])>{{ $dotaPublic ? __('ui.dota_public') : __('ui.dota_no_matches') }}</span>
            @if (! $steamAvailable)
                <span class="text-accent-300/80">{{ $steamProfile['error'] ?? __('ui.steam_unavailable') }}</span>
            @endif
            @if ($player['is_private'])
                <span class="badge border-accent-500/25 bg-accent-500/10 text-accent-300">{{ __('ui.hidden_data') }}</span>
            @endif
        </div>

        <x-player-metrics :player="$player" />

        @if ($player['is_private'])
            <div class="mt-4 flex items-start gap-3 border border-accent-500/25 bg-accent-500/[0.08] p-3 text-sm text-accent-300">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-accent-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 018 0v3" stroke-linecap="round" /></svg>
                <div>
                    <p class="font-semibold">{{ __('ui.profile_hidden') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-accent-300/70">{{ __('ui.profile_hidden_hint') }}</p>
                </div>
            </div>
        @endif

        <div class="mt-4 grid grid-cols-12 gap-4">
            <div class="col-span-12 min-w-0 space-y-4 lg:col-span-8">
                <section class="overflow-hidden border border-[#2d313e] bg-[#1c1e26] rounded-none">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#2d313e] px-3 py-2">
                        <h2 class="section-heading">{{ __('ui.recent_games') }}</h2>
                    </div>

                    @if ($recentMatches === [])
                        <div class="p-4 text-center text-xs text-mist-400">{{ __('ui.no_matches_found_hint') }}</div>
                    @else
                        <table class="w-full text-sm">
                                <thead class="table-head">
                                    <tr>
                                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.hero_mode') }}</th>
                                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.role') }}</th>
                                        <th class="px-2 py-1 text-left font-semibold">K / D / A</th>
                                        <th class="px-2 py-1 text-left font-semibold">GPM / XPM</th>
                                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.items') }}</th>
                                        <th class="px-2 py-1 text-right font-semibold">{{ __('ui.date') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="table-rows">
                                    @foreach ($recentMatches as $match)
                                        @include('dota.partials.match-row', ['match' => $match, 'accountId' => $player['account_id']])
                                    @endforeach
                                </tbody>
                        </table>
                    @endif
                </section>

                <section class="overflow-hidden border border-[#2d313e] bg-[#1c1e26] rounded-none">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#2d313e] px-3 py-2">
                        <h2 class="section-heading">{{ __('ui.most_played_heroes') }}</h2>
                        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ __('ui.all_time') }}</span>
                    </div>

                    @if ($topHeroes === [])
                        <div class="p-4 text-center text-xs text-mist-400">{{ __('ui.no_data') }}</div>
                    @else
                        <div class="grid gap-2 p-3 sm:grid-cols-2">
                            @foreach ($topHeroes as $index => $hero)
                                @php $heroUrl = route('dota.player.hero', ['accountId' => $player['account_id'], 'heroId' => $hero['hero_id']]); @endphp
                                <a href="{{ $heroUrl }}" class="group flex items-center gap-2 border border-line bg-ink-900/30 p-1.5 transition hover:border-accent-500/30 hover:bg-accent-500/[0.06]">
                                    <span class="w-4 text-center font-mono text-[10px] text-mist-500">{{ $index + 1 }}</span>
                                    <span class="h-8 w-14 shrink-0 overflow-hidden bg-ink-800">
                                        @if ($hero['image'])
                                            <img src="{{ $hero['image'] }}" alt="{{ $hero['name'] }}" class="h-full w-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-bold text-mist-200 transition group-hover:text-accent-300">{{ $hero['name'] }}</span>
                                        <span class="block text-[10px] text-mist-500">{{ $hero['games'] }} {{ __('ui.games') }}</span>
                                    </span>
                                    <span class="font-mono text-[12px] font-bold {{ ($hero['winrate'] ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">
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
                        <h2 class="section-heading">{{ __('ui.hero_wheel') }}</h2>
                        <span class="text-[10px] text-mist-500">{{ $heroUsageTotal }} {{ __('ui.matches_word') }}</span>
                    </div>

                    @include('dota.partials.hero-wheel', ['allHeroes' => $heroUsage, 'playerId' => $player['account_id'], 'heroUsageUnavailable' => $heroUsageUnavailable ?? false])
                </section>

                <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="section-heading">{{ __('ui.activity') }}</h2>
                        <span id="activity-total" class="text-[10px] text-mist-500">{{ number_format($activityTotal, 0, '.', ' ') }} {{ __('ui.games') }}</span>
                    </div>

                    <div class="mt-3 flex gap-2" aria-label="{{ __('ui.activity') }}">
                        <div id="heatmap-weekdays" class="grid w-7 shrink-0 grid-rows-7 gap-[3px] pt-4 text-[9px] font-semibold leading-none text-mist-500" aria-hidden="true">
                            @foreach ($activityHeatmap['weekdays'] as $weekday)
                                <span class="flex items-center justify-end">{{ $weekday }}</span>
                            @endforeach
                        </div>

                        <div class="min-w-0 flex-1">
                            <div id="heatmap-months" class="mb-1 grid gap-[3px] text-[9px] font-semibold text-mist-400" aria-hidden="true"></div>
                            <div
                                id="activity-heatmap"
                                class="grid w-full gap-[3px]"
                                role="grid"
                                aria-hidden="true"
                                data-match-url-template="{{ route('dota.player.matches', ['accountId' => $player['account_id'], 'date' => '__DATE__']) }}"
                            ></div>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[10px] text-mist-500">
                        <span>{{ __('ui.last_90_days') }}</span>
                        <span>{{ __('ui.today') }}</span>
                    </div>
                </section>

                @if (($lifetimeStats['total'] ?? 0) > 0)
                    <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="section-heading">{{ __('ui.lifetime_stats') }}</h2>
                                <p class="mt-0.5 text-[10px] text-mist-500">{{ __('ui.summary_by_matches', ['count' => number_format($lifetimeStats['total'], 0, '.', ' ')]) }}</p>
                            </div>
                            @php
                                $totalPlayHours = (int) floor(collect($history ?? [])->sum(fn ($m) => (int) ($m['duration'] ?? 0)) / 3600);
                            @endphp
                            <span class="text-[10px] text-mist-500">{{ $totalPlayHours }} {{ __('ui.hours_short') }}</span>
                        </div>

                        <div class="mt-2 space-y-3">
                            @foreach ($lifetimeStats['groups'] as $group)
                                <div class="min-w-0">
                                    <p class="section-heading mb-1">{{ $group['title'] }}</p>
                                    @if ($group['rows'] === [])
                                        <p class="px-2 py-1 text-[10px] text-mist-500">{{ __('ui.no_data') }}</p>
                                    @else
                                        <div class="overflow-hidden border border-line bg-ink-900/30">
                                            <table class="w-full text-[11px]">
                                                <thead class="table-head">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left font-semibold">{{ $group['key'] === 'mode' ? __('ui.mode') : __('ui.faction') }}</th>
                                                        <th class="px-2 py-1 text-right font-semibold">{{ __('ui.matches') }}</th>
                                                        <th class="px-2 py-1 text-right font-semibold">{{ __('ui.winrate') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="table-rows">
                                                    @foreach ($group['rows'] as $row)
                                                        <tr>
                                                            <td class="px-2 py-1 font-semibold text-mist-200">{{ $row['label'] }}</td>
                                                            <td class="px-2 py-1 text-right font-mono text-mist-200">{{ $row['games'] }}</td>
                                                            <td class="px-2 py-1 text-right font-mono {{ ($row['winrate'] ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">
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

                            @if (!empty($lifetimeStats['averages']))
                                <div class="min-w-0">
                                    <p class="section-heading mb-1">{{ __('ui.averages') }}</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="border border-line bg-ink-900/30 px-2 py-2 text-center">
                                            <div class="text-[10px] text-mist-500 uppercase">{{ __('ui.avg_kda') }}</div>
                                            <div class="text-sm font-semibold text-mist-200 font-mono">{{ $lifetimeStats['averages']['kda'] !== null ? number_format($lifetimeStats['averages']['kda'], 2, '.', '') : '—' }}</div>
                                        </div>
                                        <div class="border border-line bg-ink-900/30 px-2 py-2 text-center">
                                            <div class="text-[10px] text-mist-500 uppercase">{{ __('ui.avg_gpm') }}</div>
                                            <div class="text-sm font-semibold text-mist-200 font-mono">{{ $lifetimeStats['averages']['gpm'] !== null ? number_format($lifetimeStats['averages']['gpm'], 0, '.', ' ') : '—' }}</div>
                                        </div>
                                        <div class="border border-line bg-ink-900/30 px-2 py-2 text-center">
                                            <div class="text-[10px] text-mist-500 uppercase">{{ __('ui.avg_xpm') }}</div>
                                            <div class="text-sm font-semibold text-mist-200 font-mono">{{ $lifetimeStats['averages']['xpm'] !== null ? number_format($lifetimeStats['averages']['xpm'], 0, '.', ' ') : '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="border border-[#2d313e] bg-[#1c1e26] p-3 rounded-none">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="section-heading">{{ __('ui.allies') }}</h2>
                        <span class="text-[10px] text-mist-500">{{ min(5, count($player['peers'] ?? [])) }}</span>
                    </div>

                    @php $topPeers = array_slice($player['peers'] ?? [], 0, 5); @endphp
                    @if ($topPeers === [])
                        <div class="mt-2 border border-dashed border-line px-3 py-4 text-center text-[11px] text-mist-500">{{ __('ui.no_peers_data') }}</div>
                    @else
                        <div class="mt-2 space-y-1.5">
                            @foreach ($topPeers as $peer)
                                <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="group flex items-center gap-2 border border-line bg-ink-900/30 p-1.5 transition hover:border-accent-500/30 hover:bg-accent-500/[0.06]">
                                    <span class="h-7 w-7 shrink-0 overflow-hidden bg-ink-800">
                                        @if ($peer['avatar'])
                                            <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] }}" class="h-full w-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[11px] font-bold text-mist-200 transition group-hover:text-accent-300">{{ $peer['nickname'] }}</span>
                                        <span class="block text-[9px] text-mist-500">{{ $peer['games'] }} {{ __('ui.games_together') }} · {{ $peer['winrate'] !== null ? number_format($peer['winrate'], 1, '.', '').'%' : '—' }}</span>
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
    <div id="heatmap-tooltip" class="hidden fixed z-[9999] bg-[#1c1e26] border border-[#2d313e] p-2 rounded shadow-xl text-white text-sm pointer-events-none"></div>
    <script type="application/json" id="dotahub-heatmap-data">@json($activityHeatmap)</script>
    @vite('resources/js/heatmap.js')
@endpush
