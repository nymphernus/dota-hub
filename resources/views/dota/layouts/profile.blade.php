@extends('layouts.app')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <x-player-header :player="$player" :steamProfile="$steamProfile" :isFavorite="$isFavorite ?? false" />

        @if (!empty($player['api_warning']))
            <div class="mt-4 border border-yellow-500/40 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-200">
                {{ $player['api_warning'] }}
            </div>
        @endif

        @yield('profile-content')
    </div>
@endsection
