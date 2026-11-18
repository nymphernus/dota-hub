<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Cs2ProfileTest extends TestCase
{
    use RefreshDatabase;

    private const STEAM_ID = '76561198030654385';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_missing_steam_api_key_shows_setup_instructions(): void
    {
        config(['services.steam.api_key' => null]);

        $response = $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]));

        $response->assertStatus(503);
        $response->assertSee('Steam API не настроена');
        $response->assertSee('STEAM_API_KEY');
        Http::assertNothingSent();
    }

    public function test_profile_page_renders_steam_data(): void
    {
        $this->fakeSteam();

        $response = $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]));

        $response->assertOk();
        $response->assertSee('TestPlayer');
        $response->assertSee('VAC: 1');
        $response->assertViewHas('player', function (array $player) {
            return $player['steam_id'] === self::STEAM_ID
                && $player['vac_banned'] === true
                && $player['vac_bans'] === 1
                && $player['has_bans'] === true
                && $player['is_public'] === true
                && $player['has_cs2'] === true
                && $player['cs2']['total_hours'] === 2000.0
                && $player['cs2']['recent_hours'] === 10.0;
        });

        $this->assertTrue(Cache::has('steam:player:'.self::STEAM_ID));
        Http::assertSentCount(3);
    }

    public function test_second_visit_is_served_from_cache(): void
    {
        $this->fakeSteam();

        $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]))->assertOk();
        $sentAfterFirstVisit = count(Http::recorded());

        $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]))->assertOk();

        $this->assertSame($sentAfterFirstVisit, count(Http::recorded()));
    }

    public function test_refresh_forgets_the_cached_profile(): void
    {
        $this->fakeSteam();

        $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]))->assertOk();

        $this->post(route('cs2.player.refresh', ['steamId' => self::STEAM_ID]))
            ->assertRedirect(route('cs2.player', ['steamId' => self::STEAM_ID]))
            ->assertSessionHas('status');

        $this->assertFalse(Cache::has('steam:player:'.self::STEAM_ID));
    }

    public function test_private_profile_is_flagged(): void
    {
        $this->fakeSteam(['communityvisibilitystate' => 1]);

        $response = $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]));

        $response->assertOk();
        $response->assertSee('Профиль закрыт');
        $response->assertViewHas('player', fn (array $player) => $player['is_public'] === false);
    }

    public function test_profile_without_cs2_in_library_is_flagged(): void
    {
        $this->fakeSteam(games: []);

        $response = $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]));

        $response->assertOk();
        $response->assertViewHas('player', fn (array $player) => $player['has_cs2'] === false && $player['library_hidden'] === true);
    }

    public function test_unknown_steam_id_returns_a_404_page(): void
    {
        config(['services.steam.api_key' => 'test-key']);
        Http::fake([
            'api.steampowered.com/ISteamUser/GetPlayerSummaries/*' => Http::response(['response' => ['players' => []]]),
        ]);

        $response = $this->get(route('cs2.player', ['steamId' => self::STEAM_ID]));

        $response->assertNotFound();
        $response->assertSee('Игрок не найден');
    }

    public function test_invalid_steam_id_returns_a_404_page(): void
    {
        $response = $this->get('/cs2/player/12345678901234567');

        $response->assertNotFound();
        $response->assertSee('Некорректный SteamID64');
    }

    /**
     * @param  array<string, mixed>  $summaryOverrides
     * @param  array<int, mixed>|null  $games
     */
    private function fakeSteam(array $summaryOverrides = [], ?array $games = null): void
    {
        config(['services.steam.api_key' => 'test-key']);

        $games ??= [
            [
                'appid' => 730,
                'playtime_forever' => 120000,
                'playtime_2weeks' => 600,
                'rtime_last_played' => 1700000000,
            ],
        ];

        $summary = array_merge([
            'steamid' => self::STEAM_ID,
            'personaname' => 'TestPlayer',
            'avatarfull' => 'https://example.com/avatar.jpg',
            'profileurl' => 'https://steamcommunity.com/profiles/'.self::STEAM_ID,
            'communityvisibilitystate' => 3,
            'personastate' => 1,
            'loccountrycode' => 'DE',
            'timecreated' => 1300000000,
        ], $summaryOverrides);

        Http::fake([
            'api.steampowered.com/ISteamUser/GetPlayerSummaries/*' => Http::response([
                'response' => ['players' => [$summary]],
            ]),
            'api.steampowered.com/ISteamUser/GetPlayerBans/*' => Http::response([
                'players' => [[
                    'SteamId' => self::STEAM_ID,
                    'CommunityBanned' => false,
                    'VACBanned' => true,
                    'NumberOfVACBans' => 1,
                    'DaysSinceLastBan' => 30,
                    'NumberOfGameBans' => 0,
                    'EconomyBan' => 'none',
                ]],
            ]),
            'api.steampowered.com/IPlayerService/GetOwnedGames/*' => Http::response([
                'response' => ['game_count' => count($games), 'games' => $games],
            ]),
        ]);
    }
}
