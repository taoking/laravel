<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\SystemDesignInterviewPlaybook;
use Illuminate\Console\Command;

class InterviewSystemDesignCommand extends Command
{
    protected $signature = 'interview:system-design
        {case? : Optional case key, for example payment-callback}
        {--json : Output the payload as JSON}';

    protected $description = '输出支付、库存、订单、通知、RBAC、审计日志等系统设计面试 playbook。';

    public function handle(SystemDesignInterviewPlaybook $playbook): int
    {
        $case = $this->argument('case');

        if (is_string($case) && $case !== '') {
            $payload = $playbook->case($case);

            if ($payload === null) {
                $this->error("Unknown system design case: {$case}");
                $this->line('Available cases: '.implode(', ', $playbook->keys()));

                return self::FAILURE;
            }

            return $this->renderCase($case, $payload);
        }

        $payload = $playbook->all();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('System design interview playbook');
        $this->table(['Case', 'Title', 'Core tables'], array_map(
            fn (string $key, array $item): array => [
                $key,
                $item['title'],
                implode(' | ', $item['core_tables']),
            ],
            array_keys($payload),
            $payload,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderCase(string $key, array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([$key => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info($payload['title']);
        $this->line('Case: '.$key);

        foreach (['requirements', 'core_tables', 'flow', 'risks'] as $section) {
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
