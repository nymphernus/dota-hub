<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Stats Radar — быстрая статистика игроков Dota 2 и CS2. Профили, матчи, герои, VAC-баны и время в игре.')">
    <meta name="theme-color" content="#05080f">

    <title>@yield('title', 'Stats Radar — статистика игроков Dota 2 и CS2')</title>

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23080d18'/%3E%3Ccircle cx='16' cy='16' r='9' fill='none' stroke='%2334d399' stroke-width='2'/%3E%3Ccircle cx='16' cy='16' r='3' fill='%2334d399'/%3E%3Cpath d='M16 16l6-6' stroke='%2334d399' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-white/5 bg-ink-950/80 backdrop-blur-xl">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="group flex items-center gap-3" aria-label="Stats Radar — на главную">
                <span class="relative grid h-10 w-10 place-items-center overflow-hidden rounded-xl border border-radar-500/30 bg-radar-500/10">
                    <span class="absolute inset-0 bg-radar-500/10 blur-xl"></span>
                    <svg class="relative h-6 w-6 text-radar-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="12" cy="12" r="8.25" opacity=".55" />
                        <circle cx="12" cy="12" r="4.5" opacity=".8" />
                        <circle cx="12" cy="12" r="1.1" fill="currentColor" stroke="none" />
                        <path d="M12 12l6.5-6.5" stroke-linecap="round" />
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-sm font-black tracking-[0.14em] text-white transition group-hover:text-radar-200">STATS RADAR</span>
                    <span class="block text-[10px] uppercase tracking-[0.28em] text-mist-500">Dota 2 · CS2</span>
                </span>
            </a>
        </div>
    </header>

    <main class="flex-1 pb-20">
        @if (session('status'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8" x-data="{ visible: true }">
                <div x-show="visible" x-transition class="flex items-start justify-between gap-4 rounded-2xl border border-radar-500/25 bg-radar-500/10 px-4 py-3 text-sm text-radar-200">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-radar-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 12.5l4.5 4.5L19 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <p>{{ session('status') }}</p>
                    </div>
                    <button type="button" @click="visible = false" class="rounded-lg p-1 text-radar-300/70 transition hover:bg-white/5 hover:text-white" aria-label="Закрыть уведомление">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8" x-data="{ visible: true }">
                <div x-show="visible" x-transition class="flex items-start justify-between gap-4 rounded-2xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5M12 16.5v.01" stroke-linecap="round" />
                        </svg>
                        <p>{{ session('error') }}</p>
                    </div>
                    <button type="button" @click="visible = false" class="rounded-lg p-1 text-rose-300/70 transition hover:bg-white/5 hover:text-white" aria-label="Закрыть уведомление">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-white/5 bg-ink-950/60">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <span class="grid h-8 w-8 place-items-center rounded-lg border border-white/10 bg-white/5">
                    <svg class="h-4 w-4 text-radar-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="8" opacity=".6" />
                        <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
                        <path d="M12 12l6-6" stroke-linecap="round" />
                    </svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-slate-300">Stats Radar</p>
                    <p class="text-[11px] text-mist-500">Данные: OpenDota · Steam Web API</p>
                </div>
            </div>

            <p class="max-w-xl text-[11px] leading-relaxed text-mist-500">
                Проект не аффилирован с Valve Corporation. Dota 2 и Counter-Strike 2 — товарные знаки Valve.
                Статистика предоставляется как есть и обновляется из кэша.
            </p>

            <p class="text-[11px] text-mist-500">&copy; {{ date('Y') }} Stats Radar</p>
        </div>
    </footer>
</body>
</html>
