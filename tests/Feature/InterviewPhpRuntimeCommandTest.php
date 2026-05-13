<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewPhpRuntimeCommandTest extends TestCase
{
    public function test_php_runtime_command_runs(): void
    {
        $this->artisan('interview:php-runtime')
            ->assertExitCode(0);
    }

    public function test_php_runtime_command_can_output_json(): void
    {
        $this->artisan('interview:php-runtime --json')
            ->assertExitCode(0);
    }
}
