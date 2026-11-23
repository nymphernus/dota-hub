@extends('layouts.app')

@section('title', $title.' — DOTAHUB')

@section('content')
    <div class="mx-auto flex w-full max-w-3xl flex-col items-center px-4 py-20 text-center sm:px-6 lg:px-8">
        <span class="grid h-16 w-16 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7.5v5M12 16.2v.3" stroke-linecap="round" />
            </svg>
        </span>

        <h1 class="mt-6 text-2xl font-semibold text-mist-200 sm:text-3xl">{{ $title }}</h1>
        <p class="mt-3 max-w-xl text-sm leading-relaxed text-mist-400">{{ $message }}</p>

        <div class="mt-8 flex flex-wrap justify-center gap-2">
            <a href="{{ $backUrl ?? route('home') }}" class="btn-primary">{{ __('ui.back_to_search') }}</a>
            <a href="{{ route('home') }}#search" class="btn-ghost">{{ __('ui.find_another_player') }}</a>
        </div>
    </div>
@endsection
