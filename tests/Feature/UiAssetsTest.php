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
        $this->assertStringContainsString('fill="#1c1e26"', $source);
        $this->assertStringContainsString('$wheelTotal', $source);
        $this->assertStringContainsString('* 360', $source);
        $this->assertStringContainsString('$iconSize', $source);
        $this->assertStringContainsString('deg2rad($middleAngle)', $source);
        $this->assertStringContainsString('hero-wheel__slice', $source);
        $this->assertStringContainsString('hero-wheel__sector', $source);
        $this->assertStringContainsString('% побед</title>', $source);
        $this->assertStringContainsString("{{ \$sector['name'] }}: {{ \$sector['games'] }} игр", $source);
        $this->assertStringContainsString("{{ \$sector['winrate'] }}% побед", $source);
        $this->assertStringContainsString('<a', $source);
        $this->assertStringContainsString('href="{{ route(\'dota.player.hero\', [$playerId, $sector[\'hero_id\']]) }}"', $source);
        $this->assertStringContainsString('cursor-pointer hover:opacity-80', $source);

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

    public function test_assets_use_dotabuff_palette_inter_and_red_square_logo(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $vite = (string) file_get_contents(base_path('vite.config.js'));
        $package = (string) file_get_contents(base_path('package.json'));

        $this->assertStringContainsString('--color-ink-900: #111318', $css);
        $this->assertStringContainsString('--color-ink-800: #1c1e26', $css);
        $this->assertStringContainsString('--color-line: #2d313e', $css);
        $this->assertStringContainsString('--color-mist-200: #d1d5db', $css);
        $this->assertStringContainsString('--color-mist-400: #8b94a5', $css);
        $this->assertStringContainsString('--color-gold-400: #e2b02b', $css);
        $this->assertStringContainsString('--color-accent-400: #3b82f6', $css);
        $this->assertStringContainsString('--color-win: #10b981', $css);
        $this->assertStringContainsString('--color-loss: #ef4444', $css);
        $this->assertStringContainsString("--font-sans: 'Inter'", $css);
        $this->assertStringContainsString('@apply rounded-none border border-line bg-ink-800;', $css);
        $this->assertStringContainsString('border-bottom: 1px solid #2d313e;', $css);
        $this->assertStringContainsString('box-shadow: none !important', $css);
        $this->assertStringContainsString('border-radius: 0 !important', $css);
        $this->assertStringContainsString('font-weight: 700 !important', $css);
        $this->assertStringContainsString('color: #ffffff !important', $css);
        $this->assertStringContainsString('.section-heading {', $css);
        $this->assertStringContainsString('@apply text-xs font-bold uppercase tracking-wider text-mist-400;', $css);
        $this->assertStringContainsString('--color-green-500: #10b981', $css);
        $this->assertStringContainsString('--color-red-500: #ef4444', $css);
        $this->assertStringNotContainsString('radial-gradient', $css);
        $this->assertStringNotContainsString('--color-radar-', $css);

        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter', $layout);
        $this->assertStringContainsString('border-b border-[#2d313e] bg-[#111318]/90 backdrop-blur-md', $layout);
        $this->assertStringContainsString('bg-[#d32f2f] px-2 py-1 text-white font-black uppercase text-xl tracking-tighter leading-none rounded-sm', $layout);
        $this->assertStringContainsString('>DOTAHUB</a>', $layout);
        $this->assertStringNotContainsString('>dotahub</span>', $layout);
        $this->assertStringNotContainsString('text-gold-400', $layout);
        $this->assertStringNotContainsString('>Dota 2</', $layout);
        $this->assertStringNotContainsString('uppercase tracking-[0.2em] text-mist-500 sm:block', $layout);
        $this->assertStringContainsString('content="#111318"', $layout);
        $this->assertStringNotContainsString('radar', $layout);

        $this->assertStringContainsString('resources/js/heatmap.js', $vite);
        $this->assertStringNotContainsString('resources/js/charts.js', $vite);
        $this->assertStringNotContainsString('chart.js', $package);
    }

    public function test_all_views_use_flat_square_corners_and_no_shadows(): void
    {
        $files = glob(resource_path('views/*/*.blade.php'));
        $files = array_merge($files, glob(resource_path('views/*.blade.php')));
        $files = array_merge($files, glob(resource_path('views/components/*.blade.php')));
        $files = array_merge($files, glob(resource_path('views/errors/*.blade.php')));
        $files = array_merge($files, glob(resource_path('views/dota/partials/*.blade.php')));
        $files = array_merge($files, glob(resource_path('views/vendor/pagination/*.blade.php')));
        $files = array_unique($files);

        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression('/\brounded-(xl|2xl|3xl|full)\b/', $source, $file);
            $this->assertDoesNotMatchRegularExpression('/\b(?:[a-z-]+:)*(?:shadow|ring)(?:-[^\s"\']+)?/', $source, $file);
            $this->assertDoesNotMatchRegularExpression('/\bborder-white(?:\/|\b)/', $source, $file);
            $this->assertStringNotContainsString('blur-3xl', $source, $file);
            $this->assertStringNotContainsString('bg-gradient', $source, $file);
        }
    }

    public function test_match_rows_use_dense_zebra_striping_with_result_borders(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/partials/match-row.blade.php'));
        $matches = (string) file_get_contents(resource_path('views/dota/matches.blade.php'));

        $this->assertStringContainsString('odd:bg-[#1c1e26] even:bg-[#111318]', $source);
        $this->assertStringContainsString('border-l-4 border-green-500', $source);
        $this->assertStringContainsString('border-l-4 border-red-500', $source);
        $this->assertStringContainsString('px-3 py-1 sm:px-4', $source);
        $this->assertStringNotContainsString('bg-emerald-500/[0.055]', $source);
        $this->assertStringNotContainsString('bg-rose-500/[0.055]', $source);
        $this->assertStringNotContainsString('py-2.5', $source);
        $this->assertStringNotContainsString('py-2.5', $matches);
    }

    public function test_profile_uses_dense_twelve_column_grid_with_reordered_sidebar(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/player.blade.php'));

        $this->assertStringContainsString('grid grid-cols-12 gap-4', $source);
        $this->assertStringContainsString('col-span-12 min-w-0 space-y-4 lg:col-span-8', $source);
        $this->assertStringContainsString('col-span-12 min-w-0 space-y-4 lg:col-span-4', $source);
        $this->assertStringContainsString('border border-[#2d313e] bg-[#1c1e26] rounded-none', $source);
        $this->assertStringContainsString('class="section-heading"', $source);
        $this->assertStringContainsString('py-1', $source);
        $this->assertStringContainsString("['heroes' => \$heroUsage, 'playerId' => \$player['account_id']]", $source);

        foreach (['gap-6', 'space-y-6', 'p-6', 'rounded-xl', 'rounded-2xl'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        $wheel = strpos($source, '>Колесо героев<');
        $lifetime = strpos($source, '>Lifetime Stats<');
        $activity = strpos($source, '>Активность<');
        $allies = strpos($source, '>Союзники<');

        $this->assertNotFalse($wheel);
        $this->assertNotFalse($lifetime);
        $this->assertNotFalse($activity);
        $this->assertNotFalse($allies);
        $this->assertLessThan($lifetime, $wheel);
        $this->assertLessThan($activity, $lifetime);
        $this->assertLessThan($allies, $activity);
    }

    public function test_heatmap_days_link_to_the_dated_matches_page(): void
    {
        $javascript = (string) file_get_contents(resource_path('js/heatmap.js'));
        $player = (string) file_get_contents(resource_path('views/dota/player.blade.php'));

        $this->assertStringContainsString('data-match-url-template=', $player);
        $this->assertStringContainsString("'date' => '__DATE__'", $player);
        $this->assertStringContainsString("document.createElement('a')", $javascript);
        $this->assertStringContainsString("matchUrlTemplate.replace('__DATE__', day.date)", $javascript);
    }

    public function test_refresh_control_is_a_square_icon_beside_the_favorite_star(): void
    {
        $source = (string) file_get_contents(resource_path('views/components/player-header.blade.php'));

        $this->assertStringContainsString('data-favorite-star', $source);
        $this->assertStringContainsString('aria-label="Обновить"', $source);
        $this->assertStringContainsString('class="grid h-8 w-8 shrink-0 place-items-center', $source);
        $this->assertStringContainsString('dota.player.refresh', $source);
        $this->assertStringNotContainsString("x-text=\"loading ? 'Обновляем…' : 'Обновить'\"", $source);
    }
}
