<?php

namespace App\Services\Steam;

use App\Exceptions\ExternalApiException;
use App\Exceptions\MissingApiKeyException;
use App\Support\SteamId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Small Steam Web API integration used to resolve vanity profile URLs.
 */
class SteamService
{
    public function __construct(private readonly Factory $http) {}

    public function hasApiKey(): bool
    {
        return filled(config('services.steam.api_key'));
    }

    /**
     * Resolve a /id/customname Steam profile to a SteamID64.
     *
     * Resolution results are cached for a week: vanity names rarely change and
     * every search by custom URL would otherwise spend one Steam API request.
     */
    public function resolveVanityUrl(string $vanity): ?string
    {
        $vanity = trim($vanity);

        if ($vanity === '') {
            return null;
        }

        $cacheKey = 'steam:vanity:'.sha1(mb_strtolower($vanity));
        $ttl = (int) config('stats.cache.steam_vanity', 604800);

        return Cache::remember($cacheKey, $ttl, function () use ($vanity) {
            $response = $this->get('ISteamUser/ResolveVanityURL/v0001/', [
                'vanityurl' => $vanity,
            ]);

            $result = $response['response'] ?? [];

            if ((int) ($result['success'] ?? 0) !== 1) {
                return null;
            }

            $steamId = $result['steamid'] ?? null;

            return is_string($steamId) && SteamId::isSteamId64($steamId) ? $steamId : null;
        });
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        if (! $this->hasApiKey()) {
            throw new MissingApiKeyException('Steam API', 'STEAM_API_KEY');
        }

        $query['key'] = config('services.steam.api_key');
        $query['format'] = 'json';

        try {
            $response = $this->http
                ->baseUrl(rtrim((string) config('services.steam.base_url'), '/').'/')
                ->acceptJson()
                ->timeout(15)
                ->get($path, $query);
        } catch (ConnectionException $exception) {
            Log::warning('Steam connection failed', ['path' => $path, 'error' => $exception->getMessage()]);

            throw new ExternalApiException('Steam', 'Попробуйте ещё раз через минуту.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new ExternalApiException('Steam', 'Проверьте STEAM_API_KEY в настройках.');
        }

        if ($response->failed()) {
            Log::warning('Steam request failed', ['path' => $path, 'status' => $response->status()]);

            throw new ExternalApiException('Steam', 'Код ответа: '.$response->status().'.');
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }
}
