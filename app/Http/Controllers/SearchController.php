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

class SearchController extends Controller
{
    public function __invoke(Request $request, PlayerSearchService $searchService): RedirectResponse
    {
        $validated = $request->validate(
            [
                'query' => ['required', 'string', 'min:2', 'max:120'],
            ],
            [
                'query.required' => 'Введите ссылку на Steam-профиль, SteamID64 или никнейм.',
                'query.min' => 'Поисковый запрос слишком короткий.',
                'query.max' => 'Поисковый запрос слишком длинный.',
            ]
        );

        try {
            $target = $searchService->resolve($validated['query']);
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

        // Prefetch the profile in the background via Redis queues.
        WarmDotaProfile::dispatch((int) $target['account_id'])->afterResponse();

        return redirect()->route('player', ['accountId' => $target['account_id']]);
    }
}
