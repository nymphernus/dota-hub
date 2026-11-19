<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

/**
 * Steam OpenID login. The returned SteamID64 is kept in the session so the
 * header can show the signed-in player without a database round trip.
 */
class SteamAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $intended = $request->string('intended')->toString();

        if ($intended !== '') {
            Session::put('steam_intended', $intended);
        }

        return Socialite::driver('steam')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $intended = Session::pull('steam_intended', $request->string('intended')->toString());

        try {
            $steamUser = Socialite::driver('steam')->user();
            $steamId = (string) $steamUser->getId();

            if ($steamId === '') {
                throw new \RuntimeException('Steam не вернул идентификатор игрока.');
            }

            Session::put('steam_id', $steamId);
            Session::put('steam_nickname', (string) ($steamUser->getNickname() ?? ''));
            Session::put('steam_avatar', (string) ($steamUser->getAvatar() ?? ''));

            return $this->redirectTo($intended, route('home'))
                ->with('status', 'Вы вошли через Steam.');
        } catch (Throwable $exception) {
            Log::warning('Steam login failed', ['message' => $exception->getMessage()]);

            return $this->redirectTo($intended, route('home'))
                ->with('error', 'Не удалось войти через Steam. Попробуйте ещё раз.');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Session::forget(['steam_id', 'steam_nickname', 'steam_avatar']);

        $intended = $request->string('intended')->toString();

        return $this->redirectTo($intended, route('home'))
            ->with('status', 'Вы вышли из аккаунта Steam.');
    }

    /**
     * OpenID assertions can be replayed from bookmarks, so only local paths
     * (or URLs on this host) are accepted as a post-login destination.
     */
    private function redirectTo(string $intended, string $fallback): RedirectResponse
    {
        if ($intended !== '' && str_starts_with($intended, '/')) {
            try {
                return redirect()->to($intended);
            } catch (RouteNotFoundException) {
                // Fall through to the home page.
            }
        }

        return redirect()->to($fallback);
    }
}
