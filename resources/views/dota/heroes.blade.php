@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - '.__('ui.heroes').' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.heroes'))

@section('profile-content')
@php
    $selectedHeroId = (int) ($filters['hero_id'] ?? 0);
    $selectedHero = collect($heroOptions ?? [])->firstWhere('hero_id', $selectedHeroId);
@endphp

<section class="card mt-4 p-4 sm:p-5" x-data="{ heroModal: false }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="section-heading">{{ __('ui.heroes') }}</h2>
        </div>
        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $heroes->total() }} {{ __('ui.heroes_count') }}</span>
    </div>

    <form id="heroes-filter-form" method="GET" action="{{ route('player.heroes', ['accountId' => $player['account_id']]) }}" class="flex flex-row flex-wrap items-end gap-2">
        <div class="min-w-[180px] flex-1">
            <span class="label mb-1 block">{{ __('ui.hero') }}</span>
            <input type="hidden" name="hero_id" id="hero_id_input" value="{{ $selectedHeroId }}">
            <button type="button" @click="heroModal = true" class="h-10 w-full border border-[#2d313e] bg-[#1c1e26] px-3 text-left text-sm text-mist-200 hover:border-accent-400/50 focus:outline-none">
                {{ $selectedHero['localized_name'] ?? $selectedHero['name'] ?? __('ui.pick_hero') }}
            </button>
        </div>

        <label class="min-w-[180px] flex-1">
            <span class="label mb-1 block">{{ __('ui.sort') }}</span>
            <select name="sort" onchange="this.form.submit()" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                <option value="games" @selected(($filters['sort'] ?? 'games') === 'games')>{{ __('ui.sort_by_games') }}</option>
                <option value="winrate" @selected(($filters['sort'] ?? '') === 'winrate')>{{ __('ui.sort_by_winrate') }}</option>
                <option value="kda" @selected(($filters['sort'] ?? '') === 'kda')>{{ __('ui.sort_by_kda') }}</option>
            </select>
        </label>

        @if (($filters['hero_id'] ?? 0) > 0 || ($filters['sort'] ?? 'games') !== 'games')
            <a href="{{ route('player.heroes', ['accountId' => $player['account_id']]) }}" class="button-ghost h-10 px-4">{{ __('ui.reset') }}</a>
        @endif
    </form>

    @include('dota.partials.filter-hero', [
        'heroOptions' => $heroOptions ?? [],
        'selectedHeroId' => $selectedHeroId,
        'formId' => 'heroes-filter-form',
        'inputId' => 'hero_id_input',
    ])

    @if ($heroes->isEmpty())
        <div class="mt-6 p-10 text-center text-sm text-mist-400">{{ __('ui.no_heroes_found') }}</div>
    @else
        <div class="table-rows mt-4 overflow-x-auto border border-line bg-[#1c1e26]">
            <div class="table-head flex min-w-[860px] items-center gap-4 px-2 py-1 text-[11px] font-semibold">
                <span class="w-16 shrink-0"></span>
                <div class="min-w-0 flex flex-1 items-center justify-between">
                    <span class="w-32 shrink-0">{{ __('ui.hero') }}</span>
                    <span class="w-24 shrink-0">{{ __('ui.games') }}</span>
                    <span class="w-32 shrink-0">{{ __('ui.winrate') }}</span>
                </div>
                <span class="w-32 shrink-0">{{ __('ui.games') }}</span>
                <span class="w-20 shrink-0">KDA</span>
            </div>

            @foreach ($heroes as $hero)
                @php
                    $heroId = (int) ($hero['hero_id'] ?? 0);
                    $heroUrl = $heroId > 0 ? route('player.hero', ['accountId' => $player['account_id'], 'heroId' => $heroId]) : null;
                    $name = $hero['localized_name'] ?? $hero['name'] ?? __('ui.hero');
                    $img = $hero['image'] ?? $hero['icon'] ?? null;
                    $games = (int) ($hero['games'] ?? 0);
                    $wins = (int) ($hero['wins'] ?? $hero['win'] ?? 0);
                    $losses = (int) ($hero['losses'] ?? max(0, $games - $wins));
                    $winrate = (float) ($hero['win_rate'] ?? $hero['winrate'] ?? 0);
                    $kda = $hero['kda'] ?? null;
                @endphp
                <a href="{{ $heroUrl }}" class="group flex min-w-[860px] items-center gap-4 border-b border-ink-700/50 px-2 py-1 transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60 match-row">
                    <img src="{{ $img }}" class="w-16 h-9 object-cover shrink-0 rounded-sm" alt="{{ $name }}">

                    <div class="flex-1 min-w-0 flex items-center justify-between">
                        <div class="w-32 shrink-0 text-sm font-bold text-mist-200 truncate">{{ $name }}</div>

                        <div class="w-24 shrink-0 text-xs text-mist-400">
                            {{ $games }} {{ __('ui.hero_games_word') }}<br>
                            <span class="text-green-500">{{ $wins }} {{ __('ui.hero_win_short') }}</span> / <span class="text-red-500">{{ $losses }} {{ __('ui.hero_loss_short') }}</span>
                        </div>

                        <div class="flex items-center gap-2 w-32 shrink-0">
                            <span class="text-xs font-bold {{ $winrate >= 50 ? 'text-green-500' : 'text-red-500' }}">{{ number_format($winrate, 1, '.', '') }}%</span>
                            <div class="w-full bg-ink-900 h-1.5 rounded overflow-hidden">
                                <div class="{{ $winrate >= 50 ? 'bg-green-500' : 'bg-red-500' }} h-full" style="width: {{ $winrate }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="w-32 shrink-0 bg-ink-900 h-1.5 rounded overflow-hidden">
                        <div class="bg-yellow-500 h-full" style="width: {{ $maxHeroGames > 0 ? ($games / $maxHeroGames) * 100 : 0 }}%"></div>
                    </div>

                    <div class="w-20 shrink-0 font-mono text-xs text-mist-300">{{ $kda !== null ? number_format((float) $kda, 2, '.', '') : '—' }}</div>
                </a>
            @endforeach
        </div>

        @if ($heroes->hasPages())
            <div class="mt-4 border-t border-line pt-4">
                {{ $heroes->onEachSide(1)->links('vendor.pagination.dotahub') }}
            </div>
        @endif
    @endif
</section>
@endsection
