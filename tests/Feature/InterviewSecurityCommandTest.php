<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterviewSecurityCommandTest extends TestCase
{
    public function test_security_command_lists_topics(): void
    {
        $this->artisan('interview:security')
            ->assertExitCode(0);
    }

    public function test_security_command_outputs_single_topic_json(): void
    {
        $this->artisan('interview:security sql-injection --json')
            ->assertExitCode(0);
    }

    public function test_security_command_rejects_unknown_topic(): void
    {
        $this->artisan('interview:security unknown-topic')
            ->assertExitCode(1);
    }
}
