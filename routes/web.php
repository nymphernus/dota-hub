<?php

use App\Http\Controllers\Auth\SteamAuthController;
use App\Http\Controllers\DotaPlayerController;
use App\Http\Controllers\HeroPlayerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/auth/steam', [SteamAuthController::class, 'redirect'])->name('auth.steam');
Route::get('/auth/steam/callback', [SteamAuthController::class, 'callback'])->name('auth.steam.callback');
Route::post('/auth/steam/logout', [SteamAuthController::class, 'logout'])->name('auth.steam.logout');

Route::post('/search', SearchController::class)
    ->middleware('throttle:20,1')
    ->name('search');

Route::get('/dota/player/{accountId}', [DotaPlayerController::class, 'show'])
    ->whereNumber('accountId')
    ->name('dota.player');

Route::get('/dota/player/{accountId}/matches', [DotaPlayerController::class, 'matches'])
    ->whereNumber('accountId')
    ->name('dota.player.matches');

Route::get('/dota/player/{accountId}/hero/{heroId}', [HeroPlayerController::class, 'show'])
    ->whereNumber('accountId')
    ->whereNumber('heroId')
    ->name('dota.player.hero');

Route::get('/dota/match/{matchId}', [MatchController::class, 'show'])
    ->whereNumber('matchId')
    ->name('dota.match');

Route::post('/dota/player/{accountId}/refresh', [DotaPlayerController::class, 'refresh'])
    ->whereNumber('accountId')
    ->middleware('throttle:5,1')
    ->name('dota.player.refresh');
