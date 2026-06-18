<?php

namespace App\Modules\Import\Jobs;

use App\Modules\Import\Services\ImportTaskProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportTaskJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $taskId) {}

    public function handle(ImportTaskProcessor $processor): void
    {
        $processor->process($this->taskId);
    }
}
