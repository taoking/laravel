<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewDockerCommandTest extends TestCase
{
    public function test_docker_command_runs(): void
    {
        $this->artisan('interview:docker')
            ->assertExitCode(0);
    }

    public function test_docker_command_can_output_json(): void
    {
        $this->artisan('interview:docker --json')
            ->assertExitCode(0);
    }
}
