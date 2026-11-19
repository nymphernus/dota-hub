<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiAssetsTest extends TestCase
{
    public function test_hero_wheel_draws_square_minimap_icons(): void
    {
        $source = (string) file_get_contents(resource_path('js/charts.js'));

        $this->assertStringContainsString('radius * (games / maxGames)', $source);
        $this->assertStringContainsString('drawImage', $source);
        $this->assertStringNotContainsString('context.clip', $source);
        $this->assertStringNotContainsString('context.arc', $source);
    }
}
