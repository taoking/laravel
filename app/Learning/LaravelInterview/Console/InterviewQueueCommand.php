<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\LaravelQueueInterviewExamples;
use Illuminate\Console\Command;

class InterviewQueueCommand extends Command
{
    protected $signature = 'interview:queue
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 Laravel Queue、Job 重试、唯一任务、失败处理、Schedule 和 Supervisor 面试点。';

    public function handle(LaravelQueueInterviewExamples $examples): int
    {
        $payload = $examples->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Laravel queue interview examples');

        $this->table(['Runtime', 'Value'], [
            ['Default Connection', $payload['runtime']['default_connection']],
            ['Failed Driver', $payload['runtime']['failed_driver']],
            ['Jobs Table', var_export($payload['runtime']['tables']['jobs'], true)],
            ['Failed Jobs Table', var_export($payload['runtime']['tables']['failed_jobs'], true)],
        ]);

        $this->table(['Job Property', 'Value'], [
            ['Class', $payload['job']['class']],
            ['Queue', $payload['job']['queue']],
            ['ShouldQueue', $payload['job']['implements_should_queue'] ? 'yes' : 'no'],
            ['ShouldBeUnique', $payload['job']['implements_should_be_unique'] ? 'yes' : 'no'],
            ['Tries', (string) $payload['job']['tries']],
            ['Timeout', (string) $payload['job']['timeout']],
            ['Unique ID', $payload['job']['unique_id']],
            ['Backoff', implode(', ', $payload['job']['backoff_seconds'])],
            ['Middleware', implode(', ', $payload['job']['middleware'])],
        ]);

        foreach ($payload['worker_commands'] as $label => $command) {
            $this->line($label.': '.$command);
        }

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
