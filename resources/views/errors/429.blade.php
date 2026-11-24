@extends('layouts.app')

@section('title', '429 - '.__('ui.too_many_requests').' - DOTAHUB')
@section('description', __('ui.too_many_requests'))

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-none border border-accent-500/20 bg-accent-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-accent-300">
                429
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-none border border-accent-500/20 bg-accent-500/[0.07] text-accent-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
                {{ __('ui.too_many_requests') }}
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-400">
                {{ __('ui.rate_limit_hint') }}
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <a href="{{ route('home') }}" class="btn-primary">{{ __('ui.back_home') }}</a>
                <button type="button" onclick="window.location.reload()" class="btn-ghost">{{ __('ui.try_again') }}</button>
            </div>
        </div>
    </div>
@endsection
