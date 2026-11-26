@extends('layouts.app')

@section('title', '502 - '.__('ui.bad_gateway').' - DOTAHUB')
@section('description', __('ui.server_error_desc'))

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-none border border-rose-500/20 bg-rose-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-300">
                502
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-none border border-rose-500/20 bg-rose-500/[0.07] text-rose-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M7 18h10a4 4 0 0 0 .7-7.9A6 6 0 0 0 6.1 8.3 4.5 4.5 0 0 0 7 18z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M12 10.5v3.2M12 16.5v.2" stroke-linecap="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
                {{ __('ui.bad_gateway') }}
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-400">
                {{ __('ui.bad_gateway_hint') }}
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <a href="{{ route('home') }}" class="btn-primary">{{ __('ui.back_home') }}</a>
                <button type="button" onclick="window.location.reload()" class="btn-ghost">{{ __('ui.refresh_page') }}</button>
            </div>
        </div>
    </div>
@endsection
