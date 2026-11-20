<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrowserTabsTest extends TestCase
{
    public function test_tabs_are_registered_as_an_alpine_component_and_persisted_in_session_storage(): void
    {
        $javascript = (string) file_get_contents(resource_path('js/app.js'));
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("Alpine.data('tabs'", $javascript);
        $this->assertStringContainsString("const TAB_STORAGE_KEY = 'dotahub.tabs.v1'", $javascript);
        $this->assertStringContainsString('sessionStorage.setItem(TAB_STORAGE_KEY', $javascript);
        $this->assertStringContainsString('sessionStorage.getItem(TAB_STORAGE_KEY', $javascript);
        $this->assertStringContainsString('document.getElementById(\'dotahub-tab-data\')', $javascript);
        $this->assertStringContainsString('close(tab, $event)', $layout);
        $this->assertStringContainsString('x-data="tabs()"', $layout);
        $this->assertStringContainsString('id="dotahub-tab-data"', $layout);
        $this->assertStringContainsString("'dota.match' => ['url' => url()->current(), 'label' => 'Матч #'.request()->route('matchId')]", $layout);
        $this->assertStringContainsString("'dota.player.hero'", $layout);
        $this->assertStringContainsString('bg-[#1c1e26]', $layout);
        $this->assertStringContainsString("isActive(tab) ? 'bg-[#2d313e] text-white' : 'text-gray-400'", $layout);
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
            $this->assertStringNotContainsString('>Главная</a>', $source, $file);
        }
    }

    public function test_home_renders_the_tab_bar_without_a_current_tab(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('x-data="tabs()"', false);
        $response->assertSee('id="dotahub-tab-data"', false);
        $response->assertSee('aria-label="Навигация по вкладкам"', false);
        $response->assertSee('aria-label="Новая вкладка"', false);
    }
}
