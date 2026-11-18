<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use App\Models\PlayerSearch;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class DotaPlayerController extends Controller
{
    public function show(int $accountId, OpenDotaService $openDota): View|Response
    {
        try {
            $player = $openDota->player($accountId);
        } catch (PlayerNotFoundException $exception) {
            return response()->view('errors.player', [
                'title' => 'Игрок не найден',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 404);
        } catch (ApiRateLimitException $exception) {
            return response()->view('errors.player', [
                'title' => 'Лимит API исчерпан',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 429);
        } catch (ApiUnavailableException $exception) {
            return response()->view('errors.api', [
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 502);
        } catch (ExternalApiException $exception) {
            return response()->view('errors.player', [
                'title' => 'OpenDota недоступна',
                'message' => $exception->getMessage(),
                'backUrl' => route('home'),
            ], 502);
        }

        PlayerSearch::track(
            PlayerSearch::GAME_DOTA,
            (string) $accountId,
            $player['is_private'] ? null : $player['nickname'],
            $player['avatar'],
        );

        return view('dota.player', [
            'player' => $player,
            'winrate' => $player['winrate'],
            'recentMatches' => array_slice($player['matches'], 0, 20),
        ]);
    }

    public function refresh(int $accountId, OpenDotaService $openDota): RedirectResponse
    {
        $openDota->forget($accountId);

        return redirect()
            ->route('dota.player', ['accountId' => $accountId])
            ->with('status', 'Кэш очищен — загружаем свежие данные из OpenDota.');
    }
}
