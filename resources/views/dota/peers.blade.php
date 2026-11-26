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
            <div class="table-rows">
                @foreach ($player['peers'] as $peer)
                    <a href="{{ route('player', ['accountId' => $peer['account_id']]) }}" class="group block border-b border-ink-700/50 px-4 py-1 transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60 match-row">
                        <div class="flex items-center gap-2">
                            <!-- Avatar + nickname -->
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <img src="{{ $peer['avatar'] }}" class="h-10 w-10 shrink-0 object-cover">
                                <div class="min-w-0 flex-1">
                                    <div class="flex-1 min-w-0 text-[11px] font-bold text-mist-200 truncate leading-tight">{{ $peer['nickname'] ?? __('ui.teammate') }}</div>
                                    @if ($peer['is_private'] ?? false)
                                        <div class="text-[10px] text-accent-300/80">{{ __('ui.private_profile') }}</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Winrate (bar + value) -->
                            <div class="flex w-16 shrink-0 items-center justify-end gap-1">
                                <span class="w-8 text-right text-[10px] {{ (float) $peer['winrate'] >= 50 ? 'text-green-500' : 'text-red-500' }}">{{ number_format((float) $peer['winrate'], 1) }}%</span>
                                <div class="h-1.5 w-8 bg-ink-900 flex rounded overflow-hidden">
                                    <div class="bg-green-500 h-full" style="width: {{ (float) $peer['winrate'] }}%"></div>
                                    <div class="bg-red-500 h-full" style="width: {{ 100 - (float) $peer['winrate'] }}%"></div>
                                </div>
                            </div>

                            <!-- Games (bar + value) -->
                            <div class="flex w-16 shrink-0 items-center justify-end gap-1">
                                <span class="w-8 text-right text-[10px] text-mist-400">{{ $peer['games'] }}</span>
                                <div class="h-1.5 w-8 bg-ink-900 rounded overflow-hidden">
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
