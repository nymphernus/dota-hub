<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'DOTAHUB — быстрая статистика игроков Dota 2. Профили, матчи, герои и активность.')">
    <meta name="theme-color" content="#111318">

    <title>{{ $pageTitle ?? 'DOTAHUB' }} - DOTAHUB</title>

    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'light') document.documentElement.classList.remove('dark');
                else document.documentElement.classList.add('dark');
            } catch (e) { document.documentElement.classList.add('dark'); }
        })();
    </script>

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
        $currentLocale = app()->getLocale();
    @endphp
    <header class="sticky top-0 z-50 border-b border-[#2d313e] bg-[#111318]/90 backdrop-blur-md dark:bg-[#111318]/90 header-shell">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <a href="/" class="bg-[#d32f2f] px-2 py-1 text-white font-light uppercase text-2xl leading-none rounded-sm tracking-tighter">DOTAHUB</a>
                @if ($showProfileNav && $navNickname)
                    <span class="min-w-0 truncate text-sm font-semibold text-white">{{ $navNickname }}</span>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-3 sm:gap-4">
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
                        aria-label="{{ __('ui.search_player') }}"
                        title="{{ __('ui.search_player') }}"
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
                        placeholder="{{ __('ui.search_placeholder') }}"
                        aria-label="{{ __('ui.search_player') }}"
                        class="h-9 min-w-0 bg-transparent p-0 text-xs text-mist-200 placeholder:text-mist-500 transition-all duration-200 focus:outline-none"
                        :class="open ? 'w-48 px-1 sm:w-64' : 'pointer-events-none w-0 opacity-0'"
                        :tabindex="open ? 0 : -1"
                    >
                </form>

                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" class="flex items-center gap-1.5 h-9 px-2 rounded-none text-mist-400 transition hover:bg-ink-700 hover:text-white" :aria-expanded="open.toString()" aria-haspopup="true" title="{{ __('ui.language') }}">
                        <span class="inline-flex items-center">{{ $currentLocale === 'en' ? '<svg class="h-4 w-6 rounded-[2px]" viewBox="0 0 60 30" aria-hidden="true"><clipPath id="gb"><rect width="60" height="30"/></clipPath><g clip-path="url(#gb)"><rect width="60" height="30" fill="#012169"/><path d="M0,0 60,30 M60,0 0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 60,30 M60,0 0,30" stroke="#C8102E" stroke-width="4" clip-path="url(#gb)"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg>' : '<svg class="h-4 w-6 rounded-[2px]" viewBox="0 0 3 2" aria-hidden="true"><rect width="3" height="2" fill="#fff"/><rect width="3" height="1.333" y="0.333" fill="#0039a6"/><rect width="3" height="0.667" y="1.333" fill="#d52b1e"/></svg>' }}</span>
                        <svg class="h-3 w-3 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute right-0 top-10 z-50 w-40 border border-line bg-ink-800 py-1 shadow-lg">
                        <a href="{{ route('lang.switch', ['locale' => 'ru']) }}" class="flex items-center gap-2 px-3 py-2 text-sm {{ $currentLocale === 'ru' ? 'text-white bg-ink-700' : 'text-mist-300 hover:bg-ink-700 hover:text-white' }}">
                            <span class="inline-flex items-center"><svg class="h-4 w-6 rounded-[2px]" viewBox="0 0 3 2" aria-hidden="true"><rect width="3" height="2" fill="#fff"/><rect width="3" height="1.333" y="0.333" fill="#0039a6"/><rect width="3" height="0.667" y="1.333" fill="#d52b1e"/></svg></span><span>{ __('ui.russian') }</span>
                        </a>
                        <a href="{{ route('lang.switch', ['locale' => 'en']) }}" class="flex items-center gap-2 px-3 py-2 text-sm {{ $currentLocale === 'en' ? 'text-white bg-ink-700' : 'text-mist-300 hover:bg-ink-700 hover:text-white' }}">
                            <span class="inline-flex items-center"><svg class="h-4 w-6 rounded-[2px]" viewBox="0 0 60 30" aria-hidden="true"><clipPath id="gb"><rect width="60" height="30"/></clipPath><g clip-path="url(#gb)"><rect width="60" height="30" fill="#012169"/><path d="M0,0 60,30 M60,0 0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 60,30 M60,0 0,30" stroke="#C8102E" stroke-width="4" clip-path="url(#gb)"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg></span><span>{ __('ui.english') }</span>
                        </a>
                    </div>
                </div>

                <button
                    type="button"
                    id="theme-toggle"
                    class="grid h-9 w-9 place-items-center rounded-none text-mist-400 transition hover:bg-ink-700 hover:text-white"
                    aria-label="{{ __('ui.theme') }}"
                    title="{{ __('ui.theme') }}"
                >
                    <svg id="theme-icon-sun" class="h-4 w-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke-linecap="round"/></svg>
                    <svg id="theme-icon-moon" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                @php
                    $steamId = (string) session('steam_id', '');
                    $steamAccountId = null;
                    if (preg_match('/^\d{17}$/', $steamId) === 1) {
                        $steamAccountId = (string) ((int) $steamId - 76561197960265728);
                    }
                @endphp
                @if ($steamAccountId)
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('dota.player', ['accountId' => $steamAccountId]) }}" class="group flex items-center gap-2.5 rounded-none px-1 py-1 transition hover:bg-ink-700" aria-label="{{ __('ui.my_profile') }}">
                            @if (session('steam_avatar'))
                                <img src="{{ session('steam_avatar') }}" alt="Steam" class="h-8 w-8 rounded-none object-cover transition">
                            @endif
                            <span class="hidden max-w-[140px] truncate text-xs font-semibold text-mist-200 transition group-hover:text-mist-200 sm:block">
                                {{ session('steam_nickname') ?: __('ui.steam_account') }}
                            </span>
                        </a>

                        <a href="{{ route('favorites.index') }}" class="btn-ghost !px-2.5 !py-2" aria-label="{{ __('ui.favorites') }}" title="{{ __('ui.favorites') }}">
                            <svg class="h-4 w-4 text-accent-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
                            <span class="hidden sm:inline">{{ __('ui.favorites') }}</span>
                        </a>

                        <form action="{{ route('auth.steam.logout') }}" method="POST">
                            @csrf
                            <input type="hidden" name="intended" value="{{ url()->current() }}">
                            <button type="submit" class="btn-ghost !py-2">{{ __('ui.logout') }}</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('auth.steam', ['intended' => url()->current()]) }}" class="btn-primary">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2a10 10 0 0 0-9.97 9.24l5.35 2.21a2.8 2.8 0 0 1 1.6-.49l2.37-3.43v-.5a3.74 3.74 0 1 1 3.74 3.74h-.1l-3.37 2.42c0 .07.01.14.01.21a2.8 2.8 0 0 1-5.58.37l-3.83-1.58A10 10 0 1 0 12 2Zm-5.6 15.08 1.2-.5a2.1 2.1 0 1 0-1.2.5Zm11.16-8.13a2.5 2.5 0 1 0-4.98.2 2.5 2.5 0 0 0 4.98-.2Z"/>
                        </svg>
                        {{ __('ui.login_steam') }}
                    </a>
                @endif
            </div>
        </div>

        @if ($showProfileNav)
            <nav class="border-t border-[#2d313e] bg-[#1c1e26] nav-shell" aria-label="Navigation">
                <div class="mx-auto flex w-full max-w-7xl items-center gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
                    @foreach ([
                        'dota.player' => __('ui.overview'),
                        'dota.player.matches' => __('ui.matches'),
                        'dota.player.heroes' => __('ui.heroes'),
                        'dota.player.records' => __('ui.records'),
                        'dota.player.peers' => __('ui.allies'),
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
                            {{ $navRoute === 'dota.match' ? 'Match #'.request()->route('matchId') : ($navHero['name'] ?? 'Hero') }}
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
                    <button type="button" @click="visible = false" class="rounded-none p-1 text-accent-300/70 transition hover:bg-ink-700 hover:text-mist-200" aria-label="{{ __('ui.close_notification') }}">
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

    <script>
        (function () {
            var btn = document.getElementById('theme-toggle');
            var sun = document.getElementById('theme-icon-sun');
            var moon = document.getElementById('theme-icon-moon');
            function sync() {
                var dark = document.documentElement.classList.contains('dark');
                if (sun) sun.classList.toggle('hidden', dark);
                if (moon) moon.classList.toggle('hidden', !dark);
            }
            sync();
            if (btn) btn.addEventListener('click', function () {
                var dark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
                sync();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
