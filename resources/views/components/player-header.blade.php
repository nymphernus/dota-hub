@php
    $bannerHero = collect($player['heroes'] ?? [])->first(fn (array $hero) => ! empty($hero['image']));
    $bannerImage = $bannerHero['image'] ?? ($player['avatar'] ?? null);
    $rankTier = (int) ($player['rank_tier'] ?? 0);
    $rankIconBase = asset('images/dota/ranks');
    $rankTierBand = 0;
    $rankStars = 0;
    $rankIcon = $rankIconBase.'/rank_icon_0.png';
    $rankLabel = $player['rank_label'] ?: __('ui.no_rank');
    $rankStarIcon = null;

    if ($rankTier > 0) {
        $rankTierBand = intdiv($rankTier, 10);
        $rankStars = $rankTier % 10;
        $rankIcon = $rankIconBase.'/rank_icon_'.$rankTierBand.'.png';
        if ($rankTierBand >= 1 && $rankTierBand <= 7 && $rankStars >= 1) {
            $rankStarIcon = $rankIconBase.'/rank_star_'.min(5, $rankStars).'.png';
        }
    }

    $nameHistory = $player['name_history'] ?? [];
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

<section class="card relative overflow-hidden">
    @if ($bannerImage)
        <div class="absolute inset-0 bg-cover bg-center filter blur-sm opacity-20" style="background-image: url('{{ $bannerImage }}')" aria-hidden="true"></div>
    @endif
    <div class="relative z-10 px-4 pb-5 pt-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-none bg-ink-800    sm:h-24 sm:w-24">
                    @if ($player['avatar'])
                        <img src="{{ $player['avatar'] }}" alt="Аватар {{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                    @else
                        <span class="grid h-full w-full place-items-center text-3xl font-semibold text-mist-500">{{ mb_strtoupper(mb_substr($player['nickname'], 0, 1)) }}</span>
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">@if ($player['is_offline_cache'] ?? false)
                            <span class="badge border-line bg-[#2d313e] text-mist-300">Данные из кэша (Офлайн)</span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-2" x-data="{ open: false }">
                        <h1 class="min-w-0 truncate text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">{{ $player['nickname'] }}</h1>
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
                                :aria-label="active ? 'Удалить из избранного' : 'Добавить в избранное'"
                                :aria-pressed="active.toString()"
                                :title="active ? 'В избранном' : 'В избранное'"
                                data-favorite-star
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" :fill="active ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z" stroke-linejoin="round" />
                                </svg>
                            </button>
                        @else
                            <a
                                href="{{ route('auth.steam', ['intended' => url()->current()]) }}"
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-400 transition hover:border-accent-400/30 hover:text-accent-300"
                                aria-label="Добавить в избранное"
                                title="Добавить в избранное"
                                data-favorite-star
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z" stroke-linejoin="round" />
                                </svg>
                            </a>
                        @endif
                        <form
                            method="POST"
                            action="{{ route('dota.player.refresh', ['accountId' => $player['account_id']]) }}"
                            x-data="{ loading: false }"
                            @submit="loading = true"
                            @pageshow.window="loading = false"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-400 transition hover:text-mist-200 disabled:opacity-60"
                                :disabled="loading"
                                aria-label="Обновить"
                                title="Обновить"
                            >
                                <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M20 11a8 8 0 1 0-2.3 6.3" stroke-linecap="round" />
                                    <path d="M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </form>
                        @if ($nameHistory !== [])
                            <div class="relative">
                                <button type="button" @click="open = !open" class="grid h-7 w-7 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-400 transition hover:text-mist-200" :aria-expanded="open.toString()" aria-label="История никнеймов">
                                    <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click.outside="open = false" class="absolute left-0 top-9 z-20 w-56 rounded-none border border-line bg-ink-950/95 p-2 ">
                                    <p class="px-2 py-1 text-[10px] uppercase tracking-[0.16em] text-mist-500">История никнеймов</p>
                                    <div class="mt-1 max-h-56 space-y-1 overflow-y-auto">
                                        @foreach ($nameHistory as $entry)
                                            <div class="flex items-center justify-between gap-2 rounded-none px-2 py-1.5 text-[11px] hover:bg-ink-900/40">
                                                <span class="truncate font-semibold text-mist-200">{{ $entry['name'] }}</span>
                                                <span class="shrink-0 text-[9px] text-mist-500">{{ $entry['changed_at'] ? \Carbon\CarbonImmutable::parse($entry['changed_at'])->format('d.m.y') : '' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-mist-500">
                        <span class="font-mono">Dota ID {{ $player['account_id'] }}</span>
                        <span class="font-mono">SteamID64 {{ $steamProfile['steam_id'] ?? $player['steam_id'] ?? '—' }}</span>
                        @if ($steamProfile['account_created_at'] ?? null)
                            <span>Регистрация {{ \Carbon\CarbonImmutable::parse($steamProfile['account_created_at'])->format('d.m.Y') }}</span>
                        @endif
                        <a href="{{ $player['profile_url'] ?: 'https://steamcommunity.com/profiles/'.$player['steam_id'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 transition hover:text-accent-300">
                            Steam
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M10 5H5v14h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                        <span>{{ __('ui.updated') }} {{ isset($player['cached_at']) ? \Carbon\CarbonImmutable::parse($player['cached_at'])->diffForHumans(short: true) : 'только что' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-4 self-start lg:self-auto">
                <div class="relative inline-block w-24 h-24 shrink-0">
                    <img src="{{ $rankIconBase }}/rank_icon_{{ $rankTier > 0 ? $rankTierBand : 0 }}.png" alt="{{ $rankLabel }}" class="w-24 h-24 object-contain">
                    @if ($rankTier > 0 && $rankTierBand >= 1 && $rankTierBand <= 7 && $rankStars > 0)
                        <img src="{{ $rankIconBase }}/rank_star_{{ min(5, $rankStars) }}.png" alt="" class="absolute inset-0 w-24 h-24 object-contain">
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
    </div>
</section>
