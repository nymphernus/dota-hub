@extends('layouts.app')

@section('title', 'Игрок или матч не найден — Stats Radar')
@section('description', 'Запрошенный игрок, матч или страница не найдены.')

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute left-1/2 top-1/3 h-[34rem] w-[34rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-radar-500/[0.08] blur-3xl"></div>
            <div class="absolute right-[-8rem] bottom-0 h-72 w-72 rounded-full bg-cyan-500/[0.06] blur-3xl"></div>
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full border border-radar-500/20 bg-radar-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-radar-300">
                Ошибка 404
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-2xl border border-radar-500/20 bg-radar-500/[0.07] text-radar-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5" />
                    <path d="M16 16l4.5 4.5M8.5 11h5" stroke-linecap="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-black tracking-tight text-white sm:text-4xl">
                Игрок или матч не найден
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-400">
                Проверьте SteamID64, ссылку на профиль или адрес страницы. Возможно, профиль закрыт или данные ещё не проиндексированы.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <a href="{{ route('home') }}" class="btn-primary">Вернуться на главную</a>
                <a href="{{ route('home') }}#search" class="btn-ghost">К поиску игрока</a>
            </div>
        </div>
    </div>
@endsection
