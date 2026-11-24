@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.peers'))

@section('profile-content')
    <section class="mt-4 border border-[#2d313e] bg-[#1c1e26]">
        <div class="flex items-center justify-between gap-2 border-b border-[#2d313e] px-4 py-3">
            <h2 class="section-heading">{{ __('ui.allies') }}</h2>
            <span class="text-[10px] text-mist-500">{{ count($player['peers'] ?? []) }}</span>
        </div>

        @if (($player['peers'] ?? []) === [])
            <div class="p-10 text-center text-sm text-mist-400">{{ __('ui.no_allies_data') }}</div>
        @else
            @php
                $maxGames = max(array_map(fn ($p) => (int) ($p['games'] ?? 0), $player['peers']));
                $maxGames = max(1, $maxGames);
            @endphp
            <div class="divide-y divide-[#2d313e]">
                @foreach ($player['peers'] as $peer)
                    @php
                        $games = (int) ($peer['games'] ?? 0);
                        $winrate = $peer['winrate'] !== null ? (float) $peer['winrate'] : null;
                        $wr = $winrate !== null ? min(100, max(0, $winrate)) : 0;
                        $wrClass = $winrate !== null && $winrate >= 50 ? 'text-green-500' : 'text-red-500';
                        $gamesPct = min(100, max(0, ($games / $maxGames) * 100));
                    @endphp
                    <a href="{{ route('player', ['accountId' => $peer['account_id']]) }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900/30">
                        <span class="h-10 w-10 shrink-0 overflow-hidden bg-ink-800">
                            @if (!empty($peer['avatar']))
                                <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] ?? __('ui.teammate') }}" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-mist-200">{{ $peer['nickname'] ?? __('ui.teammate') }}</span>
                                    @if ($peer['is_private'] ?? false)
                                        <span class="text-[10px] text-accent-300/80">{{ __('ui.private_profile') }}</span>
                                    @endif
                                </div>
                                <span class="shrink-0 font-mono text-xs font-bold {{ $wrClass }}">
                                    {{ $winrate !== null ? number_format($winrate, 1, '.', '').'%' : '—' }}
                                </span>
                            </div>

                            <div class="mt-1.5 flex h-1.5 w-full overflow-hidden bg-gray-700">
                                <div class="h-full bg-green-500" style="width: {{ $wr }}%"></div>
                                <div class="h-full bg-red-500" style="width: {{ max(0, 100 - $wr) }}%"></div>
                            </div>

                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-[10px] text-mist-500">{{ __('ui.games_together') }}</span>
                                <span class="font-mono text-[10px] font-semibold text-mist-300">{{ number_format($games, 0, '.', ' ') }}</span>
                            </div>

                            <div class="mt-1 flex h-1.5 w-full overflow-hidden bg-gray-700">
                                <div class="h-full bg-yellow-500" style="width: {{ $gamesPct }}%"></div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
