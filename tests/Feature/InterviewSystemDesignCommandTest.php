<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewSystemDesignCommandTest extends TestCase
{
    public function test_system_design_command_lists_cases(): void
    {
        $this->artisan('interview:system-design')
            ->assertExitCode(0);
    }

    public function test_system_design_command_outputs_single_case_json(): void
    {
        $this->artisan('interview:system-design payment-callback --json')
            ->assertExitCode(0);
    }

    public function test_system_design_command_rejects_unknown_case(): void
    {
        $this->artisan('interview:system-design unknown-case')
            ->assertExitCode(1);
    }
}
