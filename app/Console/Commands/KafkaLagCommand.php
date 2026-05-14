<?php

namespace App\Console\Commands;

use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\KafkaConsumerService;
use Illuminate\Console\Command;

class KafkaLagCommand extends Command
{
    protected $signature = 'kafka:lag
        {consumer_group : Consumer group}
        {--topic= : Override topic}';

    protected $description = 'Show Kafka consumer lag for configured topics.';

    public function handle(KafkaClient $client, KafkaConsumerService $consumer): int
    {
        $consumerGroup = (string) $this->argument('consumer_group');
        $rows = [];

        foreach ($consumer->topicsForGroup($consumerGroup, $this->stringOption('topic')) as $topic) {
            $rows[] = [$consumerGroup, $topic, $client->lag($topic, $consumerGroup)];
        }

        $this->table(['Consumer group', 'Topic', 'Lag'], $rows);

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
