<?php

namespace Tests\Feature;

use App\Services\OpenDota\OpenDotaService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameModeLabelTest extends TestCase
{
    public static function gameModeProvider(): array
    {
        return [
            'all pick' => [1, 'All Pick'],
            'ranked all pick' => [22, 'Ranked'],
            'turbo' => [23, 'Turbo'],
            'single draft' => [4, 'Single Draft'],
            'ability draft' => [18, 'Ability Draft'],
            'captains mode' => [16, 'Captains Mode'],
        ];
    }

    #[DataProvider('gameModeProvider')]
    public function test_game_mode_ids_map_to_readable_labels(int $mode, string $expected): void
    {
        $this->app->setLocale('en');
        $service = $this->app->make(OpenDotaService::class);
        $method = new \ReflectionMethod($service, 'gameModeLabel');

        $this->assertSame($expected, $method->invoke($service, $mode));
    }

    #[DataProvider('gameModeProvider')]
    public function test_match_type_label_prefers_the_game_mode_over_lobby_type(int $mode, string $expected): void
    {
        $this->app->setLocale('en');
        $service = $this->app->make(OpenDotaService::class);
        $method = new \ReflectionMethod($service, 'matchTypeLabel');

        // Even for a ranked lobby the table must show the concrete game mode.
        $this->assertSame($expected, $method->invoke($service, 7, $mode));
    }
}
