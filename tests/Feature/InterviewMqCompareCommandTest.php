<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewMqCompareCommandTest extends TestCase
{
    public function test_mq_compare_command_runs(): void
    {
        $this->artisan('interview:mq-compare')
            ->assertExitCode(0);
    }

    public function test_mq_compare_command_can_output_json(): void
    {
        $this->artisan('interview:mq-compare --json')
            ->assertExitCode(0);
    }
}
