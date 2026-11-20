<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiAssetsTest extends TestCase
{
    public function test_hero_wheel_is_flat_svg_with_images_and_group_hover_growth(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/partials/hero-wheel.blade.php'));
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('<svg', $source);
        $this->assertStringContainsString('<image', $source);
        $this->assertStringContainsString('xlink:href', $source);
        $this->assertStringContainsString('fill="#1b1f2e"', $source);
        $this->assertStringContainsString('$wheelTotal', $source);
        $this->assertStringContainsString('* 360', $source);
        $this->assertStringContainsString('$iconSize', $source);
        $this->assertStringContainsString('deg2rad($middleAngle)', $source);
        $this->assertStringContainsString('hero-wheel__slice', $source);
        $this->assertStringContainsString('hero-wheel__sector', $source);

        // The icon lives in the same group as the sector, and the group itself
        // is transformed so both move together on hover.
        $slicePosition = strpos($source, '<g class="hero-wheel__slice"');
        $imagePosition = strpos($source, '<image');
        $groupEndPosition = strpos($source, '</g>', $slicePosition);
        $this->assertNotFalse($slicePosition);
        $this->assertNotFalse($imagePosition);
        $this->assertNotFalse($groupEndPosition);
        $this->assertGreaterThan($slicePosition, $imagePosition);
        $this->assertLessThan($groupEndPosition, $imagePosition);

        $this->assertStringContainsString('.hero-wheel__slice {', $css);
        $this->assertStringContainsString('transform-origin: 120px 120px', $css);
        $this->assertStringContainsString('.hero-wheel__slice:hover,', $css);
        $this->assertStringContainsString('transform: scale(1.05)', $css);
        $this->assertStringNotContainsString('.hero-wheel__slice:hover .hero-wheel__sector', $css);

        $this->assertStringNotContainsString('<defs>', $source);
        $this->assertStringNotContainsString('<radialGradient', $source);
        $this->assertStringNotContainsString('url(#hero-wheel-gradient-', $source);
        $this->assertStringNotContainsString('hero-wheel__hub-value', $source);
        $this->assertStringNotContainsString('hero-wheel__hub-label', $source);
        $this->assertStringNotContainsString('героев</text>', $source);
        $this->assertStringNotContainsString('<canvas', $source);
        $this->assertStringNotContainsString('chart.js', $source);
        $this->assertStringNotContainsString('drawImage', $source);
    }

    public function test_heatmap_uses_native_date_for_weekdays_months_and_columns(): void
    {
        $source = (string) file_get_contents(resource_path('js/heatmap.js'));

        $this->assertStringContainsString('new Date(Date.UTC(', $source);
        $this->assertStringContainsString("(date.getUTCDay() + 6) % 7", $source);
        $this->assertStringContainsString('columnCount', $source);
        $this->assertStringContainsString('Math.floor(offset / 7)', $source);
        $this->assertStringContainsString("['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']", $source);
        $this->assertStringContainsString('monthsNode', $source);
        $this->assertStringContainsString('style.gridColumn', $source);
        $this->assertStringContainsString('heatmap.weeks', $source);
        $this->assertStringNotContainsString('chart.js', $source);
    }

    public function test_assets_use_dotabuff_palette_inter_and_text_logo(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $vite = (string) file_get_contents(base_path('vite.config.js'));
        $package = (string) file_get_contents(base_path('package.json'));

        $this->assertStringContainsString('--color-ink-900: #0f111a', $css);
        $this->assertStringContainsString('--color-ink-800: #1b1f2e', $css);
        $this->assertStringContainsString('--color-line: #2a3042', $css);
        $this->assertStringContainsString('--color-mist-200: #e2e8f0', $css);
        $this->assertStringContainsString('--color-mist-400: #8b94a5', $css);
        $this->assertStringContainsString('--color-gold-400: #e2b02b', $css);
        $this->assertStringContainsString('--color-accent-400: #3b82f6', $css);
        $this->assertStringContainsString('--color-win: #10b981', $css);
        $this->assertStringContainsString('--color-loss: #ef4444', $css);
        $this->assertStringContainsString("--font-sans: 'Inter'", $css);
        $this->assertStringContainsString('@apply rounded-xl bg-ink-800 shadow-lg shadow-black/25;', $css);
        $this->assertStringContainsString('border-b border-line', $css);
        $this->assertStringNotContainsString('--color-radar-', $css);

        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter', $layout);
        $this->assertStringContainsString('font-sans font-bold tracking-tight text-2xl leading-none text-white">DotaHub</span>', $layout);
        $this->assertStringNotContainsString('text-gold-400', $layout);
        $this->assertStringNotContainsString('>Dota 2</', $layout);
        $this->assertStringNotContainsString('uppercase tracking-[0.2em] text-mist-500 sm:block', $layout);
        $this->assertStringContainsString('content="#0f111a"', $layout);
        $this->assertStringNotContainsString('radar', $layout);

        $this->assertStringContainsString('resources/js/heatmap.js', $vite);
        $this->assertStringNotContainsString('resources/js/charts.js', $vite);
        $this->assertStringNotContainsString('chart.js', $package);
    }
}
