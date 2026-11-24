<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiAssetsTest extends TestCase
{
    public function test_hero_wheel_matches_current_behavior(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/partials/hero-wheel.blade.php'));
        $javascript = (string) file_get_contents(resource_path('js/heatmap.js'));

        $this->assertStringContainsString('viewBox="-10 -10 320 320"', $source);
        $this->assertStringContainsString('max-w-[300px] mx-auto h-[300px]', $source);
        $this->assertStringContainsString('$outerRadius = 140.0;', $source);
        $this->assertStringContainsString('$innerRadius = 80.0;', $source);
        $this->assertStringContainsString('<circle cx="150" cy="150" r="80"', $source);
        $this->assertStringContainsString('id="grad-top"', $source);
        $this->assertStringContainsString("fill=\"{{ \$sector['is_top'] ? 'url(#grad-top)' : 'url(#grad-normal)' }}\"", $source);
        $this->assertStringContainsString('stroke="none"', $source);
        $this->assertStringContainsString('stroke-width="0"', $source);
        $this->assertStringContainsString('data-wheel-tooltip', $source);
        $this->assertStringContainsString('data-hero-image="{{ $sector[\'image\'] }}"', $source);
        $this->assertStringContainsString('data-hero-wins="{{ $sector[\'wins\'] }}"', $source);
        $this->assertStringContainsString('data-hero-losses="{{ $sector[\'losses\'] }}"', $source);
        $this->assertStringContainsString('id="wheel-tooltip"', $source);
        $this->assertStringContainsString("'icon' => \$hero['image'] ?? \$hero['minimap_icon'] ?? (\$hero['icon'] ?? '')", $source);

        $this->assertStringNotContainsString('<title>', $source);
        $this->assertStringNotContainsString('<canvas', $source);
        $this->assertStringNotContainsString('chart.js', $source);
        $this->assertStringNotContainsString('Только рейтинг', $source);
        $this->assertStringNotContainsString("Alpine.data('heroWheel'", (string) file_get_contents(resource_path('js/app.js')));

        $this->assertStringContainsString("document.getElementById('wheel-tooltip')", $javascript);
        $this->assertStringContainsString("slice.dataset.heroImage", $javascript);
        $this->assertStringContainsString("slice.dataset.heroWins", $javascript);
        $this->assertStringContainsString("slice.dataset.heroLosses", $javascript);
        $this->assertStringContainsString("window.dotahubI18n", $javascript);
    }

    public function test_heatmap_uses_native_date_i18n_and_tooltip(): void
    {
        $source = (string) file_get_contents(resource_path('js/heatmap.js'));
        $player = (string) file_get_contents(resource_path('views/dota/player.blade.php'));

        $this->assertStringContainsString('new Date(Date.UTC(', $source);
        $this->assertStringContainsString('(date.getUTCDay() + 6) % 7', $source);
        $this->assertStringContainsString('columnCount', $source);
        $this->assertStringContainsString('window.dotahubI18n', $source);
        $this->assertStringContainsString("document.getElementById('heatmap-tooltip')", $source);
        $this->assertStringContainsString('data-match-url-template=', $player);
        $this->assertStringContainsString("'date' => '__DATE__'", $player);
        $this->assertStringContainsString("matchUrlTemplate.replace('__DATE__', day.date)", $source);
        $this->assertStringNotContainsString('chart.js', $source);
    }

    public function test_assets_use_dark_only_flat_palette(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('--color-ink-900: #111318', $css);
        $this->assertStringContainsString('--color-ink-800: #1c1e26', $css);
        $this->assertStringContainsString('--color-line: #2d313e', $css);
        $this->assertStringContainsString('--color-win: #10b981', $css);
        $this->assertStringContainsString('--color-loss: #ef4444', $css);
        $this->assertStringContainsString("--font-sans: 'Inter'", $css);
        $this->assertStringContainsString('box-shadow: none !important', $css);
        $this->assertStringContainsString('border-radius: 0 !important', $css);
        $this->assertStringNotContainsString('gold-', $css);
        $this->assertStringNotContainsString('#e2b02b', $css);

        $this->assertStringContainsString('<html lang="{{ str_replace(\'_\', \'-\', app()->getLocale()) }}" class="dark">', $layout);
        $this->assertStringContainsString('>DOTAHUB</a>', $layout);
        $this->assertStringNotContainsString('themeToggle', $layout);
        $this->assertStringNotContainsString('localStorage', $layout);
        $this->assertStringNotContainsString('dark:', $layout);
    }

    public function test_views_have_no_rounded_or_shadow_utilities_except_wheel_tooltip(): void
    {
        $files = array_merge(
            glob(resource_path('views/*/*.blade.php')) ?: [],
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/components/*.blade.php')) ?: [],
            glob(resource_path('views/errors/*.blade.php')) ?: [],
            glob(resource_path('views/dota/partials/*.blade.php')) ?: [],
            glob(resource_path('views/vendor/pagination/*.blade.php')) ?: [],
        );
        $files = array_values(array_unique($files));
        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\brounded-(xl|2xl|3xl|full)\b/', $source, $file);

            $shadowSource = str_ends_with($file, 'hero-wheel.blade.php')
                || str_ends_with($file, 'player.blade.php')
                ? str_replace('shadow-xl', '', $source)
                : $source;
            $this->assertDoesNotMatchRegularExpression('/\b(?:[a-z-]+:)*shadow(?:-[^\s"\']+)?\b/', $shadowSource, $file);
        }
    }

    public function test_match_rows_use_dense_zebra_striping_with_result_borders(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/partials/match-row.blade.php'));

        $this->assertStringContainsString('odd:bg-[#1c1e26] even:bg-[#111318]', $source);
        $this->assertStringContainsString('border-l-4 border-green-500', $source);
        $this->assertStringContainsString('border-l-4 border-red-500', $source);
        $this->assertStringContainsString("{{ \$match['win'] ? 'W' : 'L' }}", $source);
    }

    public function test_profile_uses_dense_grid_and_key_sections(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/player.blade.php'));

        $this->assertStringContainsString('grid grid-cols-12 gap-4', $source);
        $this->assertStringContainsString('lg:col-span-8', $source);
        $this->assertStringContainsString('lg:col-span-4', $source);
        $this->assertStringContainsString("__('ui.hero_wheel')", $source);
        $this->assertStringContainsString("__('ui.activity')", $source);
        $this->assertStringContainsString("__('ui.lifetime_stats')", $source);
        $this->assertStringContainsString("__('ui.allies')", $source);
        $this->assertStringContainsString('id="activity-heatmap"', $source);
        $this->assertStringContainsString('id="dotahub-heatmap-data"', $source);
        $this->assertStringContainsString('data-match-url-template=', $source);
    }

    public function test_match_details_use_flat_table_without_draft_or_back_links(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/match.blade.php'));

        $this->assertStringContainsString('odd:bg-[#1c1e26] even:bg-[#111318]', $source);
        $this->assertStringContainsString("__('ui.match_squads')", $source);
        $this->assertStringNotContainsString('Пики и баны', $source);
        $this->assertStringNotContainsString('Вернуться к профилю', $source);
        $this->assertStringNotContainsString('На главную', $source);
    }

    public function test_refresh_control_is_a_square_icon_beside_favorite_star(): void
    {
        $source = (string) file_get_contents(resource_path('views/components/player-header.blade.php'));

        $this->assertStringContainsString('data-favorite-star', $source);
        $this->assertStringContainsString("aria-label=\"{{ __('ui.update') }}\"", $source);
        $this->assertStringContainsString('grid h-8 w-8 shrink-0 place-items-center', $source);
        $this->assertStringContainsString('player.refresh', $source);
    }

    public function test_no_gold_accents_remain_in_assets(): void
    {
        $files = array_merge(
            [resource_path('css/app.css'), resource_path('js/heatmap.js')],
            glob(resource_path('views/*/*.blade.php')) ?: [],
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/components/*.blade.php')) ?: [],
            glob(resource_path('views/errors/*.blade.php')) ?: [],
            glob(resource_path('views/dota/partials/*.blade.php')) ?: [],
            glob(resource_path('views/vendor/pagination/*.blade.php')) ?: [],
        );

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString('#e2b02b', $source, $file);
            $this->assertStringNotContainsString('gold-', $source, $file);
        }
    }
}
