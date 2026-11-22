<?php

namespace App\Http\Controllers;

use App\Services\Favorites\FavoriteService;
use App\Support\SteamId;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(FavoriteService $favorites): View|RedirectResponse
    {
        $steamId = $this->steamId();

        if ($steamId === null) {
            return $this->loginRequired();
        }

        return view('favorites.index', [
            'favorites' => $favorites->all($steamId),
            'steamId' => $steamId,
            'pageTitle' => __('ui.favorites'),
        ]);
    }

    public function store(Request $request, FavoriteService $favorites): RedirectResponse|JsonResponse
    {
        $steamId = $this->steamId();

        if ($steamId === null) {
            return $this->loginRequired();
        }

        $data = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        $avatar = (string) ($data['avatar'] ?? '');

        $favorites->add(
            $steamId,
            (int) $data['account_id'],
            mb_substr(trim((string) ($data['nickname'] ?? '')), 0, 80),
            filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'favorite' => true,
                'account_id' => (int) $data['account_id'],
            ]);
        }

        return back()->with('status', '__('ui.favorite_added')');
    }

    public function destroy(Request $request, int $accountId, FavoriteService $favorites): RedirectResponse|JsonResponse
    {
        $steamId = $this->steamId();

        if ($steamId === null) {
            return $this->loginRequired();
        }

        $favorites->remove($steamId, $accountId);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'favorite' => false,
                'account_id' => $accountId,
            ]);
        }

        return back()->with('status', '__('ui.favorite_removed')');
    }

    private function steamId(): ?string
    {
        $steamId = (string) session('steam_id', '');

        return SteamId::isSteamId64($steamId) ? $steamId : null;
    }

    private function loginRequired(): RedirectResponse|JsonResponse
    {
        $loginUrl = route('auth.steam', ['intended' => url()->previous()]);

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => '__('ui.login_to_manage_favorites')',
                'login_url' => $loginUrl,
            ], 401);
        }

        return redirect()->to($loginUrl);
    }
}
