<?php

namespace App\Domains\Messaging;

use App\Domains\Messaging\Contracts\KafkaClient;
use App\Domains\Messaging\Contracts\KafkaMessageHandler;
use App\Domains\Messaging\Models\ConsumedMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

class KafkaConsumerService
{
    /**
     * @param  iterable<KafkaMessageHandler>  $handlers
     */
    public function __construct(
        private readonly KafkaClient $client,
        private readonly KafkaMessageFactory $factory,
        private readonly iterable $handlers,
        private readonly int $maxAttempts,
    ) {}

    /**
     * @return array{consumed: int, skipped: int, failed: int, dead_lettered: int}
     */
    public function consume(string $consumerGroup, ?string $topic = null, int $maxMessages = 1, int $timeoutMs = 5000): array
    {
        $summary = ['consumed' => 0, 'skipped' => 0, 'failed' => 0, 'dead_lettered' => 0];

        foreach ($this->topicsForGroup($consumerGroup, $topic) as $topicName) {
            foreach ($this->client->consume($topicName, $consumerGroup, $maxMessages, $timeoutMs) as $record) {
                $result = $this->handleRecord($record, $consumerGroup);
                $summary[$result]++;
            }
        }

        return $summary;
    }

    /**
     * @return 'consumed'|'skipped'|'failed'|'dead_lettered'
     */
    public function handleRecord(KafkaRecord $record, string $consumerGroup): string
    {
        $message = $record->message;

        return DB::transaction(function () use ($record, $consumerGroup, $message): string {
            $consumed = ConsumedMessage::query()
                ->where('consumer_group', $consumerGroup)
                ->where('idempotency_key', $message->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($consumed && $consumed->status === 'completed') {
                return 'skipped';
            }

            if (! $consumed) {
                $consumed = ConsumedMessage::query()->create([
                    'message_id' => $message->messageId,
                    'idempotency_key' => $message->idempotencyKey,
                    'consumer_group' => $consumerGroup,
                    'topic' => $record->topic,
                    'partition' => $record->partition,
                    'offset' => $record->offset,
                    'event_type' => $message->eventType,
                    'status' => 'processing',
                    'attempts' => 0,
                    'payload' => $message->toArray(),
                ]);
            }

            $consumed->forceFill([
                'message_id' => $message->messageId,
                'topic' => $record->topic,
                'partition' => $record->partition,
                'offset' => $record->offset,
                'event_type' => $message->eventType,
                'status' => 'processing',
                'attempts' => $consumed->attempts + 1,
                'payload' => $message->toArray(),
                'error_message' => null,
            ])->save();

            try {
                $this->dispatchHandlers($message, $consumerGroup);
            } catch (Throwable $exception) {
                return $this->recordFailure($consumed, $message, $exception);
            }

            $consumed->forceFill([
                'status' => 'completed',
                'processed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            return 'consumed';
        });
    }

    /**
     * @return list<string>
     */
    public function topicsForGroup(string $consumerGroup, ?string $topic = null): array
    {
        if ($topic) {
            return [$topic];
        }

        $topics = config("kafka.consumer_groups.{$consumerGroup}.topics", []);

        return collect(is_array($topics) ? $topics : [])
            ->map(fn (mixed $topicName): string => (string) $topicName)
            ->filter()
            ->values()
            ->all();
    }

    private function dispatchHandlers(KafkaMessage $message, string $consumerGroup): void
    {
        $matched = false;

        foreach ($this->handlers as $handler) {
            if (! $handler->supports($message, $consumerGroup)) {
                continue;
            }

            $matched = true;
            $handler->handle($message, $consumerGroup);
        }

        if (! $matched) {
            throw new \RuntimeException("No Kafka handler matched [{$message->eventType}] for group [{$consumerGroup}].");
        }
    }

    /**
     * @return 'failed'|'dead_lettered'
     */
    private function recordFailure(ConsumedMessage $consumed, KafkaMessage $message, Throwable $exception): string
    {
        $status = $consumed->attempts >= $this->maxAttempts ? 'dead_lettered' : 'failed';

        $consumed->forceFill([
            'status' => $status,
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ])->save();

        if ($status === 'dead_lettered') {
            $this->client->publish(KafkaMessage::make(
                eventType: $message->eventType,
                topic: $this->factory->deadLetterTopic($message->eventType),
                key: $message->key,
                idempotencyKey: $message->idempotencyKey.':dlq',
                payload: [
                    'original_topic' => $message->topic,
                    'error_message' => $exception->getMessage(),
                    'failed_message' => $message->toArray(),
                ],
                meta: [
                    'producer' => 'laravel.kafka-consumer',
                    'dead_lettered_at' => now()->toIso8601String(),
                ],
                traceId: $message->traceId,
            ));
        }

        return $status === 'dead_lettered' ? 'dead_lettered' : 'failed';
    }
}
