<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewTroubleshootCommandTest extends TestCase
{
    public function test_troubleshoot_command_lists_scenarios(): void
    {
        $this->artisan('interview:troubleshoot')
            ->assertExitCode(0);
    }

    public function test_troubleshoot_command_outputs_single_scenario_json(): void
    {
        $this->artisan('interview:troubleshoot mysql-slow-query --json')
            ->assertExitCode(0);
    }

    public function test_troubleshoot_command_rejects_unknown_scenario(): void
    {
        $this->artisan('interview:troubleshoot unknown-scenario')
            ->assertExitCode(1);
    }
}
