<?php

use App\Http\Controllers\Cs2PlayerController;
use App\Http\Controllers\DotaPlayerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('/search', SearchController::class)
    ->middleware('throttle:20,1')
    ->name('search');

Route::get('/dota/player/{accountId}', [DotaPlayerController::class, 'show'])
    ->whereNumber('accountId')
    ->name('dota.player');

Route::get('/dota/match/{matchId}', [MatchController::class, 'show'])
    ->whereNumber('matchId')
    ->name('dota.match');

Route::post('/dota/player/{accountId}/refresh', [DotaPlayerController::class, 'refresh'])
    ->whereNumber('accountId')
    ->middleware('throttle:5,1')
    ->name('dota.player.refresh');

Route::get('/cs2/player/{steamId}', [Cs2PlayerController::class, 'show'])
    ->where('steamId', '[0-9]{17}')
    ->name('cs2.player');

Route::post('/cs2/player/{steamId}/refresh', [Cs2PlayerController::class, 'refresh'])
    ->where('steamId', '[0-9]{17}')
    ->middleware('throttle:5,1')
    ->name('cs2.player.refresh');
