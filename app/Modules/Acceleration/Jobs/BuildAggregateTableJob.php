<?php

namespace App\Modules\Acceleration\Jobs;

use App\Modules\Acceleration\Services\AggregateBuildService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildAggregateTableJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $taskId) {}

    public function handle(AggregateBuildService $buildService): void
    {
        $buildService->process($this->taskId);
    }
}
