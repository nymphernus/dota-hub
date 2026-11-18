<?php

namespace App\Http\Controllers;

use App\Exceptions\ExternalApiException;
use App\Exceptions\MissingApiKeyException;
use App\Exceptions\PlayerNotFoundException;
use App\Models\PlayerSearch;
use App\Services\Steam\SteamService;
use App\Support\SteamId;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class Cs2PlayerController extends Controller
{
    public function show(string $steamId, SteamService $steam): View|Response
    {
        if (! SteamId::isSteamId64($steamId)) {
            return response()->view('errors.player', [
                'title' => 'Некорректный SteamID64',
                'message' => 'SteamID64 должен состоять из 17 цифр и начинаться с 7656119.',
                'backUrl' => route('home'),
            ], 404);
        }

        try {
            $player = $steam->player($steamId);
        } catch (MissingApiKeyException $exception) {
            return response()->view('cs2.player', [
                'player' => null,
                'steamId' => $steamId,
                'error' => $exception->getMessage(),
            ], 503);
        } catch (PlayerNotFoundException $exception) {
            return response()->view('errors.player', [
                'title' => 'Игрок не найден',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 404);
        } catch (ExternalApiException $exception) {
            return response()->view('errors.player', [
                'title' => 'Steam API недоступна',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 502);
        }

        PlayerSearch::track(
            PlayerSearch::GAME_CS2,
            $steamId,
            $player['nickname'],
            $player['avatar'],
        );

        return view('cs2.player', [
            'player' => $player,
            'steamId' => $steamId,
            'error' => null,
        ]);
    }

    public function refresh(string $steamId, SteamService $steam): RedirectResponse
    {
        $steam->forget($steamId);

        return redirect()
            ->route('cs2.player', ['steamId' => $steamId])
            ->with('status', 'Кэш очищен — загружаем свежие данные из Steam.');
    }
}
