<?php

namespace App\Jobs;

use App\Services\OpenDota\OpenDotaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Warms the Redis cache for a Dota 2 player in the background so the redirect
 * target renders without waiting for OpenDota.
 */
class WarmDotaProfile implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $accountId)
    {
    }

    public function uniqueId(): string
    {
        return 'dota-profile-'.$this->accountId;
    }

    public function handle(OpenDotaService $openDota): void
    {
        $openDota->player($this->accountId);
    }
}
