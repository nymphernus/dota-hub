@extends('layouts.app')

@section('title', $pageTitle ?? 'Избранное - DotaHub')
@section('description', 'Избранные игроки Dota 2 в DotaHub.')

@section('content')
    <section class="mx-auto w-full max-w-5xl px-4 pt-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="label">Ваши игроки</p>
                <h1 class="mt-1 text-2xl font-semibold text-mist-200 sm:text-3xl">Избранное</h1>
            </div>
            <span class="badge border-accent-500/25 bg-accent-500/10 text-accent-300">{{ count($favorites) }}</span>
        </div>

        @if ($favorites === [])
            <div class="card mt-5 grid place-items-center p-12 text-center">
                <span class="grid h-12 w-12 place-items-center rounded-none border border-accent-500/25 bg-accent-500/10 text-accent-300">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/></svg>
                </span>
                <p class="mt-4 text-sm font-semibold text-mist-200">Вы ещё не добавили игроков в избранное.</p>
                <p class="mt-1 text-xs text-mist-500">Откройте профиль игрока и нажмите «В избранное».</p>
                <a href="{{ route('home') }}" class="btn-ghost mt-5">К поиску</a>
            </div>
        @else
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ($favorites as $favorite)
                    @php
                        $accountId = (int) ($favorite['account_id'] ?? 0);
                        $profileUrl = route('dota.player', ['accountId' => $accountId]);
                    @endphp
                    <div class="card flex items-center gap-3 p-3">
                        <a href="{{ $profileUrl }}" class="h-12 w-12 shrink-0 overflow-hidden rounded-none bg-ink-800  ">
                            @if (! empty($favorite['avatar']))
                                <img src="{{ $favorite['avatar'] }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $profileUrl }}" class="block truncate text-sm font-bold text-mist-200 transition hover:text-accent-300">
                                {{ $favorite['nickname'] ?: 'Игрок #'.$accountId }}
                            </a>
                            <p class="mt-0.5 text-[10px] text-mist-500">Account ID: {{ $accountId }}</p>
                        </div>
                        <form method="POST" action="{{ route('favorites.destroy', ['accountId' => $accountId]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost !px-2.5 !py-2 !text-[11px]" aria-label="Удалить {{ $favorite['nickname'] }} из избранного">
                                Удалить
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
