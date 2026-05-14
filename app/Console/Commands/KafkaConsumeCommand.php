<?php

namespace App\Console\Commands;

use App\Domains\Messaging\KafkaConsumerService;
use Illuminate\Console\Command;

class KafkaConsumeCommand extends Command
{
    protected $signature = 'kafka:consume
        {consumer_group : Consumer group, for example audit-log-consumer}
        {--topic= : Override topic}
        {--max=1 : Max messages per topic}
        {--timeout-ms=5000 : Consumer timeout in milliseconds}';

    protected $description = 'Consume Kafka messages and run matching Laravel handlers.';

    public function handle(KafkaConsumerService $consumer): int
    {
        $summary = $consumer->consume(
            consumerGroup: (string) $this->argument('consumer_group'),
            topic: $this->stringOption('topic'),
            maxMessages: max(1, (int) $this->option('max')),
            timeoutMs: max(100, (int) $this->option('timeout-ms')),
        );

        $this->info('Kafka consume finished.');
        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
