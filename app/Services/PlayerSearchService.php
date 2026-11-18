<?php

namespace App\Services;

use App\Exceptions\MissingApiKeyException;
use App\Exceptions\PlayerNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use App\Services\Steam\SteamService;
use App\Support\SteamId;

/**
 * Turns whatever the user typed into a routable Dota 2 player identity.
 *
 * Supported input: Steam profile URLs (/profiles/ and /id/), SteamID64,
 * Dota 2 account IDs and nicknames via OpenDota search.
 */
class PlayerSearchService
{
    public function __construct(
        private readonly OpenDotaService $openDota,
        private readonly SteamService $steam,
    ) {}

    /**
     * @return array{
     *     identifier: string,
     *     steam_id: string|null,
     *     account_id: int|null,
     *     nickname: string|null,
     *     avatar: string|null
     * }
     *
     * @throws PlayerNotFoundException
     * @throws MissingApiKeyException
     */
    public function resolve(string $query): array
    {
        $parsed = SteamId::parse(trim($query));

        if ($parsed['type'] === 'steam_id') {
            return $this->dotaTarget(SteamId::accountIdFromSteamId64($parsed['value']));
        }

        if ($parsed['type'] === 'account_id') {
            return $this->dotaTarget((int) $parsed['value']);
        }

        if ($parsed['type'] === 'vanity') {
            if (! $this->steam->hasApiKey()) {
                throw new MissingApiKeyException('Steam API', 'STEAM_API_KEY');
            }

            $steamId = $this->steam->resolveVanityUrl($parsed['value']);

            if ($steamId === null) {
                throw new PlayerNotFoundException;
            }

            return $this->dotaTarget(SteamId::accountIdFromSteamId64($steamId));
        }

        // Nickname -> OpenDota search.
        $results = $this->openDota->search($parsed['value']);

        if ($results === []) {
            throw new PlayerNotFoundException;
        }

        $match = $results[0];

        return [
            'identifier' => (string) $match['account_id'],
            'steam_id' => SteamId::steamId64FromAccountId($match['account_id']),
            'account_id' => (int) $match['account_id'],
            'nickname' => $match['nickname'],
            'avatar' => $match['avatar'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dotaTarget(int $accountId): array
    {
        if ($accountId <= 0) {
            throw new PlayerNotFoundException;
        }

        return [
            'identifier' => (string) $accountId,
            'steam_id' => SteamId::steamId64FromAccountId($accountId),
            'account_id' => $accountId,
            'nickname' => null,
            'avatar' => null,
        ];
    }
}
