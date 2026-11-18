<?php

namespace Tests\Unit;

use App\Support\SteamId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SteamIdTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function inputs(): array
    {
        return [
            'profile url' => ['https://steamcommunity.com/profiles/76561198030654385', 'steam_id', '76561198030654385'],
            'profile url without scheme' => ['steamcommunity.com/profiles/76561198030654385/', 'steam_id', '76561198030654385'],
            'profile url with trailing query' => ['https://www.steamcommunity.com/profiles/76561198030654385?l=russian', 'steam_id', '76561198030654385'],
            'vanity url' => ['https://steamcommunity.com/id/dendi', 'vanity', 'dendi'],
            'steam id' => ['76561198030654385', 'steam_id', '76561198030654385'],
            'account id' => ['70388657', 'account_id', '70388657'],
            'nickname' => ['Dendi', 'name', 'Dendi'],
            'nickname with spaces' => ['  Miracle-  ', 'name', 'Miracle-'],
            'plain word' => ['not-a-steam-id', 'name', 'not-a-steam-id'],
        ];
    }

    #[DataProvider('inputs')]
    public function test_it_parses_user_input(string $input, string $type, string $value): void
    {
        $this->assertSame(['type' => $type, 'value' => $value], SteamId::parse($input));
    }

    public function test_it_recognises_steam_id_64(): void
    {
        $this->assertTrue(SteamId::isSteamId64('76561198030654385'));
        $this->assertFalse(SteamId::isSteamId64('7656119803065438'));
        $this->assertFalse(SteamId::isSteamId64('86561198030654385'));
        $this->assertFalse(SteamId::isSteamId64('7656119803065438a'));
    }

    public function test_it_converts_between_account_id_and_steam_id_64(): void
    {
        $this->assertSame('76561198030654385', SteamId::steamId64FromAccountId(70388657));
        $this->assertSame(70388657, SteamId::accountIdFromSteamId64('76561198030654385'));
        $this->assertSame('76561198030654385', SteamId::steamId64FromAccountId('70388657'));
    }

    public function test_it_builds_a_profile_url(): void
    {
        $this->assertSame(
            'https://steamcommunity.com/profiles/76561198030654385',
            SteamId::profileUrl('76561198030654385')
        );
    }
}
