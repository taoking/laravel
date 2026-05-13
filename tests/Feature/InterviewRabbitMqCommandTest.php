<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class InterviewRabbitMqCommandTest extends TestCase
{
    public function test_rabbitmq_command_runs_when_management_api_is_available(): void
    {
        $host = (string) config('interview_examples.infrastructure.rabbitmq.host');
        $port = (int) config('interview_examples.infrastructure.rabbitmq.management_port');
        $user = (string) config('interview_examples.infrastructure.rabbitmq.user');
        $password = (string) config('interview_examples.infrastructure.rabbitmq.password');

        try {
            $response = Http::withBasicAuth($user, $password)
                ->timeout(1)
                ->get("http://{$host}:{$port}/api/overview");
        } catch (Throwable) {
            $this->markTestSkipped('RabbitMQ Management API is not available in this environment.');
        }

        if (! $response->successful()) {
            $this->markTestSkipped('RabbitMQ Management API is not available in this environment.');
        }

        $this->artisan('interview:rabbitmq --key=phpunit')
            ->assertExitCode(0);
    }
}
