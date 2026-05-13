<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewQueueCommandTest extends TestCase
{
    public function test_queue_command_runs(): void
    {
        $this->artisan('interview:queue')
            ->assertExitCode(0);
    }

    public function test_queue_command_can_output_json(): void
    {
        $this->artisan('interview:queue --json')
            ->assertExitCode(0);
    }
}
