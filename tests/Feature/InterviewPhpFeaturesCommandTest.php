<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewPhpFeaturesCommandTest extends TestCase
{
    public function test_php_features_command_runs(): void
    {
        $this->artisan('interview:php-features')
            ->assertExitCode(0);
    }

    public function test_php_features_command_can_output_json(): void
    {
        $this->artisan('interview:php-features --json')
            ->assertExitCode(0);
    }
}
