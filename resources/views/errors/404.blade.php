@extends('layouts.app')

@section('title', '404 - '.__('ui.not_found_title').' - DOTAHUB').' - DOTAHUB')
@section('description', __('ui.not_found_desc'))

@section('content')
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-8rem)] w-full max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-none border border-accent-500/20 bg-accent-500/[0.08] px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-accent-300">
                404
            </span>

            <span class="mt-6 grid h-16 w-16 place-items-center rounded-none border border-accent-500/20 bg-accent-500/[0.07] text-accent-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5" />
                    <path d="M16 16l4.5 4.5M8.5 11h5" stroke-linecap="round" />
                </svg>
            </span>

            <h1 class="mt-6 text-balance text-3xl font-semibold tracking-tight text-mist-200 sm:text-4xl">
                {{ __('ui.not_found_title') }}
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-400">
                {{ __('ui.not_found_hint') }}
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <a href="{{ route('home') }}" class="btn-primary">{{ __('ui.back_home') }}</a>
                <a href="{{ route('home') }}#search" class="btn-ghost">{{ __('ui.find_player') }}</a>
            </div>
        </div>
    </div>
@endsection
