@php
    $avatar = $player['avatar'] ?? null;
    $rankTier = (int) ($player['rank_tier'] ?? 0);
    $rankIconBase = asset('images/dota/ranks');
    $rankIcon = $rankIconBase.'/rank_icon_0.png';
    $rankStars = 0;
    $rankTierBand = 0;
    if ($rankTier > 0) {
        $rankTierBand = intdiv($rankTier, 10);
        $rankIcon = $rankIconBase.'/rank_icon_'.$rankTierBand.'.png';
        if ($rankTierBand >= 1 && $rankTierBand <= 7) {
            $rankStars = max(1, min(5, $rankTier % 10));
        }
    }
    $rankNames = [
        'Herald' => 'Рекрут', 'Guardian' => 'Страж', 'Crusader' => 'Рыцарь',
        'Archon' => 'Герой', 'Legend' => 'Легенда', 'Ancient' => 'Властелин',
        'Divine' => 'Божество', 'Immortal' => 'Титан',
    ];
    $rawRank = $player['rank_label'] ?: '';
    $rankLabel = $rawRank !== '' ? str_replace(array_keys($rankNames), array_values($rankNames), $rawRank) : __('ui.no_rank');
    $nameHistory = $player['name_history'] ?? [];
@endphp

<section class="card relative overflow-hidden">
    @if ($avatar)
        <div class="absolute inset-0 bg-cover bg-center filter blur-md opacity-40 scale-105" style="background-image: url('{{ $avatar }}');" aria-hidden="true"></div>
    @endif
    <div class="absolute inset-0 bg-gradient-to-r from-[#111318]/60 via-[#111318]/20 to-[#111318]/60" aria-hidden="true"></div>

    <div class="relative z-10 flex flex-col gap-4 p-4 sm:p-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <div class="relative h-16 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800 border border-[#2d313e] sm:h-20 sm:w-20">
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="{{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                @else
                    <span class="grid h-full w-full place-items-center text-2xl font-semibold text-mist-500">{{ mb_strtoupper(mb_substr($player['nickname'], 0, 1)) }}</span>
                @endif
            </div>

            <div class="min-w-0">
                @if ($player['is_offline_cache'] ?? false)
                    <span class="badge border-line bg-[#2d313e] text-mist-300 mb-1">{{ __('ui.cached_data') }}</span>
                @endif

                <div class="flex flex-wrap items-center gap-x-2 gap-y-1" x-data="{ open: false }">
                    <h1 class="min-w-0 truncate text-2xl font-semibold tracking-tight text-mist-200 sm:text-3xl">{{ $player['nickname'] }}</h1>
                    @if (session('steam_id'))
                        <button
                            type="button"
                            x-data="favoriteStar({
                                accountId: {{ (int) $player['account_id'] }},
                                nickname: @js($player['nickname']),
                                avatar: @js($player['avatar']),
                                active: {{ ($isFavorite ?? false) ? 'true' : 'false' }},
                                storeUrl: @js(route('favorites.store')),
                                destroyUrl: @js(route('favorites.destroy', ['accountId' => $player['account_id']])),
                            })"
                            @click="toggle"
                            :disabled="loading"
                            :class="active ? 'border-accent-400/40 bg-accent-400/10 text-accent-300' : 'border-line bg-ink-900/40 text-mist-400 hover:border-accent-400/30 hover:text-accent-300'"
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-none border transition disabled:opacity-60"
                            :aria-label="active ? @js(__('ui.remove_favorite')) : @js(__('ui.add_favorite'))"
                            :title="active ? @js(__('ui.in_favorites')) : @js(__('ui.add_favorite'))"
                            data-favorite-star
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" :fill="active ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z" stroke-linejoin="round" /></svg>
                        </button>
                    @else
                        <a href="{{ route('auth.steam', ['intended' => url()->current()]) }}" class="grid h-8 w-8 shrink-0 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-400 transition hover:border-accent-400/30 hover:text-accent-300" aria-label="{{ __('ui.add_favorite') }}" title="{{ __('ui.add_favorite') }}" data-favorite-star>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z" stroke-linejoin="round" /></svg>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('dota.player.refresh', ['accountId' => $player['account_id']]) }}" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="loading = false">
                        @csrf
                        <button type="submit" class="grid h-8 w-8 shrink-0 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-400 transition hover:text-mist-200 disabled:opacity-60" :disabled="loading" aria-label="{{ __('ui.refresh') }}" title="{{ __('ui.refresh') }}">
                            <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 6.3" stroke-linecap="round" /><path d="M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </button>
                    </form>
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-mist-500">
                    <span class="font-mono">Dota ID {{ $player['account_id'] }}</span>
                    <span class="font-mono">SteamID64 {{ $steamProfile['steam_id'] ?? $player['steam_id'] ?? '—' }}</span>
                    @if ($steamProfile['account_created_at'] ?? null)
                        <span>{{ __('ui.registration') }} {{ \Carbon\CarbonImmutable::parse($steamProfile['account_created_at'])->format('d.m.Y') }}</span>
                    @endif
                    <a href="{{ $player['profile_url'] ?: 'https://steamcommunity.com/profiles/'.$player['steam_id'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 transition hover:text-accent-300">Steam <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M10 5H5v14h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg></a>
                    <span>{{ __('ui.updated') }} {{ isset($player['cached_at']) ? \Carbon\CarbonImmutable::parse($player['cached_at'])->diffForHumans(short: true) : 'только что' }}</span>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3 self-start sm:self-auto">
            <div class="flex flex-col items-center gap-1">
                <img src="{{ $rankIcon }}" alt="{{ $rankLabel }}" class="h-14 w-14 object-contain sm:h-16 sm:w-16">
                @if ($rankTierBand >= 1 && $rankTierBand <= 7)
                    <div class="flex items-center gap-0.5" aria-label="{{ $rankStars }} / 5">
                        @for ($i = 1; $i <= 5; $i++)
                            <svg class="h-3.5 w-3.5 {{ $i <= $rankStars ? 'text-yellow-400' : 'text-mist-500/40' }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
                        @endfor
                    </div>
                @endif
            </div>
            <div class="text-right">
                <div class="text-xl font-bold text-mist-100 sm:text-2xl">{{ $rankLabel }}</div>
                @if (! empty($player['leaderboard_rank']))
                    <div class="text-sm text-accent-300">#{{ number_format((int) $player['leaderboard_rank'], 0, '.', ' ') }}</div>
                @endif
            </div>
        </div>
    </div>
</section>
