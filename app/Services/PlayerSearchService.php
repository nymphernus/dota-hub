<?php

namespace App\Services;

use App\Exceptions\MissingApiKeyException;
use App\Exceptions\PlayerNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use App\Services\Steam\SteamService;
use App\Support\SteamId;

/**
 * Turns whatever the user typed into a routable player identity.
 *
 * Supported input: Steam profile URLs (/profiles/ and /id/), SteamID64,
 * Dota 2 account IDs and (Dota only) nicknames via OpenDota search.
 */
class PlayerSearchService
{
    public function __construct(
        private readonly OpenDotaService $openDota,
        private readonly SteamService $steam,
    ) {
    }

    /**
     * @return array{
     *     game: string,
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
    public function resolve(string $query, string $game): array
    {
        $query = trim($query);
        $game = $game === 'cs2' ? 'cs2' : 'dota';
        $parsed = SteamId::parse($query);

        return $game === 'dota'
            ? $this->resolveDota($parsed)
            : $this->resolveCs2($parsed);
    }

    /**
     * @param  array{type: string, value: string}  $parsed
     * @return array<string, mixed>
     */
    private function resolveDota(array $parsed): array
    {
        if ($parsed['type'] === 'steam_id') {
            $accountId = SteamId::accountIdFromSteamId64($parsed['value']);

            return $this->dotaTarget($accountId);
        }

        if ($parsed['type'] === 'account_id') {
            return $this->dotaTarget((int) $parsed['value']);
        }

        if ($parsed['type'] === 'vanity') {
            $steamId = $this->steamIdFromVanity($parsed['value']);

            if ($steamId !== null) {
                return $this->dotaTarget(SteamId::accountIdFromSteamId64($steamId));
            }
        }

        // Nickname (or a vanity name we could not resolve) -> OpenDota search.
        $results = $this->openDota->search($parsed['value']);

        if ($results === []) {
            throw new PlayerNotFoundException('dota');
        }

        $match = $results[0];

        return [
            'game' => 'dota',
            'identifier' => (string) $match['account_id'],
            'steam_id' => SteamId::steamId64FromAccountId($match['account_id']),
            'account_id' => (int) $match['account_id'],
            'nickname' => $match['nickname'],
            'avatar' => $match['avatar'],
        ];
    }

    /**
     * @param  array{type: string, value: string}  $parsed
     * @return array<string, mixed>
     */
    private function resolveCs2(array $parsed): array
    {
        if ($parsed['type'] === 'steam_id') {
            return $this->cs2Target($parsed['value']);
        }

        if ($parsed['type'] === 'vanity') {
            if (! $this->steam->hasApiKey()) {
                throw new MissingApiKeyException('Steam API', 'STEAM_API_KEY');
            }

            $steamId = $this->steam->resolveVanityUrl($parsed['value']);

            if ($steamId === null) {
                throw new PlayerNotFoundException('cs2');
            }

            return $this->cs2Target($steamId);
        }

        // Valve does not expose a public player search, so nicknames and Dota
        // account IDs cannot be resolved for CS2.
        throw new PlayerNotFoundException('cs2');
    }

    /**
     * @return array<string, mixed>
     */
    private function dotaTarget(int $accountId): array
    {
        if ($accountId <= 0) {
            throw new PlayerNotFoundException('dota');
        }

        return [
            'game' => 'dota',
            'identifier' => (string) $accountId,
            'steam_id' => SteamId::steamId64FromAccountId($accountId),
            'account_id' => $accountId,
            'nickname' => null,
            'avatar' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cs2Target(string $steamId64): array
    {
        if (! SteamId::isSteamId64($steamId64)) {
            throw new PlayerNotFoundException('cs2');
        }

        return [
            'game' => 'cs2',
            'identifier' => $steamId64,
            'steam_id' => $steamId64,
            'account_id' => SteamId::accountIdFromSteamId64($steamId64),
            'nickname' => null,
            'avatar' => null,
        ];
    }

    private function steamIdFromVanity(string $vanity): ?string
    {
        if (! $this->steam->hasApiKey()) {
            return null;
        }

        try {
            return $this->steam->resolveVanityUrl($vanity);
        } catch (MissingApiKeyException) {
            return null;
        }
    }
}
