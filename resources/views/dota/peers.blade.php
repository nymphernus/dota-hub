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
                    <a href="{{ route('player', ['accountId' => $peer['account_id']]) }}" class="flex items-center gap-3 p-2 hover:bg-ink-700/50 transition">
                        @if (!empty($peer['avatar']))
                            <img src="{{ $peer['avatar'] }}" class="w-10 h-10 object-cover rounded-sm shrink-0">
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-mist-200 truncate">
                                {{ $peer['nickname'] ?? __('ui.teammate') }}
                                @if ($peer['is_private'] ?? false)
                                    <span class="text-[10px] font-normal text-accent-300/80">{{ __('ui.private_profile') }}</span>
                                @endif
                            </div>

                            <div class="flex h-1 w-full mt-1 rounded overflow-hidden">
                                <div class="bg-green-500" style="width: {{ (float) ($peer['winrate'] ?? 0) }}%"></div>
                                <div class="bg-red-500" style="width: {{ 100 - (float) ($peer['winrate'] ?? 0) }}%"></div>
                            </div>
                        </div>

                        <div class="text-sm font-bold {{ ($peer['winrate'] ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">
                            {{ (float) ($peer['winrate'] ?? 0) }}%
                        </div>

                        <div class="w-20 flex flex-col items-end gap-1">
                            <div class="text-xs text-mist-400">{{ (int) ($peer['games'] ?? 0) }}</div>
                            <div class="w-full bg-ink-900 h-1.5 rounded overflow-hidden">
                                <div class="bg-yellow-500 h-full" style="width: {{ ((int) ($peer['games'] ?? 0) / $maxPeerGames) * 100 }}%"></div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
