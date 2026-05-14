<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseTenRuntimeProcessTest extends TestCase
{
    public function test_runtime_worker_lab_memory_growth_command_runs(): void
    {
        $this->artisan('runtime:worker-lab', [
            'action' => 'memory-growth',
            '--iterations' => 2,
            '--chunk-kb' => 1,
        ])->assertExitCode(0);
    }

    public function test_runtime_worker_lab_lifecycle_command_runs(): void
    {
        $this->artisan('runtime:worker-lab', [
            'action' => 'lifecycle',
        ])->assertExitCode(0);
    }
}
