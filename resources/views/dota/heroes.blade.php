@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - '.__('ui.heroes').' - DOTAHUB'))
@section('description', $player['nickname'].' - '.__('ui.heroes'))

@section('profile-content')
<section class="card mt-4 p-4 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="section-heading">{{ __('ui.heroes') }}</h2>
        </div>
        <span class="badge border-line bg-ink-900/50 text-mist-400">{{ $heroes->total() }} {{ __('ui.heroes_count') }}</span>
    </div>

    <form method="GET" action="{{ route('player.heroes', ['accountId' => $player['account_id']]) }}" class="flex flex-row flex-wrap items-end gap-2">
        <label class="min-w-[180px] flex-1">
            <span class="label mb-1 block">{{ __('ui.hero') }}</span>
            <select name="hero_id" onchange="this.form.submit()" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] text-mist-200 px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                <option value="0">{{ __('ui.all_heroes') }}</option>
                @foreach (($heroOptions ?? []) as $hero)
                    <option value="{{ $hero['hero_id'] }}" @selected((int) ($filters['hero_id'] ?? 0) === (int) $hero['hero_id'])>{{ $hero['localized_name'] ?? $hero['name'] ?? __('ui.hero') }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-[180px] flex-1">
            <span class="label mb-1 block">{{ __('ui.sort') }}</span>
            <select name="sort" onchange="this.form.submit()" class="h-10 w-full rounded border border-[#2d313e] bg-[#1c1e26] text-mist-200 px-3 text-sm text-mist-200 focus:border-accent-400/50 focus:outline-none">
                <option value="games" @selected(($filters['sort'] ?? 'games') === 'games')>{{ __('ui.sort_by_games') }}</option>
                <option value="winrate" @selected(($filters['sort'] ?? '') === 'winrate')>{{ __('ui.sort_by_winrate') }}</option>
                <option value="kda" @selected(($filters['sort'] ?? '') === 'kda')>{{ __('ui.sort_by_kda') }}</option>
            </select>
        </label>

        @if (($filters['hero_id'] ?? 0) > 0 || ($filters['sort'] ?? 'games') !== 'games')
            <a href="{{ route('player.heroes', ['accountId' => $player['account_id']]) }}" class="button-ghost h-10 px-4">{{ __('ui.reset') }}</a>
        @endif
    </form>

    @if ($heroes->isEmpty())
        <div class="mt-6 p-10 text-center text-sm text-mist-400">{{ __('ui.no_heroes_found') }}</div>
    @else
        <div class="mt-4 overflow-hidden border border-line bg-[#1c1e26]">
            <table class="w-full text-sm">
                <thead class="table-head">
                    <tr>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.hero') }}</th>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.games') }}</th>
                        <th class="px-2 py-1 text-left font-semibold">{{ __('ui.winrate') }}</th>
                        <th class="px-2 py-1 text-left font-semibold">KDA</th>
                    </tr>
                </thead>
                <tbody class="table-rows">
                    @foreach ($heroes as $hero)
                        @php
                            $heroId = (int) ($hero['hero_id'] ?? 0);
                            $heroUrl = $heroId > 0 ? route('player.hero', ['accountId' => $player['account_id'], 'heroId' => $heroId]) : null;
                            $name = $hero['localized_name'] ?? $hero['name'] ?? __('ui.hero');
                            $img = $hero['image'] ?? $hero['icon'] ?? null;
                            $games = (int) ($hero['games'] ?? 0);
                            $winrate = $hero['win_rate'] ?? $hero['winrate'] ?? null;
                            $kda = $hero['kda'] ?? null;
                        @endphp
                        <tr class="transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60 text-mist-200">
                            <td class="px-2 py-1">
                                <div class="flex items-center gap-3">
                                    @if ($heroUrl)
                                        <a href="{{ $heroUrl }}" class="shrink-0 overflow-hidden rounded bg-ink-800" style="width:48px;height:27px;">
                                            @if ($img)
                                                <img src="{{ $img }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </a>
                                        <a href="{{ $heroUrl }}" class="truncate text-[13px] font-bold text-mist-200 transition hover:text-accent-300">{{ $name }}</a>
                                    @else
                                        <span class="shrink-0 overflow-hidden rounded bg-ink-800" style="width:48px;height:27px;">
                                            @if ($img)
                                                <img src="{{ $img }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </span>
                                        <span class="truncate text-[13px] font-bold text-mist-200">{{ $name }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] text-mist-200">{{ $games }}</td>
                            <td class="px-2 py-1 font-mono text-[12px] font-semibold {{ ($winrate ?? 0) >= 50 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $winrate !== null ? number_format((float) $winrate, 1, '.', '').'%' : '-' }}
                            </td>
                            <td class="px-2 py-1 font-mono text-[12px] text-mist-300">
                                {{ $kda !== null ? number_format((float) $kda, 2, '.', '') : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($heroes->hasPages())
            <div class="mt-4 border-t border-line pt-4">
                {{ $heroes->onEachSide(1)->links('vendor.pagination.dotahub') }}
            </div>
        @endif
    @endif
</section>
@endsection
