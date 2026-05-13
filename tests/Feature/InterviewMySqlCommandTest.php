<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

class InterviewMySqlCommandTest extends TestCase
{
    public function test_mysql_command_runs_when_database_is_available(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $this->markTestSkipped('MySQL is not available in this environment.');
        }

        if (! Schema::hasTable('interview_mysql_demo_orders')) {
            $this->markTestSkipped('Run php artisan migrate before this test.');
        }

        $this->artisan('interview:mysql')
            ->assertExitCode(0);
    }
}
