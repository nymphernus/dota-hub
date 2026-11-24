<?php

namespace Tests\Feature;

use Tests\TestCase;

class CleanNavigationTest extends TestCase
{
    public function test_profile_tabs_are_rendered_only_in_the_header(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $profileLayout = (string) file_get_contents(resource_path('views/dota/layouts/profile.blade.php'));

        $this->assertStringContainsString('aria-label="Navigation"', $layout);
        $this->assertStringContainsString('nav-shell', $layout);
        $this->assertStringContainsString("'player.matches' =>", $layout);
        $this->assertStringContainsString("'player.heroes' =>", $layout);
        $this->assertStringContainsString("'player.records' =>", $layout);
        $this->assertStringContainsString("'player.peers' =>", $layout);
        $this->assertStringContainsString('bg-[#2d313e] text-white', $layout);
        $this->assertSame(1, substr_count($layout, 'nav-shell'));
        $this->assertMatchesRegularExpression(
            "/'player' =>.*'player\\.matches' =>.*'player\\.heroes' =>.*'player\\.peers' =>.*'player\\.records' =>/s",
            $layout
        );

        $this->assertStringNotContainsString("@include('components.player-tabs'", $profileLayout);
        $this->assertStringNotContainsString('<x-player-tabs', $profileLayout);
        $this->assertStringNotContainsString('role="tablist"', $profileLayout);

        $this->assertStringNotContainsString('aria-label="Хлебные крошки"', $layout);
        $this->assertStringNotContainsString('dotahub-recent-data', $layout);
    }

    public function test_breadcrumbs_are_removed_from_every_view(): void
    {
        $files = array_merge(
            glob(resource_path('views/*/*.blade.php')) ?: [],
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/components/*.blade.php')) ?: [],
            glob(resource_path('views/dota/partials/*.blade.php')) ?: [],
        );

        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString('aria-label="Хлебные крошки"', $source, $file);
            $this->assertStringNotContainsString('class="breadcrumb', $source, $file);
        }
    }

    public function test_home_renders_the_header_without_profile_navigation(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('DOTAHUB', false);
        $response->assertDontSee('aria-label="Navigation"', false);
        $response->assertDontSee('dotahub-recent-data', false);
    }
}
