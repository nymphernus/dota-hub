<?php

use App\Http\Controllers\Auth\SteamAuthController;
use App\Http\Controllers\DotaPlayerController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HeroPlayerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['ru', 'en', 'de'], true)) {
        session(['locale' => $locale]);
    }
    return redirect()->back(fallback: route('home'));
})->whereIn('locale', ['ru', 'en', 'de'])->name('lang.switch');

Route::get('/auth/steam', [SteamAuthController::class, 'redirect'])->name('auth.steam');
Route::get('/auth/steam/callback', [SteamAuthController::class, 'callback'])->name('auth.steam.callback');
Route::post('/auth/steam/logout', [SteamAuthController::class, 'logout'])->name('auth.steam.logout');

Route::post('/search', SearchController::class)
    ->middleware('throttle:20,1')
    ->name('search');

Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
Route::delete('/favorites/{accountId}', [FavoriteController::class, 'destroy'])
    ->whereNumber('accountId')
    ->name('favorites.destroy');

Route::get('/player/{accountId}', [DotaPlayerController::class, 'show'])
    ->whereNumber('accountId')
    ->name('player');

Route::get('/player/{accountId}/matches', [DotaPlayerController::class, 'matches'])
    ->whereNumber('accountId')
    ->name('player.matches');

Route::get('/player/{accountId}/heroes', [DotaPlayerController::class, 'heroes'])
    ->whereNumber('accountId')
    ->name('player.heroes');

Route::get('/player/{accountId}/peers', [DotaPlayerController::class, 'peers'])
    ->whereNumber('accountId')
    ->name('player.peers');

Route::get('/player/{accountId}/records', [DotaPlayerController::class, 'records'])
    ->whereNumber('accountId')
    ->name('player.records');

Route::get('/player/{accountId}/hero/{heroId}', [HeroPlayerController::class, 'show'])
    ->whereNumber('accountId')
    ->whereNumber('heroId')
    ->name('player.hero');

Route::get('/match/{matchId}', [MatchController::class, 'show'])
    ->whereNumber('matchId')
    ->name('match');

Route::post('/player/{accountId}/refresh', [DotaPlayerController::class, 'refresh'])
    ->whereNumber('accountId')
    ->middleware('throttle:5,1')
    ->name('player.refresh');
