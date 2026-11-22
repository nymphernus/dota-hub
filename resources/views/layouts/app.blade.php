<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'DotaHub — быстрая статистика игроков Dota 2. Профили, матчи, герои и активность.')">
    <meta name="theme-color" content="#111318">

    <title>@yield('title', 'DOTAHUB')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%23dc2626'/%3E%3Ctext x='32' y='44' font-family='Arial,sans-serif' font-size='36' font-weight='700' fill='white' text-anchor='middle'%3ED%3C/text%3E%3C/svg%3E">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    @php
        $navRoute = request()->route()?->getName();
        $navPlayer = $player ?? null;
        $navHero = $hero ?? null;
        $navNickname = $navPlayer['nickname'] ?? null;
        $navAccountId = (int) ($navPlayer['account_id'] ?? request()->route('accountId') ?? 0);
        $showProfileNav = $navAccountId > 0 && $navNickname;
    @endphp
    <header class="sticky top-0 z-50 border-b border-[#2d313e] bg-[#111318]/90 backdrop-blur-md">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <a href="/" class="bg-[#d32f2f] px-2 py-0.5 text-white font-mono uppercase text-xl tracking-tighter leading-none rounded-sm">DOTAHUB</a>
                @if ($showProfileNav && $navNickname)
                    <span class="min-w-0 truncate text-sm font-semibold text-white">{{ $navNickname }}</span>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-4">
                <form
                    x-data="{ open: false }"
                    x-cloak
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    action="{{ route('search') }}"
                    method="POST"
                    role="search"
                    class="flex items-center"
                >
                    @csrf
                    <button
                        type="button"
                        @click="open = !open; if (open) { $nextTick(() => $refs.query.focus()) }"
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-none text-mist-400 transition hover:bg-ink-700 hover:text-white"
                        :aria-expanded="open.toString()"
                        aria-controls="header-search-query"
                        aria-label="Поиск игрока"
                        title="Поиск игрока"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.5-3.5" stroke-linecap="round"></path>
                        </svg>
                    </button>

                    <input
                        x-ref="query"
                        id="header-search-query"
                        type="text"
                        name="query"
                        value="{{ old('query', request('q', '')) }}"
                        required
                        autocomplete="off"
                        placeholder="Steam ID, URL или никнейм"
                        aria-label="Поиск игрока"
                        class="h-9 min-w-0 bg-transparent p-0 text-xs text-mist-200 placeholder:text-mist-500 transition-all duration-200 focus:outline-none"
                        :class="open ? 'w-48 px-1 sm:w-64' : 'pointer-events-none w-0 opacity-0'"
                        :tabindex="open ? 0 : -1"
                    >
                </form>

                @php
                    $steamId = (string) session('steam_id', '');
                    $steamAccountId = null;
                    if (preg_match('/^\d{17}$/', $steamId) === 1) {
                        $steamAccountId = (string) ((int) $steamId - 76561197960265728);
                    }
                @endphp
                @if ($steamAccountId)
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('dota.player', ['accountId' => $steamAccountId]) }}" class="group flex items-center gap-2.5 rounded-none px-1 py-1 transition hover:bg-ink-700" aria-label="Открыть мой профиль">
                            @if (session('steam_avatar'))
                                <img src="{{ session('steam_avatar') }}" alt="Аватар Steam" class="h-8 w-8 rounded-none object-cover transition">
                            @endif
                            <span class="hidden max-w-[140px] truncate text-xs font-semibold text-mist-200 transition group-hover:text-mist-200 sm:block">
                                {{ session('steam_nickname') ?: 'Steam аккаунт' }}
                            </span>
                        </a>

                        <a href="{{ route('favorites.index') }}" class="btn-ghost !px-2.5 !py-2" aria-label="Избранное" title="Избранное">
                            <svg class="h-4 w-4 text-accent-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
                            <span class="hidden sm:inline">Избранное</span>
                        </a>

                        <form action="{{ route('auth.steam.logout') }}" method="POST">
                            @csrf
                            <input type="hidden" name="intended" value="{{ url()->current() }}">
                            <button type="submit" class="btn-ghost !py-2">Выйти</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('auth.steam', ['intended' => url()->current()]) }}" class="btn-primary">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2a10 10 0 0 0-9.97 9.24l5.35 2.21a2.8 2.8 0 0 1 1.6-.49l2.37-3.43v-.5a3.74 3.74 0 1 1 3.74 3.74h-.1l-3.37 2.42c0 .07.01.14.01.21a2.8 2.8 0 0 1-5.58.37l-3.83-1.58A10 10 0 1 0 12 2Zm-5.6 15.08 1.2-.5a2.1 2.1 0 1 0-1.2.5Zm11.16-8.13a2.5 2.5 0 1 0-4.98.2 2.5 2.5 0 0 0 4.98-.2Z"/>
                        </svg>
                        Войти через Steam
                    </a>
                @endif
            </div>
        </div>

        @if ($showProfileNav)
            <nav class="border-t border-[#2d313e] bg-[#1c1e26]" aria-label="Основная навигация">
                <div class="mx-auto flex w-full max-w-7xl items-center gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
                                        @foreach ([
                        'dota.player' => 'Обзор',
                        'dota.player.matches' => 'Матчи',
                        'dota.player.heroes' => 'Герои',
                        'dota.player.trophies' => 'Трофеи',
                        'dota.player.records' => 'Рекорды',
                        'dota.player.peers' => 'Союзники',
                    ] as $routeName => $label)
                        <a
                            href="{{ route($routeName, ['accountId' => $navAccountId]) }}"
                            @class([
                                'shrink-0 px-3 py-2 text-xs font-semibold transition',
                                'bg-[#2d313e] text-white' => $navRoute === $routeName,
                                'text-mist-400 hover:text-mist-200' => $navRoute !== $routeName,
                            ])
                            @if ($navRoute === $routeName) aria-current="page" @endif
                        >{{ $label }}</a>
                    @endforeach

                    @if (in_array($navRoute, ['dota.match', 'dota.player.hero'], true))
                        <span class="shrink-0 bg-[#2d313e] px-3 py-2 text-xs font-semibold text-white" aria-current="page">
                            {{ $navRoute === 'dota.match' ? 'Матч #'.request()->route('matchId') : ($navHero['name'] ?? 'Герой') }}
                        </span>
                    @endif
                </div>
            </nav>
        @endif
    </header>

    <main id="main-content" class="flex-1" tabindex="-1">
        @if (session('status'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8" role="status" aria-live="polite">
                <div
                    x-data="{ visible: true }"
                    x-show="visible"
                    x-transition.opacity.duration.250ms
                    class="flex items-center justify-between gap-3 border border-accent-500/25 bg-accent-500/10 px-4 py-3 text-sm text-accent-300"
                >
                    <div class="flex items-center gap-2.5">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v4M12 16h.01" stroke-linecap="round" /></svg>
                        <p>{{ session('status') }}</p>
                    </div>
                    <button type="button" @click="visible = false" class="rounded-none p-1 text-accent-300/70 transition hover:bg-ink-700 hover:text-mist-200" aria-label="Закрыть уведомление">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round" /></svg>
                    </button>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer>
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <span class="text-xs font-semibold tracking-tight text-mist-400">DOTAHUB</span>
            <span class="text-[10px] text-mist-500">&copy; {{ date('Y') }} &middot; OpenDota &middot; Steam Web API</span>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
