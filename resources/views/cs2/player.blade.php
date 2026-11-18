@extends('layouts.app')

@section('title', ($player['nickname'] ?? 'CS2').' — CS2 — Stats Radar')
@section('description', 'CS2 профиль игрока: часы в игре, время за последние 2 недели, VAC-баны и статус аккаунта.')

@php
    $cs2 = $player['cs2'] ?? null;
    $accountCreated = $player['account_created_at'] ?? null;
@endphp

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-[11px] text-mist-500" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="text-mist-400">CS2</span>
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" /></svg>
            <span class="truncate text-slate-300">{{ $player['nickname'] ?? $steamId }}</span>
        </nav>

        @if ($error || $player === null)
            <section class="card relative overflow-hidden p-6 sm:p-8">
                <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-cs2-500/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex max-w-3xl flex-col gap-5">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl border border-cs2-500/25 bg-cs2-500/10 text-cs2-300">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M12 3l7 4v5c0 4.2-2.9 7.6-7 9-4.1-1.4-7-4.8-7-9V7l7-4z" stroke-linejoin="round" />
                            <path d="M12 8v4.5M12 16v.3" stroke-linecap="round" />
                        </svg>
                    </span>

                    <div>
                        <h1 class="text-2xl font-black text-white sm:text-3xl">Steam API не настроена</h1>
                        <p class="mt-2 text-sm leading-relaxed text-mist-300">{{ $error }}</p>
                    </div>

                    <ol class="space-y-3 text-sm text-mist-300">
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-cs2-500/15 text-[11px] font-bold text-cs2-300">1</span>
                            <span>Получите бесплатный ключ на <a href="https://steamcommunity.com/dev/apikey" target="_blank" rel="noopener noreferrer" class="font-semibold text-cs2-300 underline decoration-cs2-500/40 underline-offset-2 hover:text-cs2-300">steamcommunity.com/dev/apikey</a>.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-cs2-500/15 text-[11px] font-bold text-cs2-300">2</span>
                            <span>Добавьте <code class="rounded bg-ink-950 px-1.5 py-0.5 font-mono text-xs text-cs2-300">STEAM_API_KEY=ваш_ключ</code> в файл <code class="rounded bg-ink-950 px-1.5 py-0.5 font-mono text-xs text-cs2-300">.env</code>.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-cs2-500/15 text-[11px] font-bold text-cs2-300">3</span>
                            <span>Перезапустите контейнеры: <code class="rounded bg-ink-950 px-1.5 py-0.5 font-mono text-xs text-cs2-300">docker compose up -d --force-recreate app web queue</code>.</span>
                        </li>
                    </ol>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <a href="{{ route('home') }}" class="btn-primary">Вернуться к поиску</a>
                        <a href="https://steamcommunity.com/dev/apikey" target="_blank" rel="noopener noreferrer" class="btn-ghost">Получить ключ</a>
                    </div>
                </div>
            </section>
        @else
            <section class="card relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-r from-cs2-500/20 via-ink-800/40 to-sky-500/10" aria-hidden="true"></div>
                <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-cs2-500/10 blur-3xl" aria-hidden="true"></div>

                <div class="relative px-4 pb-5 pt-16 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end">
                            <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-ink-800 shadow-2xl ring-4 ring-ink-900 sm:h-28 sm:w-28">
                                @if ($player['avatar'])
                                    <img src="{{ $player['avatar'] }}" alt="Аватар {{ $player['nickname'] }}" class="h-full w-full object-cover" loading="eager">
                                @else
                                    <span class="grid h-full w-full place-items-center text-3xl font-black text-mist-500">{{ mb_strtoupper(mb_substr($player['nickname'], 0, 1)) }}</span>
                                @endif
                                @if ($player['playing_cs2'])
                                    <span class="absolute bottom-1.5 right-1.5 h-3.5 w-3.5 rounded-full border-2 border-ink-900 bg-emerald-400" title="Играет в CS2"></span>
                                @endif
                            </div>

                            <div class="min-w-0 pb-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge border-cs2-500/25 bg-cs2-500/10 text-cs2-300">CS2</span>
                                    <span @class([
                                        'badge',
                                        'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' => $player['is_public'],
                                        'border-amber-500/25 bg-amber-500/10 text-amber-300' => ! $player['is_public'],
                                    ])>
                                        {{ $player['visibility'] }}
                                    </span>
                                    @if ($player['playing_cs2'])
                                        <span class="badge border-radar-500/25 bg-radar-500/10 text-radar-300">В игре</span>
                                    @endif
                                </div>

                                <h1 class="mt-2 truncate text-3xl font-black tracking-tight text-white sm:text-4xl">{{ $player['nickname'] }}</h1>

                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-mist-400">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span @class([
                                            'h-2 w-2 rounded-full',
                                            'bg-emerald-400' => $player['status'] === 'В сети',
                                            'bg-amber-400' => $player['status'] !== 'В сети' && $player['status'] !== 'Не в сети',
                                            'bg-mist-500' => $player['status'] === 'Не в сети',
                                        ])></span>
                                        {{ $player['status'] }}
                                    </span>
                                    <span class="font-mono text-mist-500">{{ $player['steam_id'] }}</span>
                                    <a href="{{ $player['profile_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 transition hover:text-cs2-300">
                                        Steam-профиль
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M10 5H5v14h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </a>
                                    <span class="inline-flex items-center gap-1 text-mist-500">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" /></svg>
                                        Обновлено {{ \Carbon\CarbonImmutable::parse($player['cached_at'])->diffForHumans(short: true) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <span class="badge border-white/10 bg-white/5 !px-3 !py-2 text-mist-400" title="Время жизни кэша">
                                <svg class="h-3.5 w-3.5 text-cs2-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3" stroke-linecap="round" /><circle cx="12" cy="12" r="4" /></svg>
                                Кэш {{ intdiv($player['cache_ttl'], 60) }} мин
                            </span>

                            <form method="POST" action="{{ route('cs2.player.refresh', ['steamId' => $player['steam_id']]) }}" x-data="{ loading: false }" @submit="loading = true">
                                @csrf
                                <button type="submit" class="btn-ghost !py-2" :disabled="loading">
                                    <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M20 11a8 8 0 1 0-2.3 6.3" stroke-linecap="round" />
                                        <path d="M20 4v7h-7" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span x-text="loading ? 'Обновляем…' : 'Обновить профиль'">Обновить профиль</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-white/[0.06] bg-white/[0.06] sm:grid-cols-4">
                        <div class="bg-ink-900/95 px-4 py-3">
                            <p class="label">Всего в CS2</p>
                            <p class="mt-1.5 text-xl font-black text-white">{{ $cs2 ? number_format($cs2['total_hours'], 1, '.', ' ') : '—' }}@if ($cs2)<span class="text-sm text-mist-500"> ч</span>@endif</p>
                            <p class="text-[10px] text-mist-500">За всё время</p>
                        </div>
                        <div class="bg-ink-900/95 px-4 py-3">
                            <p class="label">За 2 недели</p>
                            <p class="mt-1.5 text-xl font-black text-cs2-300">{{ $cs2 ? number_format($cs2['recent_hours'], 1, '.', ' ') : '—' }}@if ($cs2)<span class="text-sm text-mist-500"> ч</span>@endif</p>
                            <p class="text-[10px] text-mist-500">Недавняя активность</p>
                        </div>
                        <div class="bg-ink-900/95 px-4 py-3">
                            <p class="label">VAC</p>
                            <p class="mt-1.5 text-xl font-black {{ $player['vac_banned'] ? 'text-rose-300' : 'text-emerald-300' }}">
                                {{ $player['vac_banned'] ? $player['vac_bans'].' бан' : 'Чисто' }}
                            </p>
                            <p class="text-[10px] text-mist-500">{{ $player['vac_banned'] ? 'VAC-блокировка' : 'Блокировок нет' }}</p>
                        </div>
                        <div class="bg-ink-900/95 px-4 py-3">
                            <p class="label">Game bans</p>
                            <p class="mt-1.5 text-xl font-black {{ $player['game_bans'] > 0 ? 'text-rose-300' : 'text-emerald-300' }}">{{ $player['game_bans'] }}</p>
                            <p class="text-[10px] text-mist-500">{{ $player['game_bans'] > 0 ? 'Есть блокировки' : 'Блокировок нет' }}</p>
                        </div>
                    </div>
                </div>
            </section>

            @if ($player['has_bans'])
                <div class="mt-4 flex flex-col gap-3 rounded-2xl border border-rose-500/30 bg-rose-500/[0.08] p-4 sm:flex-row sm:items-start">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-500/15 text-rose-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 4l8 14H4L12 4z" stroke-linejoin="round" /><path d="M12 9.5v4M12 16.2v.3" stroke-linecap="round" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-rose-200">Обнаружены блокировки аккаунта</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                            @if ($player['vac_banned'])
                                <span class="badge border-rose-500/30 bg-rose-500/10 text-rose-300">VAC: {{ $player['vac_bans'] }}</span>
                            @endif
                            @if ($player['game_bans'] > 0)
                                <span class="badge border-rose-500/30 bg-rose-500/10 text-rose-300">Game bans: {{ $player['game_bans'] }}</span>
                            @endif
                            @if ($player['community_banned'])
                                <span class="badge border-rose-500/30 bg-rose-500/10 text-rose-300">Community ban</span>
                            @endif
                            @if ($player['days_since_last_ban'] > 0)
                                <span class="badge border-white/10 bg-white/5 text-mist-300">{{ $player['days_since_last_ban'] }} дн. с последнего бана</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-4 flex items-center gap-3 rounded-2xl border border-emerald-500/25 bg-emerald-500/[0.07] p-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-500/15 text-emerald-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3l7 4v5c0 4.2-2.9 7.6-7 9-4.1-1.4-7-4.8-7-9V7l7-4z" stroke-linejoin="round" /><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-emerald-200">Блокировок не обнаружено</p>
                        <p class="mt-0.5 text-[11px] text-emerald-200/60">Steam не сообщает о VAC- или game-банах для этого аккаунта.</p>
                    </div>
                </div>
            @endif

            @if (! $player['is_public'])
                <div class="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/[0.07] p-4 sm:flex-row sm:items-start">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500/15 text-amber-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-amber-100">Профиль закрыт</p>
                        <p class="mt-1 text-xs leading-relaxed text-amber-100/70">
                            Игрок ограничил видимость профиля, поэтому Steam не отдаёт часы и данные библиотеки.
                            Чтобы увидеть полную статистику, попросите владельца аккаунта открыть профиль в настройках приватности Steam.
                        </p>
                    </div>
                    <a href="https://steamcommunity.com/my/edit/settings" target="_blank" rel="noopener noreferrer" class="btn-ghost shrink-0 !py-2 text-xs">Настройки Steam</a>
                </div>
            @endif

            <div
                class="mt-6"
                x-data="{ tab: 'stats' }"
            >
                <div class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-white/[0.06] bg-ink-900/60 p-1.5" role="tablist" aria-label="Разделы профиля">
                    <button type="button" @click="tab = 'stats'" :class="{ 'tab-button-active': tab === 'stats' }" class="tab-button" role="tab" :aria-selected="(tab === 'stats').toString()">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 19V5M4 19h16" stroke-linecap="round" /><path d="M8 15l3-4 3 2 4-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Статистика
                    </button>
                    <button type="button" @click="tab = 'account'" :class="{ 'tab-button-active': tab === 'account' }" class="tab-button" role="tab" :aria-selected="(tab === 'account').toString()">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5" /><path d="M5 20a7 7 0 0 1 14 0" stroke-linecap="round" /></svg>
                        Аккаунт
                    </button>
                </div>

                <div class="mt-4">
                    <div x-show="tab === 'stats'" x-transition.opacity.duration.200ms role="tabpanel" aria-label="Статистика">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <x-stat label="Часов в CS2" :value="$cs2 ? number_format($cs2['total_hours'], 1, '.', ' ').' ч' : '—'" hint="За всё время по данным Steam" />
                            <x-stat label="За 2 недели" :value="$cs2 ? number_format($cs2['recent_hours'], 1, '.', ' ').' ч' : '—'" hint="Recent playtime" />
                            <x-stat label="VAC-баны" :value="$player['vac_bans']" hint="Valve Anti-Cheat" />
                            <x-stat label="Game-баны" :value="$player['game_bans']" hint="Блокировки в играх" />
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <div class="card p-4 sm:p-5 lg:col-span-2">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="label">Библиотека Steam</p>
                                        <h2 class="mt-1 text-lg font-bold text-white">Counter-Strike 2</h2>
                                    </div>
                                    @if ($cs2)
                                        <span class="badge border-emerald-500/25 bg-emerald-500/10 text-emerald-300">В библиотеке</span>
                                    @endif
                                </div>

                                @if ($cs2)
                                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-xl border border-white/[0.06] bg-white/[0.025] p-4">
                                            <p class="text-[10px] uppercase tracking-wider text-mist-500">Всего часов</p>
                                            <p class="mt-1 text-2xl font-black text-white">{{ number_format($cs2['total_hours'], 1, '.', ' ') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-white/[0.06] bg-white/[0.025] p-4">
                                            <p class="text-[10px] uppercase tracking-wider text-mist-500">За 2 недели</p>
                                            <p class="mt-1 text-2xl font-black text-cs2-300">{{ number_format($cs2['recent_hours'], 1, '.', ' ') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-white/[0.06] bg-white/[0.025] p-4">
                                            <p class="text-[10px] uppercase tracking-wider text-mist-500">Последний запуск</p>
                                            <p class="mt-1 text-sm font-bold text-white">
                                                {{ $cs2['last_played_at'] ? \Carbon\CarbonImmutable::parse($cs2['last_played_at'])->diffForHumans(short: true) : '—' }}
                                            </p>
                                        </div>
                                    </div>
                                @elseif ($player['library_hidden'])
                                    <div class="mt-4 rounded-xl border border-dashed border-white/10 p-6 text-center">
                                        <p class="text-sm text-mist-400">Данные библиотеки скрыты настройками приватности Steam.</p>
                                    </div>
                                @else
                                    <div class="mt-4 rounded-xl border border-dashed border-white/10 p-6 text-center">
                                        <p class="text-sm text-mist-400">CS2 не найдена в открытой библиотеке игрока.</p>
                                    </div>
                                @endif
                            </div>

                            <div class="card flex flex-col p-4 sm:p-5">
                                <p class="label">Ограничения API</p>
                                <h2 class="mt-1 text-lg font-bold text-white">Что доступно</h2>
                                <p class="mt-2 text-xs leading-relaxed text-mist-400">
                                    Valve не публикует открытый API матчей CS2. Stats Radar показывает только то, что Steam
                                    отдаёт официально: часы, статус аккаунта и блокировки.
                                </p>
                                <ul class="mt-4 space-y-2 text-xs text-mist-300">
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Steam Web API · Player Summaries</li>
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> GetPlayerBans · VAC / game bans</li>
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> GetOwnedGames · playtime</li>
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-mist-500"></span> Матчи и рейтинг — недоступны</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'account'" x-cloak x-transition.opacity.duration.200ms role="tabpanel" aria-label="Аккаунт">
                        <div class="card overflow-hidden">
                            <div class="border-b border-white/[0.06] px-5 py-4">
                                <p class="label">Steam</p>
                                <h2 class="mt-1 text-lg font-bold text-white">Данные аккаунта</h2>
                            </div>
                            <dl class="grid gap-px bg-white/[0.06] sm:grid-cols-2 lg:grid-cols-3">
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">SteamID64</dt>
                                    <dd class="mt-1.5 break-all font-mono text-sm text-white">{{ $player['steam_id'] }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Видимость</dt>
                                    <dd class="mt-1.5 text-sm font-semibold {{ $player['is_public'] ? 'text-emerald-300' : 'text-amber-300' }}">{{ $player['visibility'] }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Статус</dt>
                                    <dd class="mt-1.5 text-sm font-semibold text-white">{{ $player['status'] }}{{ $player['playing_cs2'] ? ' · CS2' : '' }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Аккаунт создан</dt>
                                    <dd class="mt-1.5 text-sm text-white">{{ $accountCreated ? \Carbon\CarbonImmutable::parse($accountCreated)->translatedFormat('j F Y') : '—' }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Последний вход</dt>
                                    <dd class="mt-1.5 text-sm text-white">{{ $player['last_logoff_at'] ? \Carbon\CarbonImmutable::parse($player['last_logoff_at'])->diffForHumans() : '—' }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Economy ban</dt>
                                    <dd class="mt-1.5 text-sm font-semibold {{ $player['economy_ban'] !== 'none' ? 'text-rose-300' : 'text-emerald-300' }}">{{ $player['economy_ban'] }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Community ban</dt>
                                    <dd class="mt-1.5 text-sm font-semibold {{ $player['community_banned'] ? 'text-rose-300' : 'text-emerald-300' }}">{{ $player['community_banned'] ? 'Да' : 'Нет' }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Последний бан</dt>
                                    <dd class="mt-1.5 text-sm text-white">{{ $player['days_since_last_ban'] > 0 ? $player['days_since_last_ban'].' дней назад' : '—' }}</dd>
                                </div>
                                <div class="bg-ink-900/95 p-4">
                                    <dt class="label">Регион</dt>
                                    <dd class="mt-1.5 text-sm text-white">{{ $player['country_code'] ? mb_strtoupper($player['country_code']) : '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
