<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Throwable;

class InterviewRedisCommandTest extends TestCase
{
    public function test_redis_command_runs_when_redis_is_available(): void
    {
        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            $this->markTestSkipped('Redis is not available in this environment.');
        }

        $this->artisan('interview:redis --key=phpunit')
            ->assertExitCode(0);
    }
}
