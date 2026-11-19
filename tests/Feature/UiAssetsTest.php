<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiAssetsTest extends TestCase
{
    public function test_hero_wheel_draws_square_minimap_icons_inside_each_sector(): void
    {
        $source = (string) file_get_contents(resource_path('js/charts.js'));

        $this->assertStringContainsString('radius * (games / maxGames)', $source);
        $this->assertStringContainsString('band - 8', $source);
        $this->assertStringContainsString('arcLength - 4', $source);
        $this->assertStringContainsString('middleRadius', $source);
        $this->assertStringContainsString('drawImage', $source);
        $this->assertStringNotContainsString('context.clip', $source);
        $this->assertStringNotContainsString('context.arc', $source);
    }

    public function test_hero_wheel_uses_thin_ring_moving_icons_and_visible_tooltip(): void
    {
        $source = (string) file_get_contents(resource_path('js/charts.js'));

        $this->assertStringContainsString("cutout: '40%'", $source);
        $this->assertStringContainsString('zIndex: 9999', $source);
        $this->assertStringContainsString('Math.cos(middleAngle)', $source);
        $this->assertStringContainsString('Math.sin(middleAngle)', $source);
        $this->assertStringContainsString('drawImage', $source);
        $this->assertStringNotContainsString('tooltipPosition()', $source);

        $view = (string) file_get_contents(resource_path('views/dota/player.blade.php'));
        $this->assertStringContainsString('relative z-10 mx-auto mt-4 h-56 w-56', $view);
        $this->assertStringContainsString('style="position: relative; z-index: 10;"', $view);
    }

    public function test_hero_wheel_uses_dark_sectors_and_highlights_only_the_first_three(): void
    {
        $source = (string) file_get_contents(resource_path('js/charts.js'));

        $this->assertStringContainsString("HERO_SECTOR_COLOR = '#1e293b'", $source);
        $this->assertStringContainsString("TOP_HERO_SECTOR_COLOR = '#475569'", $source);
        $this->assertStringContainsString('index < 3', $source);
        $this->assertStringNotContainsString('hero.color', $source);

        $controller = (string) file_get_contents(app_path('Http/Controllers/DotaPlayerController.php'));
        $this->assertStringNotContainsString('HERO_USAGE_COLORS', $controller);
    }

    public function test_section_titles_have_no_english_or_duplicate_eyebrows(): void
    {
        $files = [
            'dota/player.blade.php',
            'dota/heroes.blade.php',
            'dota/peers.blade.php',
            'dota/match.blade.php',
            'dota/hero.blade.php',
        ];

        $staleLabels = [
            '>Hero pool<',
            '>Hero wheel<',
            '>Frequently played with<',
            '>Календарь матчей<',
            '>Совместные матчи<',
            '>Economy<',
            '<p class="label">Бенчмарки</p>',
            '<p class="label">История</p>',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents(resource_path('views/'.$file));

            foreach ($staleLabels as $label) {
                $this->assertStringNotContainsString($label, $source, $file.' still contains '.$label);
            }
        }

        $metrics = (string) file_get_contents(resource_path('views/components/player-metrics.blade.php'));
        $this->assertStringNotContainsString('>KDA<', $metrics);
        $this->assertStringNotContainsString('K + A / D', $metrics);

        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringNotContainsString('Проект не аффилирован', $layout);
    }
}
