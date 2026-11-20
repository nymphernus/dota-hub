@extends('layouts.app')

@section('title', 'Не удалось загрузить данные — DotaHub')
@section('description', 'OpenDota временно недоступна. Попробуйте обновить страницу позже.')

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-lg border border-sky-500/25 bg-sky-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-300">
                Ошибка сети · 502
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-lg border border-sky-500/20 bg-sky-500/[0.07] text-sky-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M7 18h10a4 4 0 0 0 .7-7.9A6 6 0 0 0 6.1 8.3 4.5 4.5 0 0 0 7 18z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M9 9l6 6M15 9l-6 6" stroke-linecap="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
                Не удалось загрузить данные
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-300">
                {{ $message ?? 'Сервера OpenDota временно недоступны. Попробуйте обновить страницу позже.' }}
            </p>
            <p class="mt-2 max-w-xl text-xs leading-relaxed text-mist-500">
                Проверьте подключение к интернету. Если ошибка повторяется, возможно, OpenDota временно недоступна.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <button type="button" onclick="window.location.reload()" class="btn-primary">Повторить запрос</button>
                <a href="{{ $backUrl ?? route('home') }}" class="btn-ghost">Вернуться на главную</a>
            </div>
        </div>
    </div>
@endsection
