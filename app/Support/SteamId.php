<?php

namespace App\Support;

/**
 * Helpers for the three common ways players reference a Steam account:
 * SteamID64, Dota 2 account ID and Steam community URLs.
 */
final class SteamId
{
    /**
     * Every SteamID64 is derived from this constant plus the Dota 2 account ID.
     */
    public const BASE = 76561197960265728;

    public const COMMUNITY_URL = 'https://steamcommunity.com';

    /**
     * @return array{type: 'steam_id'|'account_id'|'vanity'|'name', value: string}
     */
    public static function parse(string $input): array
    {
        $input = trim($input);

        if (preg_match('~^(?:https?://)?(?:www\.)?steamcommunity\.com/profiles/(\d{17})(?:[/?#].*)?$~i', $input, $matches)) {
            return ['type' => 'steam_id', 'value' => $matches[1]];
        }

        if (preg_match('~^(?:https?://)?(?:www\.)?steamcommunity\.com/id/([A-Za-z0-9_-]+)(?:[/?#].*)?$~i', $input, $matches)) {
            return ['type' => 'vanity', 'value' => $matches[1]];
        }

        if (self::isSteamId64($input)) {
            return ['type' => 'steam_id', 'value' => $input];
        }

        if (self::isAccountId($input)) {
            return ['type' => 'account_id', 'value' => $input];
        }

        return ['type' => 'name', 'value' => $input];
    }

    public static function isSteamId64(string $value): bool
    {
        return preg_match('/^7656119\d{10}$/', $value) === 1;
    }

    public static function isAccountId(string $value): bool
    {
        return preg_match('/^\d{1,10}$/', $value) === 1;
    }

    public static function steamId64FromAccountId(int|string $accountId): string
    {
        return (string) (self::BASE + (int) $accountId);
    }

    public static function accountIdFromSteamId64(int|string $steamId64): int
    {
        return (int) ((int) $steamId64 - self::BASE);
    }

    public static function profileUrl(int|string $steamId64): string
    {
        return self::COMMUNITY_URL.'/profiles/'.$steamId64;
    }
}
