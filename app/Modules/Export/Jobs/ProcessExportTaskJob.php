<?php

namespace App\Modules\Export\Jobs;

use App\Modules\Export\Services\ExportTaskProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessExportTaskJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $taskId) {}

    public function handle(ExportTaskProcessor $processor): void
    {
        $processor->process($this->taskId);
    }
}
