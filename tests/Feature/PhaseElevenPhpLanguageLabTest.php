<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseElevenPhpLanguageLabTest extends TestCase
{
    public function test_php_language_lab_runs_copy_on_write_and_references(): void
    {
        $this->artisan('php:language-lab', [
            'action' => 'cow',
            '--rows' => 10,
        ])
            ->expectsOutputToContain('copy-on-write')
            ->expectsOutputToContain('array-shape')
            ->assertExitCode(0);

        $this->artisan('php:language-lab', [
            'action' => 'references',
        ])
            ->expectsOutputToContain('references')
            ->assertExitCode(0);
    }

    public function test_php_language_lab_runs_generator_and_modern_php_features(): void
    {
        $this->artisan('php:language-lab', [
            'action' => 'generator',
            '--rows' => 10,
        ])
            ->expectsOutputToContain('generator')
            ->assertExitCode(0);

        $this->artisan('php:language-lab', [
            'action' => 'modern',
        ])
            ->expectsOutputToContain('modern-php')
            ->assertExitCode(0);
    }

    public function test_php_language_lab_runs_all_actions_and_rejects_invalid_action(): void
    {
        $this->artisan('php:language-lab', [
            'action' => 'all',
            '--rows' => 5,
        ])
            ->expectsOutputToContain('weak-types')
            ->expectsOutputToContain('objects')
            ->assertExitCode(0);

        $this->artisan('php:language-lab', [
            'action' => 'missing',
        ])->assertExitCode(1);
    }
}
