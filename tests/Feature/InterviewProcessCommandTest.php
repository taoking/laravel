<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewProcessCommandTest extends TestCase
{
    public function test_process_command_runs_when_pcntl_is_available(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is not available in this environment.');
        }

        $this->artisan('interview:process')
            ->assertExitCode(0);
    }
}
