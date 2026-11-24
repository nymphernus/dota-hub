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
        $this->assertStringContainsString("'icon' => \$hero['icon'] ?? \$hero['minimap_icon'] ?? ''", $source);
        $this->assertStringContainsString("__('ui.wins_short_ru')", $source);
        $this->assertStringContainsString("__('ui.losses_short_ru')", $source);
        $this->assertStringContainsString("'image' => \$hero['image'] ?? \$hero['hero_image']", $source);
        $this->assertStringContainsString("'image' => \$hero['image']", $source);

        $this->assertStringNotContainsString('<title>', $source);
        $this->assertStringNotContainsString('<canvas', $source);
        $this->assertStringNotContainsString('chart.js', $source);
        $this->assertStringNotContainsString('Только рейтинг', $source);
        $this->assertStringNotContainsString("Alpine.data('heroWheel'", (string) file_get_contents(resource_path('js/app.js')));

        $this->assertStringContainsString("document.getElementById('wheel-tooltip')", $javascript);
        $this->assertStringContainsString("slice.dataset.heroImage", $javascript);
        $this->assertStringContainsString("const heroImage = slice.dataset.heroImage || '';", $javascript);
        $this->assertStringContainsString("image.className = 'w-16 h-9 object-cover rounded';", $javascript);
        $this->assertStringContainsString("slice.dataset.heroWins", $javascript);
        $this->assertStringContainsString("slice.dataset.heroLosses", $javascript);
        $this->assertStringContainsString("window.dotahubI18n", $javascript);
    }

    public function test_profile_header_tabs_and_lists_follow_current_layout(): void
    {
        $header = (string) file_get_contents(resource_path('views/components/player-header.blade.php'));
        $profile = (string) file_get_contents(resource_path('views/dota/player.blade.php'));
        $peers = (string) file_get_contents(resource_path('views/dota/peers.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/dota/layouts/profile.blade.php'));
        $appLayout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $controller = (string) file_get_contents(app_path('Http/Controllers/DotaPlayerController.php'));

        $this->assertStringContainsString('nav-shell', $appLayout);
        $this->assertStringContainsString('aria-label="Navigation"', $appLayout);
        $this->assertSame(1, substr_count($appLayout, 'nav-shell'));
        $this->assertStringContainsString('blur-md opacity-45', $header);
        $this->assertStringNotContainsString("__('ui.cached_offline')", $header);
        $this->assertStringNotContainsString("@include('components.player-tabs'", $layout);
        $this->assertStringNotContainsString('role="tablist"', $layout);
        $this->assertStringNotContainsString('player-tabs', $profile);

        $this->assertStringNotContainsString('role="tablist"', $profile);
        $this->assertStringNotContainsString('bg-ink-900/60 p-1.5', $profile);
        $this->assertStringContainsString('flex flex-col p-1', $profile);
        $this->assertStringContainsString('class="w-16 h-9 object-cover shrink-0 rounded-sm"', $profile);
        $this->assertStringContainsString('class="flex items-center gap-4 py-2 border-b border-ink-700/50 hover:bg-ink-800/50 px-2 transition"', $profile);
        $this->assertStringContainsString('w-32 shrink-0 text-sm font-bold text-mist-200 truncate', $profile);
        $this->assertStringContainsString('flex items-center gap-2 w-32 shrink-0', $profile);
        $this->assertStringContainsString('class="w-full bg-ink-900 h-1.5 rounded overflow-hidden"', $profile);
        $this->assertStringContainsString('w-32 shrink-0 bg-ink-900 h-1.5 rounded overflow-hidden', $profile);
        $this->assertStringContainsString("__('ui.hero_games_word')", $profile);
        $this->assertStringContainsString("__('ui.hero_win_short')", $profile);
        $this->assertStringContainsString("__('ui.hero_loss_short')", $profile);
        $this->assertStringContainsString("\$hero['wins']", $profile);
        $this->assertStringContainsString("\$hero['losses']", $profile);
        $this->assertStringContainsString('$maxHeroGames', $profile);
        $this->assertStringNotContainsString('bg-blue-500 h-full', $profile);
        $this->assertStringNotContainsString('h-3 rounded overflow-hidden', $profile);
        $this->assertStringNotContainsString("__('ui.games_word')", $profile);
        $this->assertStringContainsString('class="w-10 h-10 object-cover shrink-0"', $profile);
        $this->assertStringContainsString('class="w-10 h-10 object-cover shrink-0"', $peers);
        $this->assertStringContainsString('flex items-center gap-2 w-24 shrink-0', $profile);
        $this->assertStringContainsString('flex items-center gap-4 py-3 border-b border-ink-700/50 hover:bg-ink-800/50 px-4 transition', $peers);
        $this->assertStringContainsString('flex items-center gap-3 w-1/3 min-w-0', $peers);
        $this->assertStringContainsString('flex items-center gap-3 w-1/4 shrink-0', $peers);
        $this->assertStringContainsString('flex items-center gap-3 w-1/4 shrink-0 ml-auto', $peers);
        $this->assertStringContainsString('w-full bg-ink-900 h-1.5 flex rounded overflow-hidden', $profile);
        $this->assertStringContainsString('w-full bg-ink-900 h-2 flex rounded overflow-hidden', $peers);
        $this->assertStringContainsString('w-full bg-ink-900 h-1.5 rounded overflow-hidden', $profile);
        $this->assertStringContainsString('w-full bg-ink-900 h-2 rounded overflow-hidden', $peers);
        $this->assertStringContainsString('bg-yellow-500 h-full', $profile);
        $this->assertStringContainsString('bg-yellow-500 h-full', $peers);
        $this->assertStringContainsString('maxPeerGames', $controller);
        $this->assertStringContainsString('maxHeroGames', $controller);
        $this->assertStringContainsString('$peerGamesScale', $profile);
        $this->assertStringContainsString('$maxPeerGames', $peers);

        $this->assertStringContainsString("'pageTitle' => \$player['nickname'].' - '.__('ui.overview').' - DOTAHUB'", $controller);
        $this->assertStringContainsString('loadMatchDetails(', $controller);
    }

    public function test_heatmap_uses_native_date_i18n_and_tooltip(): void
    {
        $source = (string) file_get_contents(resource_path('js/heatmap.js'));
        $player = (string) file_get_contents(resource_path('views/dota/player.blade.php'));

        $this->assertStringContainsString('new Date(Date.UTC(', $source);
        $this->assertStringContainsString('(date.getUTCDay() + 6) % 7', $source);
        $this->assertStringContainsString('columnCount', $source);
        $this->assertStringContainsString('window.dotahubI18n', $source);
        $this->assertStringContainsString('i18n.wins_tooltip', $source);
        $this->assertStringContainsString('i18n.losses_tooltip', $source);
        $this->assertStringContainsString("document.getElementById('heatmap-tooltip')", $source);
        $this->assertStringContainsString("heatmapTooltip.id = 'heatmap-tooltip'", $source);
        $this->assertStringContainsString('fixed z-[9999] bg-[#1c1e26] border border-[#2d313e] p-2 rounded shadow-xl text-white text-xs pointer-events-none hidden', $source);
        $this->assertStringContainsString('innerHTML', $source);
        $this->assertStringContainsString('<strong>', $source);
        $this->assertStringContainsString("addEventListener('mouseenter'", $source);
        $this->assertStringNotContainsString("setAttribute('title'", $source);
        $this->assertStringNotContainsString('id="heatmap-tooltip"', $player);
        $this->assertStringContainsString('data-match-url-template=', $player);
        $this->assertStringContainsString("'date' => '__DATE__'", $player);
        $this->assertStringContainsString("matchUrlTemplate.replace('__DATE__', day.date)", $source);
        $this->assertStringNotContainsString('chart.js', $source);
    }

    public function test_mode_labels_and_activity_tooltip_wordings_follow_strict_translations(): void
    {
        $ru = (string) file_get_contents(lang_path('ru/ui.php'));
        $en = (string) file_get_contents(lang_path('en/ui.php'));
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $service = (string) file_get_contents(app_path('Services/OpenDota/OpenDotaService.php'));
        $row = (string) file_get_contents(resource_path('views/dota/partials/match-row.blade.php'));
        $matches = (string) file_get_contents(resource_path('views/dota/matches.blade.php'));
        $matchPage = (string) file_get_contents(resource_path('views/dota/match.blade.php'));

        $this->assertStringContainsString("'peers' => 'Союзники'", $ru);
        $this->assertStringContainsString("'peers' => 'Allies'", $en);
        $expectedModeLabels = [
            'mode_1' => 'All Pick',
            'mode_2' => 'Captains Draft',
            'mode_4' => 'Single Draft',
            'mode_5' => 'All Random',
            'mode_16' => 'Captains Mode',
            'mode_18' => 'Ability Draft',
            'mode_22' => 'Ranked',
            'mode_23' => 'Turbo',
        ];
        foreach ($expectedModeLabels as $key => $label) {
            $this->assertStringContainsString("'{$key}' => '{$label}'", $ru);
            $this->assertStringContainsString("'{$key}' => '{$label}'", $en);
        }
        $this->assertStringContainsString("'ranked' => 'Ranked'", $ru);
        $this->assertStringContainsString("'turbo' => 'Turbo'", $ru);
        $this->assertStringContainsString("'normal' => 'Normal'", $ru);
        $this->assertStringNotContainsString('game_mode_label', $row);
        $this->assertStringContainsString("'wins_tooltip' => 'Побед'", $ru);
        $this->assertStringContainsString("'losses_tooltip' => 'Поражений'", $ru);
        $this->assertStringContainsString("'wins_tooltip' => __('ui.wins_tooltip')", $layout);
        $this->assertStringContainsString("'losses_tooltip' => __('ui.losses_tooltip')", $layout);
        $this->assertStringContainsString("'game_mode' => 'Режим игры'", $ru);
        $this->assertStringContainsString("'game_mode' => 'Game Mode'", $en);
        $this->assertStringContainsString("'tooltip_date' => 'Дата:'", $ru);
        $this->assertStringContainsString("'tooltip_date' => 'Date:'", $en);
        $this->assertStringContainsString("'games_tooltip' => 'Игр'", $ru);
        $this->assertStringContainsString("'games_tooltip' => 'Games'", $en);
        $this->assertStringContainsString("'tooltip_date' => __('ui.tooltip_date')", $layout);
        $this->assertStringContainsString("'games_tooltip' => __('ui.games_tooltip')", $layout);
        $this->assertStringContainsString("__('ui.mode_'.\$mode)", $service);
        $this->assertStringContainsString("__('ui.mode_'.\$gameMode)", $row);
        $this->assertStringContainsString("__('ui.game_mode')", $matches);
        $this->assertStringContainsString("@include('dota.partials.match-row', ['matches' => \$matches])", $matches);
        $this->assertStringContainsString("__('ui.mode_'.\$gameMode)", $matchPage);
        $this->assertStringNotContainsString("{{ \$match['game_mode_label'] }}", $matchPage);
        $this->assertStringContainsString("\$this->gameModeLabel(\$gameMode)", $service);
        $this->assertStringContainsString("\$gameMode > 0", $service);
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

    public function test_all_localized_labels_have_strict_english_and_russian_values(): void
    {
        $ru = require lang_path('ru/ui.php');
        $en = require lang_path('en/ui.php');

        $expected = [
            'by_game_mode' => ['ПО РЕЖИМАМ ИГРЫ', 'BY GAME MODE'],
            'by_faction' => ['ПО ФРАКЦИЯМ', 'BY FACTION'],
            'all_time' => ['За всё время', 'All time'],
            'steam_public' => ['Steam: Публичный', 'Steam: Public'],
            'public_match_history' => ['Публичная история матчей', 'Public match history'],
            'lifetime_stats' => ['СТАТИСТИКА ЗА ВСЁ ВРЕМЯ', 'LIFETIME STATS'],
            'avg_stats' => ['СРЕДНИЕ ПОКАЗАТЕЛИ', 'AVERAGE STATS'],
            'faction' => ['ФРАКЦИЯ', 'FACTION'],
            'mode' => ['РЕЖИМ', 'MODE'],
            'matches_word' => ['МАТЧИ', 'MATCHES'],
            'winrate_word' => ['ВИНРЕЙТ', 'WINRATE'],
            'ranked' => ['Ranked', 'Ranked'],
            'turbo' => ['Turbo', 'Turbo'],
        ];

        foreach ($expected as $key => [$russian, $english]) {
            $this->assertSame($russian, $ru[$key], $key);
            $this->assertSame($english, $en[$key], $key);
        }

        $this->assertSame('Russian', $en['russian']);
        $this->assertDoesNotMatchRegularExpression('/[\x{0400}-\x{04FF}]/u', (string) file_get_contents(lang_path('en/ui.php')));

        $files = array_merge(
            glob(resource_path('views/dota/*.blade.php')) ?: [],
            glob(resource_path('views/dota/partials/*.blade.php')) ?: [],
            glob(resource_path('views/components/*.blade.php')) ?: [],
            glob(resource_path('views/layouts/*.blade.php')) ?: [],
            [resource_path('js/heatmap.js')],
        );

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/[\x{0400}-\x{04FF}]/u', (string) file_get_contents($file), $file);
        }

        $player = (string) file_get_contents(resource_path('views/dota/player.blade.php'));
        $metrics = (string) file_get_contents(resource_path('views/components/player-metrics.blade.php'));
        $matches = (string) file_get_contents(resource_path('views/dota/matches.blade.php'));

        $this->assertStringContainsString("__('ui.steam_public')", $player);
        $this->assertStringContainsString("__('ui.public_match_history')", $player);
        $this->assertStringContainsString("__('ui.avg_stats')", $player);
        $this->assertStringContainsString("__('ui.matches_word')", $metrics);
        $this->assertStringContainsString("__('ui.winrate_word')", $metrics);
        $this->assertStringContainsString("__('ui.ranked')", $matches);
        $this->assertStringContainsString("__('ui.turbo')", $matches);
    }

    public function test_hero_picker_reset_button_and_record_labels_follow_current_ui(): void
    {
        $partial = (string) file_get_contents(resource_path('views/dota/partials/filter-hero.blade.php'));
        $matches = (string) file_get_contents(resource_path('views/dota/matches.blade.php'));
        $heroes = (string) file_get_contents(resource_path('views/dota/heroes.blade.php'));
        $service = (string) file_get_contents(app_path('Services/OpenDota/OpenDotaService.php'));
        $ru = require lang_path('ru/ui.php');
        $en = require lang_path('en/ui.php');

        $this->assertStringNotContainsString("__('ui.attr_strength')", $partial);
        $this->assertStringNotContainsString("__('ui.attr_agility')", $partial);
        $this->assertStringNotContainsString("__('ui.attr_intelligence')", $partial);
        $this->assertStringNotContainsString("__('ui.attr_universal')", $partial);
        $this->assertStringContainsString('max-w-6xl', $partial);
        $this->assertStringContainsString('grid grid-cols-8 sm:grid-cols-10 lg:grid-cols-12 gap-1', $partial);
        $this->assertStringContainsString("\$hero['image']", $partial);
        $this->assertStringContainsString('class="w-8 h-8 sm:w-10 sm:h-10 object-cover"', $partial);
        $this->assertStringNotContainsString("\$hero['icon']", $partial);
        $this->assertStringContainsString("@include('dota.partials.filter-hero'", $matches);
        $this->assertStringContainsString("@include('dota.partials.filter-hero'", $heroes);
        $this->assertStringNotContainsString('grid grid-cols-8 gap-1 max-h-[60vh]', $matches);
        $this->assertStringNotContainsString('<select name="hero_id"', $heroes);

        $this->assertStringContainsString('hasActiveFilters:', $matches);
        $this->assertStringContainsString(':disabled="!hasActiveFilters"', $matches);
        $this->assertStringContainsString("!hasActiveFilters ? 'opacity-50 cursor-not-allowed' : ''", $matches);
        $this->assertStringContainsString(
            'px-4 py-2 bg-ink-800 border border-line text-mist-300 rounded hover:bg-ink-700 transition',
            $matches
        );

        $expectedRecords = [
            'record_longest_match' => ['Самый длинный матч', 'Longest match'],
            'record_shortest_match' => ['Самый короткий матч', 'Shortest match'],
            'record_max_kills' => ['Максимум убийств', 'Most kills'],
            'record_max_deaths' => ['Максимум смертей', 'Most deaths'],
            'record_max_assists' => ['Максимум помощи', 'Most assists'],
            'record_max_gpm' => ['Максимальный GPM', 'Highest GPM'],
            'record_max_xpm' => ['Максимальный XPM', 'Highest XPM'],
            'record_max_hero_damage' => ['Максимальный урон по героям', 'Most hero damage'],
            'record_max_tower_damage' => ['Максимальный урон по строениям', 'Most tower damage'],
            'record_max_hero_healing' => ['Максимальное лечение', 'Most hero healing'],
            'record_max_last_hits' => ['Максимум добиваний', 'Most last hits'],
            'record_max_denies' => ['Максимум добиваний союзников', 'Most denies'],
            'record_max_gold' => ['Максимум золота', 'Most gold'],
            'record_max_net_worth' => ['Максимальная ценность', 'Highest net worth'],
            'record_longest_win_streak' => ['Самая длинная серия побед', 'Longest win streak'],
        ];

        foreach ($expectedRecords as $key => [$russian, $english]) {
            $this->assertSame($russian, $ru[$key], $key);
            $this->assertSame($english, $en[$key], $key);
            $this->assertStringContainsString("__('ui.{$key}')", $service, $key);
        }
    }

    public function test_profile_storage_is_persistent_and_incremental(): void
    {
        $service = (string) file_get_contents(app_path('Services/OpenDota/OpenDotaService.php'));
        $entrypoint = (string) file_get_contents(base_path('docker/entrypoint.sh'));
        $readme = (string) file_get_contents(base_path('README.md'));

        $this->assertStringNotContainsString('PlayerProfile::truncate', $service);
        $this->assertStringNotContainsString('Cache::flush()', $service);
        $this->assertStringNotContainsString('PlayerProfile::whereKey($accountId)->delete()', $service);
        $this->assertStringContainsString('latestMatchId(', $service);
        $this->assertStringContainsString('mergeHistory(', $service);
        $this->assertStringContainsString('less_than_match_id', $service);

        $this->assertStringContainsString('php artisan migrate --force', $entrypoint);
        $this->assertStringNotContainsString('migrate:fresh', $entrypoint);
        $this->assertStringNotContainsString('db:wipe', $entrypoint);

        $this->assertStringContainsString('chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache', $entrypoint);
        $this->assertStringContainsString('chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache', $entrypoint);
        $this->assertStringNotContainsString('chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true', $entrypoint);
        $this->assertStringNotContainsString('chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true', $entrypoint);


        $this->assertStringContainsString('хранятся в PostgreSQL бессрочно', $readme);
        $this->assertStringContainsString('инкрементально', $readme);
        $this->assertStringNotContainsString('Сброс кэша профиля', $readme);
    }
}
