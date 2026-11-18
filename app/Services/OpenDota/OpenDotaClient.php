<?php

namespace App\Services\OpenDota;

use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\PlayerNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client around the OpenDota REST API.
 *
 * OpenDota allows 2000 requests per day (and 60 per minute for some endpoints),
 * so every call is accounted for in the shared cache store. A hard local limit
 * protects the application from exhausting the quota on a busy day.
 */
class OpenDotaClient
{
    public function __construct(private readonly Factory $http) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     *
     * @throws PlayerNotFoundException
     * @throws ApiRateLimitException
     * @throws ExternalApiException
     */
    public function get(string $path, array $query = []): array
    {
        $this->reserveRequestSlot();

        $baseUrl = rtrim((string) config('services.opendota.base_url'), '/');

        if ($apiKey = config('services.opendota.api_key')) {
            $query['api_key'] = $apiKey;
        }

        try {
            $response = $this->http
                ->baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(15)
                ->get(ltrim($path, '/'), $query);
        } catch (ConnectionException $exception) {
            Log::warning('OpenDota connection failed', ['path' => $path, 'error' => $exception->getMessage()]);

            throw new ExternalApiException('OpenDota', 'Попробуйте ещё раз через минуту.');
        }

        if ($response->status() === 404) {
            throw new PlayerNotFoundException('dota');
        }

        if ($response->status() === 429) {
            throw new ApiRateLimitException('OpenDota', 'Дневной лимит запросов исчерпан. Данные обновятся позже.');
        }

        if ($response->failed()) {
            Log::warning('OpenDota request failed', ['path' => $path, 'status' => $response->status()]);

            throw new ExternalApiException('OpenDota', 'Код ответа: '.$response->status().'.');
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Reserve one request from the shared OpenDota daily quota.
     *
     * @throws ApiRateLimitException
     */
    private function reserveRequestSlot(): void
    {
        $limit = (int) config('stats.opendota_daily_limit', 2000);
        $key = 'opendota:quota:'.now()->utc()->format('Y-m-d');

        Cache::add($key, 0, now()->addDays(2));

        if ((int) Cache::get($key, 0) >= $limit) {
            throw new ApiRateLimitException(
                'OpenDota',
                "Локальный лимит в {$limit} запросов в сутки исчерпан. Кэшированные данные продолжают работать."
            );
        }

        Cache::increment($key);
    }
}
