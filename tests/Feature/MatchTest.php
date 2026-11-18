<?php

namespace Tests\Feature;

use App\Exceptions\MatchNotFoundException;
use App\Services\OpenDota\OpenDotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchTest extends TestCase
{
    use RefreshDatabase;

    private const MATCH_ID = 9000000001;

    private const ACCOUNT_ID = 70388657;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.opendota.api_key' => null]);
        Cache::flush();
    }

    public function test_match_page_renders_the_scoreboard(): void
    {
        $this->fakeMatch();

        $response = $this->get(route('dota.match', [
            'matchId' => self::MATCH_ID,
            'player_id' => self::ACCOUNT_ID,
        ]));

        $response->assertOk();
        $response->assertSee('Матч');
        $response->assertSee('#'.self::MATCH_ID);
        $response->assertSee('Победа Radiant');
        $response->assertSee('40:56'); // 2456 seconds
        $response->assertSee('Anti-Mage');
        $response->assertSee('Axe');
        $response->assertSee('Radiant');
        $response->assertSee('Dire');
        $response->assertSee('Вы');
        $response->assertViewHas('highlightPlayerId', self::ACCOUNT_ID);
        $response->assertViewHas('match', function (array $match) {
            return $match['match_id'] === self::MATCH_ID
                && $match['radiant_win'] === true
                && $match['radiant_score'] === 32
                && $match['dire_score'] === 18
                && count($match['players']['radiant']) === 2
                && count($match['players']['dire']) === 2;
        });
    }

    public function test_match_details_are_cached_for_repeat_views(): void
    {
        $this->fakeMatch();

        $service = app(OpenDotaService::class);

        $service->getMatch(self::MATCH_ID);
        $service->getMatch(self::MATCH_ID);

        // One match request + one hero metadata request.
        Http::assertSentCount(2);
        $this->assertTrue(Cache::has('opendota:match:'.self::MATCH_ID));
    }

    public function test_unknown_match_redirects_back_with_an_error(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $response = $this
            ->from(route('home'))
            ->get(route('dota.match', ['matchId' => 999999999]));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'Матч не найден.');
    }

    public function test_empty_match_payload_is_treated_as_not_found(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response([]),
        ]);

        $response = $this
            ->from(route('home'))
            ->get(route('dota.match', ['matchId' => self::MATCH_ID]));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'Матч не найден.');
    }

    public function test_service_throws_when_match_has_no_players(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/*' => Http::response([
                'match_id' => self::MATCH_ID,
                'players' => [],
            ]),
        ]);

        $this->expectException(MatchNotFoundException::class);

        app(OpenDotaService::class)->getMatch(self::MATCH_ID);
    }

    public function test_match_route_is_registered(): void
    {
        $this->assertSame(
            url('/dota/match/'.self::MATCH_ID),
            route('dota.match', ['matchId' => self::MATCH_ID])
        );
    }

    private function fakeMatch(): void
    {
        Http::fake([
            'api.opendota.com/api/matches/'.self::MATCH_ID.'*' => Http::response([
                'match_id' => self::MATCH_ID,
                'radiant_win' => true,
                'duration' => 2456,
                'start_time' => 1700000000,
                'game_mode' => 22,
                'radiant_score' => 32,
                'dire_score' => 18,
                'players' => [
                    [
                        'account_id' => self::ACCOUNT_ID,
                        'player_slot' => 0,
                        'hero_id' => 1,
                        'personaname' => 'Dendi',
                        'level' => 25,
                        'kills' => 10,
                        'deaths' => 2,
                        'assists' => 15,
                        'gold_per_min' => 620,
                        'xp_per_min' => 710,
                        'hero_damage' => 25000,
                    ],
                    [
                        'account_id' => 123456789,
                        'player_slot' => 1,
                        'hero_id' => 2,
                        'personaname' => 'Teammate',
                        'level' => 22,
                        'kills' => 4,
                        'deaths' => 7,
                        'assists' => 19,
                        'gold_per_min' => 410,
                        'xp_per_min' => 520,
                        'hero_damage' => 12000,
                    ],
                    [
                        'account_id' => 987654321,
                        'player_slot' => 128,
                        'hero_id' => 2,
                        'personaname' => 'Enemy',
                        'level' => 20,
                        'kills' => 6,
                        'deaths' => 9,
                        'assists' => 8,
                        'gold_per_min' => 380,
                        'xp_per_min' => 460,
                        'hero_damage' => 16000,
                    ],
                    [
                        'account_id' => 0,
                        'player_slot' => 129,
                        'hero_id' => 1,
                        'level' => 18,
                        'kills' => 2,
                        'deaths' => 11,
                        'assists' => 5,
                        'gold_per_min' => 290,
                        'xp_per_min' => 390,
                        'hero_damage' => 8000,
                    ],
                ],
            ]),
            'api.opendota.com/api/heroStats*' => Http::response([
                ['id' => 1, 'localized_name' => 'Anti-Mage', 'img' => '/apps/dota2/images/heroes/antimage_full.png'],
                ['id' => 2, 'localized_name' => 'Axe', 'img' => '/apps/dota2/images/heroes/axe_full.png'],
            ]),
        ]);
    }
}
