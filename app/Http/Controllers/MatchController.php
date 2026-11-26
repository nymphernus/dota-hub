<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\MatchNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MatchController extends Controller
{
    public function show(int $matchId, OpenDotaService $openDota): View|RedirectResponse
    {
        try {
            // getMatch() uses Cache::remember: a missing or expired detail cache
            // entry triggers the OpenDota match request on this page only.
            $match = $openDota->getMatch($matchId);
        } catch (MatchNotFoundException) {
            return back()->with('error', __('ui.match_not_found'));
        } catch (ApiRateLimitException|ExternalApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $playerId = request()->integer('player_id') ?: null;
        $players = array_merge($match['players']['radiant'] ?? [], $match['players']['dire'] ?? []);
        $matchedPlayer = $playerId !== null
            ? collect($players)->first(fn (array $player): bool => (int) ($player['account_id'] ?? 0) === $playerId)
            : null;

        // Only the account from ?player_id becomes the profile context in the
        // layout. Never fall back to the first or last player in the match.
        $profilePlayer = is_array($matchedPlayer) ? [
            'account_id' => $playerId,
            'nickname' => $matchedPlayer['nickname'] ?? null,
            'avatar' => $matchedPlayer['avatar'] ?? null,
        ] : null;

        return view('dota.match', [
            'match' => $match,
            'pageTitle' => __('ui.match').' #'.$match['match_id'].' - DOTAHUB',
            'playerId' => $playerId,
            'profilePlayer' => $profilePlayer,
        ]);
    }
}
