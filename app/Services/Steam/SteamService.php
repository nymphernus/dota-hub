<?php

namespace App\Services\Steam;

use App\Exceptions\ExternalApiException;
use App\Exceptions\MissingApiKeyException;
use App\Exceptions\PlayerNotFoundException;
use App\Support\SteamId;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Steam Web API integration for CS2 profiles.
 *
 * Only public data exposed by Valve is used: player summaries, VAC/game bans
 * and owned-games playtime. Everything is cached in Redis to stay far away
 * from Steam's rate limits.
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
     */
    public function resolveVanityUrl(string $vanity): ?string
    {
        $response = $this->get('ISteamUser/ResolveVanityURL/v0001/', [
            'vanityurl' => $vanity,
        ]);

        $result = $response['response'] ?? [];
        $steamId = $result['steamid'] ?? null;

        return is_string($steamId) && SteamId::isSteamId64($steamId) ? $steamId : null;
    }

    /**
     * Aggregated, cached CS2 profile.
     *
     * @return array<string, mixed>
     */
    public function player(string $steamId64): array
    {
        $ttl = (int) config('stats.cache.steam_profile', 1800);

        return Cache::remember(
            "steam:player:{$steamId64}",
            $ttl,
            fn () => $this->fetchPlayer($steamId64, $ttl)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPlayer(string $steamId64, int $ttl): array
    {
        $summary = $this->playerSummary($steamId64);

        if ($summary === null) {
            throw new PlayerNotFoundException('cs2');
        }

        $bans = $this->bans($steamId64);
        $cs2 = $this->cs2Game($steamId64);
        $visibility = (int) ($summary['communityvisibilitystate'] ?? 1);
        $isPublic = $visibility === 3;

        $vacBanned = (bool) ($bans['VACBanned'] ?? false);
        $gameBans = (int) ($bans['NumberOfGameBans'] ?? 0);
        $communityBanned = (bool) ($bans['CommunityBanned'] ?? false);

        return [
            'steam_id' => $steamId64,
            'nickname' => $summary['personaname'] ?? 'Steam User',
            'real_name' => $summary['realname'] ?? null,
            'avatar' => $summary['avatarfull'] ?? $summary['avatarmedium'] ?? $summary['avatar'] ?? null,
            'profile_url' => $summary['profileurl'] ?? SteamId::profileUrl($steamId64),
            'country_code' => $summary['loccountrycode'] ?? null,
            'status' => $this->personaState((int) ($summary['personastate'] ?? 0)),
            'is_public' => $isPublic,
            'visibility' => $this->visibilityLabel($visibility),
            'account_created_at' => isset($summary['timecreated'])
                ? CarbonImmutable::createFromTimestampUTC((int) $summary['timecreated'])->toIso8601String()
                : null,
            'last_logoff_at' => isset($summary['lastlogoff'])
                ? CarbonImmutable::createFromTimestampUTC((int) $summary['lastlogoff'])->toIso8601String()
                : null,
            'playing_cs2' => (string) ($summary['gameid'] ?? '') === (string) config('services.steam.cs2_app_id', 730),
            'vac_banned' => $vacBanned,
            'vac_bans' => (int) ($bans['NumberOfVACBans'] ?? 0),
            'game_bans' => $gameBans,
            'community_banned' => $communityBanned,
            'days_since_last_ban' => (int) ($bans['DaysSinceLastBan'] ?? 0),
            'economy_ban' => $bans['EconomyBan'] ?? 'none',
            'has_bans' => $vacBanned || $gameBans > 0 || $communityBanned,
            'has_cs2' => $cs2 !== null,
            'cs2' => $cs2,
            // A public profile can still hide its game library.
            'library_hidden' => $isPublic && $cs2 === null,
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
            'cache_ttl' => $ttl,
        ];
    }

    public function forget(string $steamId64): void
    {
        Cache::forget("steam:player:{$steamId64}");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function playerSummary(string $steamId64): ?array
    {
        $response = $this->get('ISteamUser/GetPlayerSummaries/v0002/', [
            'steamids' => $steamId64,
        ]);

        $player = $response['response']['players'][0] ?? null;

        return is_array($player) ? $player : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function bans(string $steamId64): array
    {
        try {
            $response = $this->get('ISteamUser/GetPlayerBans/v0001/', [
                'steamids' => $steamId64,
            ]);
        } catch (ExternalApiException $exception) {
            Log::warning('Steam bans endpoint failed', ['steam_id' => $steamId64, 'message' => $exception->getMessage()]);

            return [];
        }

        $player = $response['players'][0] ?? null;

        return is_array($player) ? $player : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cs2Game(string $steamId64): ?array
    {
        try {
            $response = $this->get('IPlayerService/GetOwnedGames/v0001/', [
                'steamid' => $steamId64,
                'include_appinfo' => 0,
                'include_played_free_games' => 1,
                'appids_filter' => [config('services.steam.cs2_app_id', 730)],
            ]);
        } catch (ExternalApiException $exception) {
            Log::warning('Steam owned games endpoint failed', ['steam_id' => $steamId64, 'message' => $exception->getMessage()]);

            return null;
        }

        $games = $response['response']['games'] ?? [];
        $game = is_array($games) ? ($games[0] ?? null) : null;

        if (! is_array($game)) {
            return null;
        }

        $totalMinutes = (int) ($game['playtime_forever'] ?? 0);
        $recentMinutes = (int) ($game['playtime_2weeks'] ?? 0);

        return [
            'app_id' => (int) ($game['appid'] ?? config('services.steam.cs2_app_id', 730)),
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'recent_minutes' => $recentMinutes,
            'recent_hours' => round($recentMinutes / 60, 1),
            'last_played_at' => ! empty($game['rtime_last_played'])
                ? CarbonImmutable::createFromTimestampUTC((int) $game['rtime_last_played'])->toIso8601String()
                : null,
        ];
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

    private function personaState(int $state): string
    {
        return match ($state) {
            1 => 'В сети',
            2 => 'Не беспокоить',
            3 => 'Неактивен',
            4 => 'Скоро вернусь',
            5 => 'Готов к обмену',
            6 => 'Готов к игре',
            default => 'Не в сети',
        };
    }

    private function visibilityLabel(int $visibility): string
    {
        return match ($visibility) {
            3 => 'Публичный',
            2 => 'Только для друзей',
            1 => 'Приватный',
            default => 'Неизвестно',
        };
    }
}
