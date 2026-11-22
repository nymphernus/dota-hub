<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class CacheRankImages extends Command
{
    protected $signature = 'dotahub:cache-ranks';
    protected $description = 'Download Dota 2 rank icons locally';

    public function handle(): int
    {
        $dir = public_path('images/dota/ranks');
        File::ensureDirectoryExists($dir);

        $base = 'https://www.opendota.com/assets/images/dota2/rank_icons';
        $ok = 0; $fail = 0;

        // base band icons 0-8 and stars 1-5
        foreach (array_merge(range(0,8), []) as $band) {
            $name = "rank_icon_{$band}.png";
            if ($this->download($base.'/'.$name, $dir.DIRECTORY_SEPARATOR.$name)) $ok++; else $fail++;
        }
        foreach (range(1,5) as $star) {
            $name = "rank_star_{$star}.png";
            if ($this->download($base.'/'.$name, $dir.DIRECTORY_SEPARATOR.$name)) $ok++; else $fail++;
        }

        // also save tier-named copies (e.g. rank_icon_33.png) pointing to band icon
        for ($tier = 10; $tier <= 85; $tier++) {
            $band = intdiv($tier, 10);
            $star = $tier % 10;
            if ($band < 1 || $band > 8) continue;
            if ($band < 8 && ($star < 1 || $star > 7)) continue;
            $src = $dir.DIRECTORY_SEPARATOR."rank_icon_{$band}.png";
            $dst = $dir.DIRECTORY_SEPARATOR."rank_icon_{$tier}.png";
            if (is_file($src)) { File::copy($src, $dst); $ok++; }
        }

        $this->info("Downloaded/copied: $ok, failed: $fail");
        return self::SUCCESS;
    }

    private function download(string $url, string $path): bool
    {
        try {
            $res = Http::timeout(20)->get($url);
            if ($res->successful()) {
                File::put($path, $res->body());
                $this->line("ok ".basename($path));
                return true;
            }
            $this->warn("fail ".basename($path)." ".$res->status());
        } catch (\Throwable $e) {
            $this->warn("fail ".basename($path)." ".$e->getMessage());
        }
        return false;
    }
}
