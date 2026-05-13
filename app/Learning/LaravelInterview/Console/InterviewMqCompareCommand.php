<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\MessageQueueComparison;
use Illuminate\Console\Command;

class InterviewMqCompareCommand extends Command
{
    protected $signature = 'interview:mq-compare
        {--json : Output the complete payload as JSON}';

    protected $description = '对比 RabbitMQ、Kafka、RocketMQ 的模型、场景、取舍和资深面试点。';

    public function handle(MessageQueueComparison $comparison): int
    {
        $payload = $comparison->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('MQ comparison interview examples');
        $this->table(['MQ', 'Model', 'Best for'], array_map(
            fn (string $key, array $item): array => [
                $key,
                $item['model'],
                implode(', ', $item['best_for']),
            ],
            array_keys($payload['matrix']),
            $payload['matrix'],
        ));

        $this->newLine();
        $this->line('Selection rules');
        foreach ($payload['selection_rules'] as $rule) {
            $this->line('- '.$rule);
        }

        $this->newLine();
        $this->line('Interview points');
        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
