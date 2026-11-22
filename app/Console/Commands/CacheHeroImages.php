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
        foreach ($heroes as $hero) {
            $short = '';
            $cdn = (string) ($hero['image_cdn'] ?? $hero['image'] ?? '');
            if (preg_match('#/heroes/([a-z0-9_]+)\.png#i', $cdn, $m)) {
                $short = $m[1];
            }
            if ($short === '' || $cdn === '') continue;
            $path = $heroDir.DIRECTORY_SEPARATOR.$short.'.png';
            if (! $this->option('force') && is_file($path)) continue;
            try {
                $res = Http::timeout(20)->get($cdn);
                if ($res->ok()) {
                    file_put_contents($path, $res->body());
                    $downloaded++;
                }
            } catch (\Throwable $e) {
                $this->warn("skip hero {$short}: {$e->getMessage()}");
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
