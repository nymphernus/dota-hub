<?php

namespace App\Services\Steam;

use App\Exceptions\ExternalApiException;
use App\Exceptions\MissingApiKeyException;
use App\Support\SteamId;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Steam Web API integration for vanity URL resolution and account privacy.
 *
 * Only public data exposed by Valve is used. Browsing a profile never fails
 * just because Steam is down or STEAM_API_KEY is missing: the service returns
 * an “unavailable” payload that the profile page can render gracefully.
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
     * Public Steam account summary, including the community visibility state.
     *
     * @return array{
     *     available: bool,
     *     steam_id: string|null,
     *     nickname: string|null,
     *     real_name: string|null,
     *     avatar: string|null,
     *     profile_url: string|null,
     *     country_code: string|null,
     *     status: string|null,
     *     is_public: bool|null,
     *     visibility: string,
     *     visibility_state: int|null,
     *     account_created_at: string|null,
     *     last_logoff_at: string|null,
     *     error: string|null
     * }
     */
    public function summary(?string $steamId64): array
    {
        $steamId64 = is_string($steamId64) ? trim($steamId64) : '';

        if (! SteamId::isSteamId64($steamId64)) {
            return $this->unavailable($steamId64 ?: null, 'Некорректный SteamID64.');
        }

        if (! $this->hasApiKey()) {
            return $this->unavailable($steamId64, 'STEAM_API_KEY не настроен.');
        }

        $cacheKey = 'steam:summary:v1:'.$steamId64;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $ttl = (int) config('stats.cache.steam_profile', 1800);

        try {
            $response = $this->get('ISteamUser/GetPlayerSummaries/v0002/', [
                'steamids' => $steamId64,
            ]);

            $players = $response['response']['players'] ?? [];
            $player = is_array($players) ? ($players[0] ?? null) : null;

            if (! is_array($player) || empty($player['steamid'])) {
                throw new ExternalApiException('Steam', 'Steam не вернул профиль игрока.');
            }

            $summary = $this->transformSummary($player, $steamId64);
        } catch (MissingApiKeyException|ExternalApiException $exception) {
            $summary = $this->unavailable($steamId64, $exception->getMessage());
            $ttl = min(300, $ttl);
        }

        Cache::put($cacheKey, $summary, $ttl);

        return $summary;
    }

    /**
     * Public VAC / Game Ban state for the account tab.
     *
     * @return array<string, mixed>
     */
    public function bans(?string $steamId64): array
    {
        $steamId64 = is_string($steamId64) ? trim($steamId64) : '';

        if (! SteamId::isSteamId64($steamId64)) {
            return $this->unavailableBans($steamId64 ?: null, 'Некорректный SteamID64.');
        }

        if (! $this->hasApiKey()) {
            return $this->unavailableBans($steamId64, 'STEAM_API_KEY не настроен.');
        }

        $cacheKey = 'steam:bans:v1:'.$steamId64;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->get('ISteamUser/GetPlayerBans/v1/', [
                'steamids' => $steamId64,
            ]);
            $players = $response['response']['players'] ?? [];
            $player = is_array($players) ? ($players[0] ?? null) : null;

            if (! is_array($player) || empty($player['SteamId'])) {
                throw new ExternalApiException('Steam', 'Steam не вернул данные о банах.');
            }

            $daysSinceLastBan = isset($player['DaysSinceLastBan'])
                ? (int) $player['DaysSinceLastBan']
                : null;

            $bans = [
                'available' => true,
                'vac_banned' => (bool) ($player['VACBanned'] ?? false),
                'vac_bans' => (int) ($player['NumberOfVACBans'] ?? 0),
                'game_bans' => (int) ($player['GameBanCount'] ?? 0),
                'days_since_last_ban' => $daysSinceLastBan,
                'error' => null,
            ];
        } catch (MissingApiKeyException|ExternalApiException $exception) {
            $bans = $this->unavailableBans($steamId64, $exception->getMessage());
        }

        Cache::put($cacheKey, $bans, (int) config('stats.cache.steam_profile', 1800));

        return $bans;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableBans(?string $steamId64, string $message): array
    {
        return [
            'available' => false,
            'steam_id' => $steamId64,
            'vac_banned' => null,
            'vac_bans' => null,
            'game_bans' => null,
            'days_since_last_ban' => null,
            'error' => $message,
        ];
    }

    /**
     * Scrape Steam community profile background image. Cached for 24h.
     */
    public function getProfileBackground(?string $steamId64): ?string
    {
        $steamId64 = is_string($steamId64) ? trim($steamId64) : '';
        if (! SteamId::isSteamId64($steamId64)) {
            return null;
        }

        $cacheKey = 'steam:background:v1:'.$steamId64;

        return Cache::remember($cacheKey, 86400, function () use ($steamId64) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->get("https://steamcommunity.com/profiles/{$steamId64}");

                if ($response->failed()) {
                    return null;
                }

                $html = $response->body();
                if (preg_match('/profile_background_image_content\s*"?[^>]*?background-image\s*:\s*url\((?:&quot;|\'|")?(.*?)(?:&quot;|\'|")?\)/si', $html, $m)) {
                    $url = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
                    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
                }
                if (preg_match('/background-image\s*:\s*url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $html, $m)) {
                    $url = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
                    if (str_contains($url, 'steamstatic') || str_contains($url, 'steamcdn')) {
                        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('Steam background scrape failed', ['steam_id' => $steamId64, 'error' => $e->getMessage()]);
            }

            return null;
        });
    }

    public function forget(?string $steamId64): void
    {
        if (is_string($steamId64) && SteamId::isSteamId64($steamId64)) {
            Cache::forget('steam:summary:v1:'.$steamId64);
            Cache::forget('steam:bans:v1:'.$steamId64);
            Cache::forget('steam:background:v1:'.$steamId64);
        }
    }

    /**
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    private function transformSummary(array $player, string $fallbackSteamId): array
    {
        $visibilityState = isset($player['communityvisibilitystate'])
            ? (int) $player['communityvisibilitystate']
            : null;

        $visibility = match ($visibilityState) {
            1 => 'Приватный',
            2 => 'Только для друзей',
            3 => 'Публичный',
            default => 'Неизвестно',
        };

        $status = match ((int) ($player['personastate'] ?? 0)) {
            1 => 'В сети',
            2 => 'Занят',
            3 => 'Отошёл',
            4 => 'Спит',
            5 => 'Хочет обменяться',
            6 => 'Хочет играть',
            default => 'Не в сети',
        };

        return [
            'available' => true,
            'steam_id' => (string) ($player['steamid'] ?? $fallbackSteamId),
            'nickname' => $player['personaname'] ?? null,
            'real_name' => $player['realname'] ?? null,
            'avatar' => $player['avatarfull'] ?? $player['avatarmedium'] ?? $player['avatar'] ?? null,
            'profile_url' => $player['profileurl'] ?? null,
            'country_code' => $player['loccountrycode'] ?? null,
            'status' => $status,
            'is_public' => $visibilityState === null ? null : $visibilityState === 3,
            'visibility' => $visibility,
            'visibility_state' => $visibilityState,
            'account_created_at' => $this->timestamp($player['timecreated'] ?? null),
            'last_logoff_at' => $this->timestamp($player['lastlogoff'] ?? null),
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(?string $steamId64, string $message): array
    {
        return [
            'available' => false,
            'steam_id' => $steamId64,
            'nickname' => null,
            'real_name' => null,
            'avatar' => null,
            'profile_url' => null,
            'country_code' => null,
            'status' => null,
            'is_public' => null,
            'visibility' => 'Неизвестно',
            'visibility_state' => null,
            'account_created_at' => null,
            'last_logoff_at' => null,
            'error' => $message,
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $value)->toIso8601String();
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
