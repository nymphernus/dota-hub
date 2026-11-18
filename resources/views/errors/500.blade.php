@extends('layouts.app')

@section('title', 'Ошибка сервера — Stats Radar')
@section('description', 'Не удалось загрузить данные Stats Radar.')

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute left-1/2 top-1/3 h-[34rem] w-[34rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-rose-500/[0.08] blur-3xl"></div>
            <div class="absolute left-[-8rem] bottom-0 h-72 w-72 rounded-full bg-amber-500/[0.05] blur-3xl"></div>
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full border border-rose-500/20 bg-rose-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-300">
                Ошибка 500
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-2xl border border-rose-500/20 bg-rose-500/[0.07] text-rose-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M12 3l9 16H3L12 3z" stroke-linejoin="round" />
                    <path d="M12 9v4.5M12 16.8v.2" stroke-linecap="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-black tracking-tight text-white sm:text-4xl">
                Ошибка сервера
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-400">
                Не удалось загрузить данные. Попробуйте обновить страницу через минуту — если ошибка повторится, проверьте логи приложения.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <a href="{{ route('home') }}" class="btn-primary">Вернуться на главную</a>
                <button type="button" onclick="window.location.reload()" class="btn-ghost">Обновить страницу</button>
            </div>
        </div>
    </div>
@endsection
