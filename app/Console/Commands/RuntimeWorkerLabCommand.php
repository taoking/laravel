<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Number;

class RuntimeWorkerLabCommand extends Command
{
    protected $signature = 'runtime:worker-lab
        {action=memory-growth : Lab action: memory-growth or lifecycle}
        {--iterations=5 : Iterations for memory-growth}
        {--chunk-kb=64 : Memory chunk size per iteration}
        {--sleep-ms=0 : Sleep milliseconds between iterations}';

    protected $description = 'Run PHP runtime, worker, and long-running process labs.';

    public function handle(): int
    {
        return match ((string) $this->argument('action')) {
            'memory-growth' => $this->memoryGrowth(),
            'lifecycle' => $this->lifecycle(),
            default => $this->invalidAction(),
        };
    }

    private function memoryGrowth(): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $chunkKb = max(1, (int) $this->option('chunk-kb'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $chunks = [];
        $rows = [];

        for ($index = 1; $index <= $iterations; $index++) {
            $chunks[] = str_repeat('x', $chunkKb * 1024);
            $rows[] = [
                $index,
                $chunkKb,
                Number::format(memory_get_usage(true) / 1024 / 1024, 2),
                Number::format(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->table(['Iteration', 'Added KB', 'Memory MB', 'Peak MB'], $rows);

        return self::SUCCESS;
    }

    private function lifecycle(): int
    {
        $this->table(['Mode', 'Lifecycle', 'Risk'], [
            ['PHP-FPM', 'Request scoped', 'Process exhaustion, 502/504, OPcache refresh'],
            ['CLI command', 'Single command process', 'Long task timeout, memory_limit'],
            ['Queue worker', 'Long-running loop', 'Old code after deploy, memory growth'],
            ['Scheduler', 'Cron or schedule:work', 'Duplicate execution on multiple machines'],
            ['Octane', 'Application kept in memory', 'State pollution, static data, leaks'],
        ]);

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Unsupported action. Use memory-growth or lifecycle.');

        return self::FAILURE;
    }
}
