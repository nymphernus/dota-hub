<?php

namespace Tests\Feature;

use Tests\TestCase;

class CleanNavigationTest extends TestCase
{
    public function test_navigation_is_a_horizontal_menu_with_a_recent_pages_dropdown(): void
    {
        $javascript = (string) file_get_contents(resource_path('js/app.js'));
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("Alpine.data('recentLinks'", $javascript);
        $this->assertStringContainsString("const RECENT_STORAGE_KEY = 'dotahub.recent.v1'", $javascript);
        $this->assertStringContainsString('sessionStorage.setItem(RECENT_STORAGE_KEY', $javascript);
        $this->assertStringContainsString('sessionStorage.getItem(RECENT_STORAGE_KEY', $javascript);
        $this->assertStringContainsString("document.getElementById('dotahub-recent-data')", $javascript);

        $this->assertStringContainsString('aria-label="Основная навигация"', $layout);
        $this->assertStringContainsString('x-data="recentLinks()"', $layout);
        $this->assertStringContainsString('id="dotahub-recent-data"', $layout);
        $this->assertStringContainsString('aria-label="Недавние страницы"', $layout);
        $this->assertStringContainsString('bg-[#2d313e] text-white', $layout);
        $this->assertStringContainsString('text-gray-400 hover:bg-[#111318] hover:text-white', $layout);

        $this->assertStringNotContainsString("Alpine.data('tabs'", $javascript);
        $this->assertStringNotContainsString('dotahub.tabs.v1', $javascript);
        $this->assertStringNotContainsString('x-data="tabs()"', $layout);
        $this->assertStringNotContainsString('Навигация по вкладкам', $layout);
        $this->assertStringNotContainsString('Закрыть вкладку', $layout);
        $this->assertStringNotContainsString('Новая вкладка', $layout);
        $this->assertStringNotContainsString('close(tab, $event)', $layout);
    }

    public function test_breadcrumbs_are_removed_from_every_view(): void
    {
        $files = array_merge(
            glob(resource_path('views/*/*.blade.php')),
            glob(resource_path('views/*.blade.php')),
            glob(resource_path('views/components/*.blade.php')),
            glob(resource_path('views/dota/partials/*.blade.php')),
        );

        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString('aria-label="Хлебные крошки"', $source, $file);
            $this->assertStringNotContainsString('class="breadcrumb', $source, $file);
        }
    }

    public function test_home_renders_the_menu_without_a_current_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('aria-label="Основная навигация"', false);
        $response->assertSee('id="dotahub-recent-data"', false);
        $response->assertSee('>Главная</a>', false);
        $response->assertDontSee('aria-label="Навигация по вкладкам"', false);
        $response->assertDontSee('aria-label="Закрыть вкладку', false);
    }
}
