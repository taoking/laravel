<?php

namespace App\Console\Commands;

use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\KafkaMessage;
use Illuminate\Console\Command;

class KafkaDeadLetterReplayCommand extends Command
{
    protected $signature = 'kafka:dead-letter:replay
        {topic : Dead letter topic}
        {--group=dead-letter-replay : Replay consumer group}
        {--max=10 : Max dead letter messages to replay}';

    protected $description = 'Replay dead letter Kafka messages back to their original topics.';

    public function handle(KafkaClient $client): int
    {
        $topic = (string) $this->argument('topic');
        $group = (string) $this->option('group');
        $count = 0;

        foreach ($client->consume($topic, $group, max(1, (int) $this->option('max')), 1000) as $record) {
            $payload = $record->message->payload;
            $failedMessage = $payload['failed_message'] ?? null;
            $originalTopic = $payload['original_topic'] ?? null;

            if (! is_array($failedMessage) || ! is_string($originalTopic) || $originalTopic === '') {
                continue;
            }

            $client->publish(KafkaMessage::fromArray($failedMessage, $originalTopic, (string) ($failedMessage['key'] ?? $record->key)));
            $count++;
        }

        $this->info("Dead letter replayed: {$count}");

        return self::SUCCESS;
    }
}
