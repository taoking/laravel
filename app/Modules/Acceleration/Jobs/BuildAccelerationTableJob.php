<?php

namespace App\Modules\Acceleration\Jobs;

use App\Modules\Acceleration\Services\AccelerationSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildAccelerationTableJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $taskId) {}

    public function handle(AccelerationSyncService $syncService): void
    {
        $syncService->process($this->taskId);
    }
}
