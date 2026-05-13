<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\PhpProcessInterviewExamples;
use Illuminate\Console\Command;

class InterviewProcessCommand extends Command
{
    protected $signature = 'interview:process
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 PHP 进程模型、pcntl fork、PHP-FPM worker 和常驻进程面试点。';

    public function handle(PhpProcessInterviewExamples $examples): int
    {
        $payload = $examples->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('PHP process interview examples');

        $this->table(['Topic', 'Result'], [
            ['PHP Version', $payload['runtime']['php_version']],
            ['SAPI', $payload['runtime']['sapi']],
            ['Parent PID', (string) $payload['runtime']['parent_pid']],
            ['pcntl', $payload['runtime']['pcntl_available'] ? 'available' : 'missing'],
            ['Children', json_encode($payload['process_isolation']['children'], JSON_UNESCAPED_UNICODE)],
            ['Parent Counter', (string) $payload['process_isolation']['parent_counter_after_children']],
            ['Threading', $payload['threading_note']],
            ['Octane', $payload['octane_note']],
        ]);

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
