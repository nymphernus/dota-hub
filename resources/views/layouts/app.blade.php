<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'DotaHub — быстрая статистика игроков Dota 2. Профили, матчи, герои и активность.')">
    <meta name="theme-color" content="#111318">

    <title>@yield('title', 'DotaHub')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ctext x='16' y='24' font-family='Arial,Helvetica,sans-serif' font-size='24' font-weight='700' fill='%23d1d5db' text-anchor='middle'%3ED%3C/text%3E%3C/svg%3E">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-[#2d313e] bg-[#111318]/90 backdrop-blur-md">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center px-4 sm:px-6 lg:px-8">
            <a href="/" class="bg-[#d32f2f] px-2 py-1 text-white font-black uppercase text-xl tracking-tighter leading-none rounded-sm" aria-label="DOTAHUB — на главную">DOTAHUB</a>

            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                <form
                    method="POST"
                    action="{{ route('search') }}"
                    role="search"
                    class="header-search"
                    x-data="{ open: false }"
                    :class="open ? 'is-open' : ''"
                >
                    @csrf
                    <button
                        type="button"
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-none text-mist-400 transition hover:bg-ink-700 hover:text-mist-200"
                        @click="open = !open; if (open) $nextTick(() => $refs.query.focus())"
                        :aria-expanded="open.toString()"
                        aria-controls="header-search-query"
                        aria-label="Поиск игрока"
                        title="Поиск игрока"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" stroke-linecap="round" />
                        </svg>
                    </button>
                    <input
                        id="header-search-query"
                        x-ref="query"
                        type="search"
                        name="query"
                        minlength="2"
                        maxlength="120"
                        required
                        autocomplete="off"
                        placeholder="Steam ID, URL или никнейм"
                        aria-label="Поиск игрока"
                        class="h-9 min-w-0 bg-transparent p-0 text-xs text-mist-200 placeholder:text-mist-500 transition-all duration-200 focus:outline-none "
                        :class="open ? 'w-48 px-1 sm:w-64' : 'pointer-events-none w-0 opacity-0'"
                        @keydown.escape.window="if (open) { open = false; $refs.query.blur(); }"
                    >
                </form>

                @php
                    $steamSessionId = (string) session('steam_id', '');
                    $steamAccountId = \App\Support\SteamId::isSteamId64($steamSessionId)
                        ? \App\Support\SteamId::accountIdFromSteamId64($steamSessionId)
                        : null;
                @endphp

                @if ($steamAccountId)
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('dota.player', ['accountId' => $steamAccountId]) }}" class="group flex items-center gap-2.5 rounded-none px-1 py-1 transition hover:bg-ink-700" aria-label="Открыть мой профиль">
                            @if (session('steam_avatar'))
                                <img src="{{ session('steam_avatar') }}" alt="Аватар Steam" class="h-8 w-8 rounded-none object-cover   transition ">
                            @endif
                            <span class="hidden max-w-[140px] truncate text-xs font-semibold text-mist-200 transition group-hover:text-mist-200 sm:block">
                                {{ session('steam_nickname') ?: 'Steam аккаунт' }}
                            </span>
                        </a>

                        <a href="{{ route('favorites.index') }}" class="btn-ghost !px-2.5 !py-2" aria-label="Избранное" title="Избранное">
                            <svg class="h-4 w-4 text-amber-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
                            <span class="hidden sm:inline">Избранное</span>
                        </a>

                        <form method="POST" action="{{ route('auth.steam.logout') }}">
                            @csrf
                            <input type="hidden" name="intended" value="{{ url()->current() }}">
                            <button type="submit" class="btn-ghost !py-2">Выйти</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('auth.steam', ['intended' => url()->current()]) }}" class="btn-ghost !py-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2a10 10 0 0 0-9.97 9.24l5.35 2.21a2.8 2.8 0 0 1 1.6-.49l2.37-3.43v-.5a3.74 3.74 0 1 1 3.74 3.74h-.1l-3.37 2.42c0 .07.01.14.01.21a2.8 2.8 0 0 1-5.58.37l-3.83-1.58A10 10 0 1 0 12 2Zm-5.6 15.08 1.2-.5a2.1 2.1 0 1 0-1.2.5Zm11.16-8.13a2.5 2.5 0 1 0-4.98.2 2.5 2.5 0 0 0 4.98-.2Z"/>
                        </svg>
                        Войти через Steam
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="flex-1 pb-20">
        @if (session('status'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8" x-data="{ visible: true }">
                <div x-show="visible" x-transition class="flex items-start justify-between gap-4 rounded-none border border-gold-500/25 bg-gold-500/10 px-4 py-3 text-sm text-gold-200">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 12.5l4.5 4.5L19 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <p>{{ session('status') }}</p>
                    </div>
                    <button type="button" @click="visible = false" class="rounded-none p-1 text-gold-300/70 transition hover:bg-ink-700 hover:text-mist-200" aria-label="Закрыть уведомление">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8" x-data="{ visible: true }">
                <div x-show="visible" x-transition class="flex items-start justify-between gap-4 rounded-none border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5M12 16.5v.01" stroke-linecap="round" />
                        </svg>
                        <p>{{ session('error') }}</p>
                    </div>
                    <button type="button" @click="visible = false" class="rounded-none p-1 text-rose-300/70 transition hover:bg-ink-700 hover:text-mist-200" aria-label="Закрыть уведомление">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-line bg-ink-950/60">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div>
                <p class="inline-flex bg-[#1c1e26] border border-[#2d313e] px-3 py-1.5 rounded-none font-bold text-xl tracking-tighter text-white">dotahub</p>
                <p class="text-[11px] text-mist-500">Данные: OpenDota · Steam Web API</p>
            </div>

            <p class="text-[11px] text-mist-500">&copy; {{ date('Y') }} dotahub</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
