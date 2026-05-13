<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InterviewExamplesRoutingTest extends TestCase
{
    public function test_interview_examples_are_not_routed_by_default(): void
    {
        $this->assertFalse(Route::has('interview.index'));

        $this->get('/interview-examples')
            ->assertNotFound();
    }

    public function test_interview_digest_command_is_available_for_learning(): void
    {
        $this->artisan('interview:digest')
            ->assertExitCode(0);
    }
}
