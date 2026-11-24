<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiAssetsTest extends TestCase
{
    public function test_hero_wheel_has_ranked_toggle_center_winrate_and_rich_tooltip(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/partials/hero-wheel.blade.php'));
        $app = (string) file_get_contents(resource_path('js/app.js'));
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $javascript = (string) file_get_contents(resource_path('js/heatmap.js'));

        $this->assertStringContainsString('<svg', $source);
        $this->assertStringContainsString('<image', $source);
        $this->assertStringContainsString(':href="sector.icon"', $source);
        $this->assertStringNotContainsString('xlink:href', $source);
        $this->assertStringContainsString('fill="#1c1e26"', $source);
        $this->assertStringContainsString('$wheelTotal', $source);
        $this->assertStringContainsString('* 360', $source);
        $this->assertStringContainsString('$iconSize', $source);
        $this->assertStringContainsString('deg2rad($middleAngle)', $source);
        $this->assertStringContainsString('hero-wheel__slice', $source);
        $this->assertStringContainsString('hero-wheel__sector', $source);
        $this->assertStringContainsString('cursor-pointer hover:opacity-80 focus:outline-none outline-none ring-0', $source);

        $this->assertStringContainsString('Только рейтинг', $source);
        $this->assertStringContainsString('x-model="rankedOnly"', $source);
        $this->assertStringContainsString('x-for="sector in sectors"', $source);
        $this->assertStringContainsString('ranked_games', $source);
        $this->assertStringContainsString('ranked_wins', $source);
        $this->assertStringContainsString('ranked_losses', $source);
        $this->assertStringContainsString('class="text-2xl font-bold"', $source);
        $this->assertStringContainsString('{{ $overallWinrate }}%', $source);
        $this->assertStringContainsString('hero-wheel-all-data', $source);
        $this->assertStringContainsString('hero-wheel-ranked-data', $source);

        $this->assertStringContainsString("Alpine.data('heroWheel'", $app);
        $this->assertStringContainsString("return this.rankedOnly ? this.rankedSectors : this.allSectors;", $app);
        $this->assertStringContainsString('data-all-winrate', $source);
        $this->assertStringContainsString('data-ranked-winrate', $source);

        $this->assertStringContainsString('.hero-wheel__slice {', $css);
        $this->assertStringContainsString('transform-origin: 120px 120px', $css);
        $this->assertStringContainsString('.hero-wheel__slice:hover,', $css);
        $this->assertStringContainsString('transform: scale(1.05)', $css);
        $this->assertStringNotContainsString('.hero-wheel__slice:hover .hero-wheel__sector', $css);

        $this->assertStringNotContainsString('<defs>', $source);
        $this->assertStringNotContainsString('<radialGradient', $source);
        $this->assertStringNotContainsString('url(#hero-wheel-gradient-', $source);
        $this->assertStringNotContainsString('<canvas', $source);
        $this->assertStringNotContainsString('chart.js', $source);
        $this->assertStringNotContainsString('drawImage', $source);

        $this->assertStringContainsString('id="wheel-tooltip" class="hidden fixed z-50 bg-[#1c1e26] border border-[#2d313e] rounded p-2 text-white shadow-xl flex items-center gap-3"', $source);
        $this->assertStringContainsString('data-wheel-tooltip', $source);
        $this->assertStringContainsString(':data-hero-image="sector.icon"', $source);
        $this->assertStringContainsString(':data-hero-wins="sector.wins"', $source);
        $this->assertStringContainsString(':data-hero-losses="sector.losses"', $source);
        $this->assertStringContainsString("document.getElementById('wheel-tooltip')", $javascript);
        $this->assertStringContainsString("image.className = 'w-12 h-12 object-cover'", $javascript);
        $this->assertStringContainsString("name.className = 'font-bold'", $javascript);
        $this->assertStringContainsString("details.className = 'text-sm text-gray-400'", $javascript);
        $this->assertStringContainsString('Побед /', $javascript);
        $this->assertStringContainsString('Поражений', $javascript);
        $this->assertStringContainsString("addEventListener('mouseover'", $javascript);
        $this->assertStringContainsString("addEventListener('mousemove'", $javascript);
        $this->assertStringContainsString("addEventListener('mouseout'", $javascript);
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
        $hero = (string) file_get_contents(resource_path('views/dota/hero.blade.php'));
        $vite = (string) file_get_contents(base_path('vite.config.js'));
        $package = (string) file_get_contents(base_path('package.json'));

        $this->assertStringContainsString('--color-ink-900: #111318', $css);
        $this->assertStringContainsString('--color-ink-800: #1c1e26', $css);
        $this->assertStringContainsString('--color-line: #2d313e', $css);
        $this->assertStringContainsString('--color-mist-200: #d1d5db', $css);
        $this->assertStringContainsString('--color-mist-400: #8b94a5', $css);
        $this->assertStringNotContainsString('--color-gold-', $css);
        $this->assertStringNotContainsString('#e2b02b', $css);
        $this->assertStringContainsString('--color-accent-400: #2d313e', $css);
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
        $this->assertStringContainsString('--color-yellow-500: #eab308', $css);
        $this->assertStringNotContainsString('--color-blue-500', $css);
        $this->assertStringNotContainsString('--color-sky-500', $css);
        $this->assertStringNotContainsString('#3b82f6', $css);
        $this->assertStringNotContainsString('blue-500', $css);
        $this->assertStringNotContainsString('sky-500', $css);
        $this->assertStringNotContainsString('radial-gradient', $css);
        $this->assertStringNotContainsString('--color-radar-', $css);

        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter', $layout);
        $this->assertStringContainsString('border-b border-[#2d313e] bg-[#111318]/90 backdrop-blur-md', $layout);
        $this->assertStringContainsString('bg-[#d32f2f] px-2 py-1 text-2xl leading-none tracking-tighter text-white', $layout);
        $this->assertStringContainsString('>DOTAHUB</a>', $layout);
        $this->assertStringNotContainsString('font-black', $layout);
        $this->assertStringNotContainsString('font-normal', $layout);
        $this->assertStringNotContainsString('font-light', $layout);
        $this->assertStringNotContainsString('rounded-sm', $layout);
        $this->assertStringNotContainsString('>dotahub</span>', $layout);
        $this->assertStringNotContainsString('gold-', $layout);
        $this->assertStringContainsString('<link rel="icon" href="/favicon.ico">', $layout);
        $this->assertStringContainsString('>dotahub</p>', $layout);
        $this->assertStringContainsString('&copy; {{ date(\'Y\') }} dotahub', $layout);
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertSame("\x00\x00", substr((string) file_get_contents(public_path('favicon.ico')), 0, 2));
        $this->assertStringNotContainsString('>Dota 2</', $layout);
        $this->assertStringNotContainsString('uppercase tracking-[0.2em] text-mist-500 sm:block', $layout);
        $this->assertStringContainsString('content="#111318"', $layout);
        $this->assertStringNotContainsString('radar', $layout);
        $this->assertStringNotContainsString('Dota Plus style', $hero);

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

            // The rich wheel tooltip is the one intentionally elevated layer.
            $shadowSource = str_ends_with($file, 'hero-wheel.blade.php')
                ? str_replace('shadow-xl', '', $source)
                : $source;
            $this->assertDoesNotMatchRegularExpression('/\b(?:[a-z-]+:)*shadow(?:-[^\s"\']+)?\b/', $shadowSource, $file);

            preg_match_all('/\b(?:[a-z-]+:)*ring(?:-[^\s"\']+)?\b/', $source, $ringMatches);

            foreach ($ringMatches[0] as $ring) {
                $this->assertContains($ring, ['ring-0'], $file);
            }

            $this->assertDoesNotMatchRegularExpression('/\bborder-white(?:\/|\b)/', $source, $file);
            $this->assertStringNotContainsString('blur-3xl', $source, $file);
            $this->assertStringNotContainsString('bg-gradient', $source, $file);
            $this->assertStringNotContainsString('blue-500', $source, $file);
            $this->assertStringNotContainsString('sky-500', $source, $file);
        }
    }

    public function test_match_rows_use_dense_zebra_striping_with_result_borders(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/partials/match-row.blade.php'));
        $matches = (string) file_get_contents(resource_path('views/dota/matches.blade.php'));

        $this->assertStringContainsString('odd:bg-[#1c1e26] even:bg-[#111318]', $source);
        $this->assertStringContainsString('border-l-4 border-yellow-500', $source);
        $this->assertStringContainsString('border-l-4 border-red-500', $source);
        $this->assertStringContainsString('bg-yellow-500/20 text-yellow-500', $source);
        $this->assertStringContainsString("'bg-[#2d313e] text-mist-300' => \$isTurbo && ! \$isRanked", $source);
        $this->assertStringNotContainsString('blue-500', $source);
        $this->assertStringNotContainsString('sky-500', $source);
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
        $activity = strpos($source, '>Активность<');
        $lifetime = strpos($source, '>Статистика за всё время<');
        $allies = strpos($source, '>Союзники<');

        $this->assertNotFalse($wheel);
        $this->assertNotFalse($activity);
        $this->assertNotFalse($lifetime);
        $this->assertNotFalse($allies);
        $this->assertLessThan($activity, $wheel);
        $this->assertLessThan($lifetime, $activity);
        $this->assertLessThan($allies, $lifetime);
    }

    public function test_filters_use_a_hero_modal_a_custom_calendar_and_heroes_use_a_dense_table(): void
    {
        $matches = (string) file_get_contents(resource_path('views/dota/matches.blade.php'));
        $heroes = (string) file_get_contents(resource_path('views/dota/heroes.blade.php'));
        $matchPagination = (string) file_get_contents(resource_path('views/vendor/pagination/dotahub.blade.php'));
        $heroPagination = (string) file_get_contents(resource_path('views/dota/partials/hero-pagination.blade.php'));

        $this->assertStringContainsString('id="filter-hero"', $matches);
        $this->assertStringContainsString('>Выбрать героя</span>', $matches);
        $this->assertStringContainsString('id="filter-hero-grid"', $matches);
        $this->assertStringContainsString('heroPickerHeroes', $matches);
        $this->assertStringContainsString("hero.icon || hero.image", $matches);
        $this->assertStringContainsString('class="h-8 w-8 overflow-hidden bg-ink-800"', $matches);
        $this->assertStringNotContainsString("'id' => 'filter-hero'", $matches);
        $this->assertStringContainsString('bg-[#1c1e26] text-white border-[#2d313e] rounded px-3 py-2', $matches);

        $this->assertStringContainsString('id="filter-calendar"', $matches);
        $this->assertStringContainsString('aria-label="Календарь"', $matches);
        $this->assertStringContainsString('calendarDays', $matches);
        $this->assertStringContainsString('shiftMonth(', $matches);
        $this->assertStringContainsString('chooseDate(day.iso)', $matches);
        $this->assertStringContainsString('За всё время', $matches);
        $this->assertStringNotContainsString('type="date"', $matches);

        $this->assertStringContainsString('perPage: 24', $heroes);
        $this->assertStringContainsString('<th class="px-2 py-1 text-left font-semibold sm:px-5">ГЕРОЙ</th>', $heroes);
        $this->assertStringContainsString('<th class="px-2 py-1 text-left font-semibold">МАТЧИ</th>', $heroes);
        $this->assertStringContainsString('<th class="px-2 py-1 text-left font-semibold">ПРОЦЕНТ ПОБЕД</th>', $heroes);
        $this->assertStringContainsString('<th class="px-2 py-1 text-left font-semibold">KDA</th>', $heroes);
        $this->assertStringContainsString('odd:bg-[#1b1f2e] even:bg-[#0f111a]', $heroes);
        $this->assertStringContainsString('class="px-2 py-1 sm:px-5"', $heroes);
        $this->assertStringNotContainsString('grid grid-cols-2 gap-2 p-3 md:grid-cols-4 lg:grid-cols-6', $heroes);
        $this->assertStringContainsString('id="heroes-hero-filter"', $heroes);
        $this->assertStringContainsString('id="heroes-hero-grid"', $heroes);
        $this->assertStringContainsString('heroPickerQuery', $heroes);
        $this->assertStringContainsString('filteredHeroPickerHeroes', $heroes);
        $this->assertStringContainsString("hero.icon || hero.image", $heroes);

        $sharedClasses = [
            'grid h-9 min-w-9 place-items-center rounded-none border border-line bg-ink-900/40 px-3 text-xs font-semibold text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200',
            'border-mist-400 bg-mist-400',
            'text-ink-950',
            'grid h-9 w-9 place-items-center rounded-none border border-line',
        ];

        foreach ($sharedClasses as $classes) {
            $this->assertStringContainsString($classes, $matchPagination);
            $this->assertStringContainsString($classes, $heroPagination);
        }

        $this->assertStringContainsString('aria-label="Пагинация"', $matchPagination);
        $this->assertStringContainsString('aria-label="Пагинация"', $heroPagination);
    }

    public function test_match_details_use_the_flat_striped_table_without_draft_or_back_links(): void
    {
        $source = (string) file_get_contents(resource_path('views/dota/match.blade.php'));

        $this->assertStringContainsString('<th class="px-2 py-1 font-semibold">IMP</th>', $source);
        $this->assertStringContainsString('odd:bg-[#1c1e26] even:bg-[#111318]', $source);
        $this->assertStringContainsString("'border-emerald-500 bg-emerald-500/10' => \$teamKey === 'radiant'", $source);
        $this->assertStringContainsString("'border-rose-500 bg-rose-500/10' => \$teamKey === 'dire'", $source);
        $this->assertStringContainsString('number_format($player[\'impact\'], 1, \'.\', \'\')', $source);
        $this->assertStringNotContainsString('Пики и баны', $source);
        $this->assertStringNotContainsString('picks_bans', $source);
        $this->assertStringNotContainsString('Вернуться к профилю', $source);
        $this->assertStringNotContainsString('На главную', $source);
    }

    public function test_no_gold_accents_remain_in_assets(): void
    {
        $files = [
            resource_path('css/app.css'),
            resource_path('js/heatmap.js'),
        ];
        $files = array_merge(
            $files,
            glob(resource_path('views/*/*.blade.php')),
            glob(resource_path('views/*.blade.php')),
            glob(resource_path('views/components/*.blade.php')),
            glob(resource_path('views/errors/*.blade.php')),
            glob(resource_path('views/dota/partials/*.blade.php')),
            glob(resource_path('views/vendor/pagination/*.blade.php')),
        );

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString('#e2b02b', $source, $file);
            $this->assertStringNotContainsString('gold-', $source, $file);
        }

        $css = (string) file_get_contents(resource_path('css/app.css'));
        $javascript = (string) file_get_contents(resource_path('js/heatmap.js'));

        $this->assertStringNotContainsString('--color-gold-', $css);
        $this->assertStringNotContainsString('bg-amber-', $javascript);
        $this->assertStringNotContainsString('bg-orange-', $javascript);
        $this->assertStringNotContainsString('bg-accent-', $javascript);
        $this->assertStringContainsString('bg-green-500', $javascript);
        $this->assertStringContainsString('bg-red-500', $javascript);
        $this->assertStringContainsString('bg-yellow-500', $javascript);
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
        $this->assertStringContainsString('player.refresh', $source);
        $this->assertStringNotContainsString("x-text=\"loading ? 'Обновляем…' : 'Обновить'\"", $source);
    }
}
