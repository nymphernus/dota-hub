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
                        <div class="flex items-center gap-3 py-2 border-b border-ink-700/50">
                            <img src="{{ $peer['avatar'] }}" class="w-10 h-10 object-cover shrink-0">

                            <div class="flex-1 min-w-0 truncate text-sm text-mist-200">
                                {{ $peer['nickname'] ?? __('ui.teammate') }}
                                @if ($peer['is_private'] ?? false)
                                    <span class="text-[10px] text-accent-300/80">{{ __('ui.private_profile') }}</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 w-24 shrink-0">
                                <span class="text-xs {{ ($peer['winrate'] ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">{{ number_format((float) ($peer['winrate'] ?? 0), 1) }}%</span>
                                <div class="w-full bg-ink-900 h-1.5 flex rounded overflow-hidden">
                                    <div class="bg-green-500" style="width: {{ (float) ($peer['winrate'] ?? 0) }}%"></div>
                                    <div class="bg-red-500" style="width: {{ 100 - (float) ($peer['winrate'] ?? 0) }}%"></div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 w-20 shrink-0 justify-end">
                                <span class="text-xs text-mist-400">{{ (int) ($peer['games'] ?? 0) }}</span>
                                <div class="w-full bg-ink-900 h-1.5 rounded overflow-hidden">
                                    <div class="bg-yellow-500 h-full" style="width: {{ ((int) ($peer['games'] ?? 0) / $maxPeerGames) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
