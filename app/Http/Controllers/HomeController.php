<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiUnavailableException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use App\Support\SteamId;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, OpenDotaService $openDota): View
    {
        $steamId = (string) $request->session()->get('steam_id', '');

        if (! SteamId::isSteamId64($steamId)) {
            return view('home', [
                'dashboard' => null,
                'pageTitle' => __('ui.home'),
            ]);
        }

        $accountId = SteamId::accountIdFromSteamId64($steamId);

        try {
            $player = $openDota->player($accountId);
        } catch (PlayerNotFoundException|ApiRateLimitException|ApiUnavailableException|ExternalApiException) {
            $player = null;
        }

        $dashboard = [
            'account_id' => $accountId,
            'steam_id' => $steamId,
            'nickname' => $player['nickname'] ?? ($request->session()->get('steam_nickname') ?: 'Steam аккаунт'),
            'avatar' => $player['avatar'] ?? $request->session()->get('steam_avatar'),
            'mmr_estimate' => $player['mmr_estimate'] ?? null,
            'mmr_is_estimated' => $player['mmr_is_estimated'] ?? false,
            'rank_label' => $player['rank_label'] ?? null,
            'recent_matches' => $player !== null ? array_slice($player['matches'] ?? [], 0, 3) : [],
            'available' => $player !== null,
        ];

        return view('home', [
            'dashboard' => $dashboard,
            'pageTitle' => __('ui.home'),
        ]);
    }
}
