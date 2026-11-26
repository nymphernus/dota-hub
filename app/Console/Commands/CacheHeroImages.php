<?php

namespace App\Console\Commands;

use App\Services\OpenDota\OpenDotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CacheHeroImages extends Command
{
    protected $signature = 'dotahub:cache-images {--force : Re-download existing files}';

    protected $description = 'Download hero/item images to public/images for local caching';

    public function handle(OpenDotaService $openDota): int
    {
        $heroes = $openDota->heroStats();
        $heroDir = public_path('images/heroes');
        $itemDir = public_path('images/items');
        if (! is_dir($heroDir)) mkdir($heroDir, 0777, true);
        if (! is_dir($itemDir)) mkdir($itemDir, 0777, true);

        $downloaded = 0;
        $minimapDir = public_path('images/heroes/minimap');
        $iconDir = public_path('images/heroes/icons');
        if (! is_dir($minimapDir)) mkdir($minimapDir, 0777, true);
        if (! is_dir($iconDir)) mkdir($iconDir, 0777, true);
        foreach ($heroes as $hero) {
            $short = '';
            $cdn = (string) ($hero['image_cdn'] ?? $hero['image'] ?? '');
            if (preg_match('#/heroes/([a-z0-9_]+)\.png#i', $cdn, $m)) {
                $short = $m[1];
            }
            if ($short === '' || $cdn === '') continue;
            $path = $heroDir.DIRECTORY_SEPARATOR.$short.'.png';
            if ($this->option('force') || ! is_file($path)) {
                try {
                    $res = Http::timeout(20)->get($cdn);
                    if ($res->ok()) { file_put_contents($path, $res->body()); $downloaded++; }
                } catch (\Throwable $e) { $this->warn("skip hero {$short}: {$e->getMessage()}"); }
            }
            // minimap icon
            $mmPath = $minimapDir.DIRECTORY_SEPARATOR.$short.'.png';
            if ($this->option('force') || ! is_file($mmPath)) {
                $mmUrl = "https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/icons/{$short}.png";
                try {
                    $res = Http::timeout(20)->get($mmUrl);
                    if ($res->ok()) { file_put_contents($mmPath, $res->body()); $downloaded++; }
                } catch (\Throwable $e) { $this->warn("skip minimap {$short}: {$e->getMessage()}"); }
            }
            // Tooltip icons use the same artwork as the cached minimap images.
            $iconPath = $iconDir.DIRECTORY_SEPARATOR.$short.'.png';
            if (is_file($mmPath) && ($this->option('force') || ! is_file($iconPath))) {
                copy($mmPath, $iconPath);
                $downloaded++;
            }
        }

        $items = $openDota->itemStats();
        foreach ($items as $key => $item) {
            $img = (string) ($item['image'] ?? '');
            $name = is_string($key) ? $key : '';
            if ($name === '' || $img === '') continue;
            $path = $itemDir.DIRECTORY_SEPARATOR.$name.'.png';
            if (! $this->option('force') && is_file($path)) continue;
            try {
                $res = Http::timeout(20)->get($img);
                if ($res->ok()) {
                    file_put_contents($path, $res->body());
                    $downloaded++;
                }
            } catch (\Throwable $e) {
                $this->warn("skip item {$name}: {$e->getMessage()}");
            }
        }

        $this->info("Downloaded {$downloaded} images.");
        return self::SUCCESS;
    }
}
