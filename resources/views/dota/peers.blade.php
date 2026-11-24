@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - '.__('ui.peers').' - DOTAHUB'))
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
            <div class="divide-y divide-[#2d313e]">
                @foreach ($player['peers'] as $peer)
                    <a href="{{ route('player', ['accountId' => $peer['account_id']]) }}" class="block">
                        <div class="flex items-center gap-4 py-3 border-b border-ink-700/50 hover:bg-ink-800/50 px-4 transition">
                            <!-- Avatar + nickname -->
                            <div class="flex items-center gap-3 w-1/3 min-w-0">
                                <img src="{{ $peer['avatar'] }}" class="w-10 h-10 object-cover shrink-0">
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-mist-200 truncate">{{ $peer['nickname'] ?? __('ui.teammate') }}</div>
                                    @if ($peer['is_private'] ?? false)
                                        <div class="text-[10px] text-accent-300/80">{{ __('ui.private_profile') }}</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Winrate (bar + value) -->
                            <div class="flex items-center gap-3 w-1/4 shrink-0">
                                <span class="text-xs w-12 text-right {{ (float) $peer['winrate'] >= 50 ? 'text-green-500' : 'text-red-500' }}">{{ number_format((float) $peer['winrate'], 1) }}%</span>
                                <div class="w-full bg-ink-900 h-2 flex rounded overflow-hidden">
                                    <div class="bg-green-500 h-full" style="width: {{ (float) $peer['winrate'] }}%"></div>
                                    <div class="bg-red-500 h-full" style="width: {{ 100 - (float) $peer['winrate'] }}%"></div>
                                </div>
                            </div>

                            <!-- Games (bar + value) -->
                            <div class="flex items-center gap-3 w-1/4 shrink-0 ml-auto">
                                <span class="text-xs w-12 text-right text-mist-400">{{ $peer['games'] }}</span>
                                <div class="w-full bg-ink-900 h-2 rounded overflow-hidden">
                                    <div class="bg-yellow-500 h-full" style="width: {{ ($peer['games'] / $maxPeerGames) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
