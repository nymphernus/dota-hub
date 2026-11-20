@extends('layouts.app')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <x-player-header :player="$player" :steamProfile="$steamProfile" :isFavorite="$isFavorite ?? false" />

        <x-player-metrics :player="$player" />

        @yield('profile-content')
    </div>
@endsection
