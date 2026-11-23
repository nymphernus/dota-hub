@extends('layouts.app')

@section('title', __('ui.api_error').' - DOTAHUB')
@section('description', __('ui.api_error_desc'))

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-none border border-line bg-[#2d313e] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-mist-300">
                502
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-none border border-line bg-[#2d313e] text-mist-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M7 18h10a4 4 0 0 0 .7-7.9A6 6 0 0 0 6.1 8.3 4.5 4.5 0 0 0 7 18z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M9 9l6 6M15 9l-6 6" stroke-linecap="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
                {{ __('ui.data_load_failed') }}
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-300">
                {{ $message ?? __('ui.api_unavailable') }}
            </p>
            <p class="mt-2 max-w-xl text-xs leading-relaxed text-mist-500">
                {{ __('ui.api_error_hint') }}
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <button type="button" onclick="window.location.reload()" class="btn-primary">{{ __('ui.try_again') }}</button>
                <a href="{{ $backUrl ?? route('home') }}" class="btn-ghost">{{ __('ui.back_home') }}</a>
            </div>
        </div>
    </div>
@endsection
