@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Союзники - DotaHub'))
@section('description', 'Игроки, с которыми '.$player['nickname'].' чаще всего играл: совместные матчи, винрейт и дата последней игры.')

@section('profile-content')
        <div class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-4 sm:px-5">
                <h2 class="section-heading">Союзники</h2>
                <span class="badge border-line bg-ink-900/50 text-mist-400">{{ count($player['peers'] ?? []) }}</span>
            </div>

            @if (($player['peers'] ?? []) === [])
                <div class="p-10 text-center text-sm text-mist-400">Нет данных о совместных матчах</div>
            @else
                @php
                    $showWinrate = collect($player['peers'])->contains(fn (array $peer) => ($peer['winrate'] ?? null) !== null);
                    $showFavorite = collect($player['peers'])->contains(fn (array $peer) => ! empty($peer['favorite_hero']));
                @endphp
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-2 py-1 font-semibold sm:px-5">Игрок</th>
                                <th class="px-2 py-1 font-semibold">Матчи вместе</th>
                                @if ($showWinrate)
                                    <th class="px-2 py-1 font-semibold">Винрейт вместе</th>
                                @endif
                                @if ($showFavorite)
                                    <th class="px-2 py-1 font-semibold sm:px-5">Любимый герой</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="table-rows">
                            @foreach ($player['peers'] as $peer)
                                <tr class="transition hover:bg-ink-900/30">
                                    <td class="px-2 py-1 sm:px-5">
                                        <a href="{{ route('dota.player', ['accountId' => $peer['account_id']]) }}" class="group flex items-center gap-3">
                                            <span class="h-9 w-9 shrink-0 overflow-hidden rounded-none bg-ink-800  ">
                                                @if ($peer['avatar'])
                                                    <img src="{{ $peer['avatar'] }}" alt="{{ $peer['nickname'] }}" class="h-full w-full object-cover" loading="lazy">
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="flex items-center gap-1.5 truncate text-[13px] font-semibold text-mist-200 transition group-hover:text-gold-200">
                                                    {{ $peer['nickname'] }}
                                                    @if ($peer['is_private'] ?? false)
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-label="Скрытый профиль" title="Скрытый профиль"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 018 0v3" stroke-linecap="round" /></svg>
                                                    @endif
                                                </span>
                                                @if ($peer['is_private'] ?? false)
                                                    <span class="text-[9px] text-amber-300/80">Скрытый профиль</span>
                                                @endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-2 py-1 font-mono text-[13px] tabular-nums text-mist-300">{{ number_format($peer['games'], 0, '.', ' ') }}</td>
                                    @if ($showWinrate)
                                        <td class="px-2 py-1 font-mono text-[13px] tabular-nums {{ ($peer['winrate'] ?? 0) >= 50 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $peer['winrate'] !== null ? number_format($peer['winrate'], 1, '.', '').'%' : '—' }}</td>
                                    @endif
                                    @if ($showFavorite)
                                        <td class="px-2 py-1 sm:px-5">
                                            @if (! empty($peer['favorite_hero']))
                                                <span class="flex items-center gap-2" title="{{ $peer['favorite_hero']['games'] }} игр">
                                                    @if ($peer['favorite_hero']['image'])
                                                        <img src="{{ $peer['favorite_hero']['image'] }}" alt="{{ $peer['favorite_hero']['name'] }}" class="h-8 w-12 rounded border border-line object-cover" loading="lazy">
                                                    @endif
                                                    <span class="truncate text-[12px] font-semibold text-mist-200">{{ $peer['favorite_hero']['name'] }}</span>
                                                </span>
                                            @else
                                                <span class="text-[11px] text-mist-500">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
@endsection
