<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\MatchNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function show(int $matchId, Request $request, OpenDotaService $openDota): View|RedirectResponse
    {
        try {
            $match = $openDota->getMatch($matchId);
        } catch (MatchNotFoundException) {
            return back()->with('error', 'Матч не найден.');
        } catch (ApiRateLimitException|ExternalApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return view('dota.match', [
            'match' => $match,
            'highlightPlayerId' => $request->integer('player_id') ?: null,
        ]);
    }
}
