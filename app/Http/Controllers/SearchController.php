<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\MissingApiKeyException;
use App\Exceptions\PlayerNotFoundException;
use App\Jobs\WarmDotaProfile;
use App\Services\PlayerSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    public function __invoke(Request $request, PlayerSearchService $searchService): RedirectResponse
    {
        $validated = $request->validate(
            [
                'query' => ['required', 'string', 'min:2', 'max:120'],
                'game' => ['required', 'string', Rule::in(['dota', 'cs2'])],
            ],
            [
                'query.required' => 'Введите ссылку на Steam-профиль, SteamID64 или никнейм.',
                'query.min' => 'Поисковый запрос слишком короткий.',
                'query.max' => 'Поисковый запрос слишком длинный.',
                'game.required' => 'Выберите игру.',
                'game.in' => 'Поддерживаются только Dota 2 и CS2.',
            ]
        );

        try {
            $target = $searchService->resolve($validated['query'], $validated['game']);
        } catch (PlayerNotFoundException|MissingApiKeyException $exception) {
            return back()
                ->withInput()
                ->withErrors(['query' => $exception->getMessage()]);
        } catch (ApiRateLimitException $exception) {
            return back()
                ->withInput()
                ->withErrors(['query' => $exception->getMessage()]);
        } catch (ExternalApiException $exception) {
            return back()
                ->withInput()
                ->withErrors(['query' => $exception->getMessage()]);
        }

        if ($target['game'] === 'dota') {
            // Prefetch the profile in the background via Redis queues.
            WarmDotaProfile::dispatch((int) $target['account_id'])->afterResponse();

            return redirect()->route('dota.player', ['accountId' => $target['account_id']]);
        }

        return redirect()->route('cs2.player', ['steamId' => $target['steam_id']]);
    }
}
