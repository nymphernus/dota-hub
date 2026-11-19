<?php

namespace App\Services\Favorites;

use App\Support\SteamId;
use Illuminate\Support\Facades\Cache;

/**
 * Session-scoped favourites for signed-in Steam users.
 *
 * The MVP deliberately stores the list in the cache instead of introducing a
 * users/favourites schema: the Steam identity already lives in the session, so
 * a per-SteamID cache key is enough and expires on its own.
 */
class FavoriteService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $steamId): array
    {
        if (! SteamId::isSteamId64($steamId)) {
            return [];
        }

        $favorites = Cache::get($this->cacheKey($steamId), []);

        return is_array($favorites) ? array_values($favorites) : [];
    }

    public function has(string $steamId, int $accountId): bool
    {
        foreach ($this->all($steamId) as $favorite) {
            if ((int) ($favorite['account_id'] ?? 0) === $accountId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function add(string $steamId, int $accountId, string $nickname, ?string $avatar): array
    {
        if (! SteamId::isSteamId64($steamId) || $accountId <= 0) {
            return $this->all($steamId);
        }

        $favorites = $this->all($steamId);

        foreach ($favorites as $favorite) {
            if ((int) ($favorite['account_id'] ?? 0) === $accountId) {
                return $favorites;
            }
        }

        $favorites[] = [
            'account_id' => $accountId,
            'steam_id' => SteamId::steamId64FromAccountId($accountId),
            'nickname' => $nickname !== '' ? $nickname : 'Игрок #'.$accountId,
            'avatar' => $avatar,
            'added_at' => now()->toIso8601String(),
        ];

        $this->store($steamId, $favorites);

        return $favorites;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function remove(string $steamId, int $accountId): array
    {
        $favorites = array_values(array_filter(
            $this->all($steamId),
            fn (array $favorite) => (int) ($favorite['account_id'] ?? 0) !== $accountId
        ));

        $this->store($steamId, $favorites);

        return $favorites;
    }

    /**
     * @param  array<int, array<string, mixed>>  $favorites
     */
    private function store(string $steamId, array $favorites): void
    {
        if (! SteamId::isSteamId64($steamId)) {
            return;
        }

        Cache::put(
            $this->cacheKey($steamId),
            $favorites,
            (int) config('stats.cache.favorites', 2592000)
        );
    }

    private function cacheKey(string $steamId): string
    {
        return 'steam:favorites:'.$steamId;
    }
}
