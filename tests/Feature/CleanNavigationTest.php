<?php

namespace Tests\Feature;

use Tests\TestCase;

class CleanNavigationTest extends TestCase
{
    public function test_profile_tabs_are_rendered_only_by_the_profile_layout(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $profileLayout = (string) file_get_contents(resource_path('views/dota/layouts/profile.blade.php'));
        $tabs = (string) file_get_contents(resource_path('views/components/player-tabs.blade.php'));

        $this->assertStringNotContainsString('aria-label="Navigation"', $layout);
        $this->assertStringNotContainsString('nav-shell', $layout);
        $this->assertStringNotContainsString("'player.matches' =>", $layout);
        $this->assertStringNotContainsString("'player.heroes' =>", $layout);
        $this->assertStringNotContainsString("'player.records' =>", $layout);
        $this->assertStringNotContainsString("'player.peers' =>", $layout);
        $this->assertStringNotContainsString('bg-[#2d313e] text-white', $layout);

        $this->assertStringContainsString("@include('components.player-tabs'", $profileLayout);
        $this->assertStringContainsString('role="tablist"', $tabs);
        $this->assertSame(1, substr_count($profileLayout, "@include('components.player-tabs'"));

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
