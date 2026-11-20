@extends('layouts.app')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-2 text-sm text-[#8b94a5]" aria-label="Хлебные крошки">
            <a href="{{ route('home') }}" class="transition hover:text-white">Главная</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('dota.player', ['accountId' => $player['account_id']]) }}" class="transition hover:text-white">{{ $player['nickname'] }}</a>
            <span aria-hidden="true">/</span>
            <span class="font-medium text-white" aria-current="page">{{ $profileSection }}</span>
        </nav>

        <x-player-header :player="$player" :steamProfile="$steamProfile" :isFavorite="$isFavorite ?? false" />

        <x-player-metrics :player="$player" />

        <x-player-tabs :player="$player" :active="$activeTab" />

        @yield('profile-content')
    </div>
@endsection
