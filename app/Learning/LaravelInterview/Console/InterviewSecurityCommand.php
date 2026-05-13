<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\SecurityInterviewChecklist;
use Illuminate\Console\Command;

class InterviewSecurityCommand extends Command
{
    protected $signature = 'interview:security
        {topic? : Optional topic key, for example sql-injection}
        {--json : Output the payload as JSON}';

    protected $description = '输出 Laravel/PHP 安全面试清单和生产防护要点。';

    public function handle(SecurityInterviewChecklist $checklist): int
    {
        $topic = $this->argument('topic');

        if (is_string($topic) && $topic !== '') {
            $payload = $checklist->topic($topic);

            if ($payload === null) {
                $this->error("Unknown security topic: {$topic}");
                $this->line('Available topics: '.implode(', ', $checklist->keys()));

                return self::FAILURE;
            }

            return $this->renderTopic($topic, $payload);
        }

        $payload = $checklist->all();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Laravel/PHP security interview checklist');
        $this->table(['Topic', 'Title', 'Risk'], array_map(
            fn (string $key, array $item): array => [
                $key,
                $item['title'],
                $item['risk'],
            ],
            array_keys($payload),
            $payload,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderTopic(string $key, array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([$key => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info($payload['title']);
        $this->line('Topic: '.$key);
        $this->line('Risk: '.$payload['risk']);

        foreach (['laravel_defenses', 'pitfalls'] as $section) {
            $this->newLine();
            $this->line(str_replace('_', ' ', strtoupper($section)));

            foreach ($payload[$section] as $item) {
                $this->line('- '.$item);
            }
        }

        $this->newLine();
        $this->line('Interview answer: '.$payload['interview_answer']);

        return self::SUCCESS;
    }
}
