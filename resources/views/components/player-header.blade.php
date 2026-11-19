@php
    $rankTier = (int) ($player['rank_tier'] ?? 0);
    $rankIconBase = asset('images/dota/ranks');
    $rankIcon = $rankIconBase.'/rank_icon_0.png';
    $rankStarIcon = null;

    if ($rankTier > 0) {
        $rankTierBand = intdiv($rankTier, 10);
        $rankIcon = $rankIconBase.'/rank_icon_'.$rankTierBand.'.png';

        if ($rankTierBand >= 1 && $rankTierBand <= 7) {
            $rankStars = max(1, min(7, $rankTier % 10));
            $rankStarIcon = $rankIconBase.'/rank_star_'.$rankStars.'.png';
        }
    }

    $nameHistory = $player['name_history'] ?? [];
    $steamAvailable = (bool) ($steamProfile['available'] ?? false);
    $steamPublic = $steamProfile['is_public'] ?? null;
    $dotaPublic = (bool) ($player['has_public_matches'] ?? false);
    $steamTone = match (true) {
        $steamPublic === true => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
        $steamPublic === false => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
        default => 'border-white/10 bg-white/5 text-mist-300',
    };
    $dotaTone = $dotaPublic
        ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300'
        : 'border-amber-500/25 bg-amber-500/10 text-amber-300';
@endphp

<section class="card relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-r from-dota-500/20 via-ink-800/40 to-cyan-500/10" aria-hidden="true"></div>
    <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-dota-500/10 blur-3xl" aria-hidden="true"></div>

    <div class="relative px-4 pb-5 pt-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-ink-800 shadow-2xl ring-4 ring-ink-900 sm:h-24 sm:w-24">
                    @if ($player['avatar'])
                        <img src="{{ $player['avatar'] }}" alt="Аватар {{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                    @else
                        <span class="grid h-full w-full place-items-center text-3xl font-black text-mist-500">{{ mb_strtoupper(mb_substr($player['nickname'], 0, 1)) }}</span>
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-2.5 py-1.5" title="Ранг: {{ $player['rank_label'] ?: 'Не откалиброван' }}">
                            <span class="relative inline-flex h-9 w-9 items-center justify-center">
                                <img src="{{ $rankIcon }}" alt="Иконка ранга {{ $player['rank_label'] ?: 'без ранга' }}" class="h-9 w-9 object-contain">
                                @if ($rankStarIcon)
                                    <img src="{{ $rankStarIcon }}" alt="" class="absolute -bottom-1 left-1/2 h-3 w-8 -translate-x-1/2 object-contain">
                                @endif
                            </span>
                            <span class="text-xs font-bold text-white">{{ $player['rank_label'] ?: 'Без ранга' }}</span>
                        </span>
                        @if ($player['is_offline_cache'] ?? false)
                            <span class="badge border-sky-500/25 bg-sky-500/10 text-sky-300">Данные из кэша (Офлайн)</span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-2" x-data="{ open: false }">
                        <h1 class="min-w-0 truncate text-3xl font-black tracking-tight text-white sm:text-4xl">{{ $player['nickname'] }}</h1>
                        @if ($nameHistory !== [])
                            <div class="relative">
                                <button type="button" @click="open = !open" class="grid h-7 w-7 place-items-center rounded-lg border border-white/10 bg-white/[0.04] text-mist-400 transition hover:text-white" :aria-expanded="open.toString()" aria-label="История никнеймов">
                                    <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click.outside="open = false" class="absolute left-0 top-9 z-20 w-56 rounded-xl border border-white/10 bg-ink-950/95 p-2 shadow-2xl backdrop-blur">
                                    <p class="px-2 py-1 text-[10px] uppercase tracking-[0.16em] text-mist-500">История никнеймов</p>
                                    <div class="mt-1 max-h-56 space-y-1 overflow-y-auto">
                                        @foreach ($nameHistory as $entry)
                                            <div class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-[11px] hover:bg-white/[0.04]">
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
                        <span class="font-mono">SteamID64 {{ $steamProfile['steam_id'] ?? $player['steam_id'] }}</span>
                        @if ($steamProfile['account_created_at'] ?? null)
                            <span>Регистрация {{ \Carbon\CarbonImmutable::parse($steamProfile['account_created_at'])->format('d.m.Y') }}</span>
                        @endif
                        <a href="{{ $player['profile_url'] ?: 'https://steamcommunity.com/profiles/'.$player['steam_id'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 transition hover:text-radar-300">
                            Steam
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M10 5H5v14h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                        <span>Обновлено {{ \Carbon\CarbonImmutable::parse($player['cached_at'])->diffForHumans(short: true) }}</span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('dota.player.refresh', ['accountId' => $player['account_id']]) }}" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="loading = false">
                    @csrf
                    <button type="submit" class="btn-ghost !py-2" :disabled="loading">
                        <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M20 11a8 8 0 1 0-2.3 6.3" stroke-linecap="round" />
                            <path d="M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span x-text="loading ? 'Обновляем…' : 'Обновить'">Обновить</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<div class="mt-4 flex flex-wrap items-center gap-2 rounded-2xl border border-white/[0.06] bg-ink-900/60 px-4 py-3 text-[11px]">
    <span class="uppercase tracking-[0.16em] text-mist-500">Приватность</span>
    <span @class(['badge', $steamTone])>Steam: {{ $steamProfile['visibility'] ?? 'Неизвестно' }}</span>
    <span @class(['badge', $dotaTone])>Dota: {{ $dotaPublic ? 'Публичные матчи доступны' : 'Матчей пока нет' }}</span>
    @if (! $steamAvailable)
        <span class="text-amber-300/80">{{ $steamProfile['error'] ?? 'Steam API недоступна' }}</span>
    @endif
    @if ($player['is_private'])
        <span class="badge border-amber-500/25 bg-amber-500/10 text-amber-300">Данные скрыты</span>
    @endif
</div>
