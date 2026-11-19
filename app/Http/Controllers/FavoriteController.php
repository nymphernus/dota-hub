<?php

namespace App\Http\Controllers;

use App\Services\Favorites\FavoriteService;
use App\Support\SteamId;
use Illuminate\Contracts\View\View;
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
        ]);
    }

    public function store(Request $request, FavoriteService $favorites): RedirectResponse
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

        return back()->with('status', 'Игрок добавлен в избранное.');
    }

    public function destroy(int $accountId, FavoriteService $favorites): RedirectResponse
    {
        $steamId = $this->steamId();

        if ($steamId === null) {
            return $this->loginRequired();
        }

        $favorites->remove($steamId, $accountId);

        return back()->with('status', 'Игрок удалён из избранного.');
    }

    private function steamId(): ?string
    {
        $steamId = (string) session('steam_id', '');

        return SteamId::isSteamId64($steamId) ? $steamId : null;
    }

    private function loginRequired(): RedirectResponse
    {
        return redirect()->route('auth.steam', ['intended' => url()->previous()]);
    }
}
