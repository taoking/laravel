<?php

namespace App\Console\Commands;

use App\Domains\Messaging\KafkaProducer;
use Illuminate\Console\Command;
use InvalidArgumentException;

class KafkaProduceCommand extends Command
{
    protected $signature = 'kafka:produce
        {event : Event type, for example metric.import.completed}
        {--payload= : JSON payload}
        {--key= : Override message key}
        {--idempotency-key= : Override idempotency key}
        {--trace-id= : Override trace id}';

    protected $description = 'Produce a Kafka learning event.';

    public function handle(KafkaProducer $producer): int
    {
        $eventType = (string) $this->argument('event');
        $payload = $this->payload($eventType);

        $message = $producer->publishEvent(
            eventType: $eventType,
            payload: $payload,
            key: $this->stringOption('key'),
            idempotencyKey: $this->stringOption('idempotency-key'),
            traceId: $this->stringOption('trace-id'),
        );

        $this->info('Kafka message produced.');
        $this->line($message->toJson());

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $eventType): array
    {
        $json = $this->stringOption('payload');
        if ($json !== null) {
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('The --payload option must be valid JSON object.');
            }

            return $decoded;
        }

        return match ($eventType) {
            'metric.import.completed' => [
                'import_task_id' => 1001,
                'status' => 'completed',
                'total_rows' => 10,
                'success_rows' => 10,
                'failed_rows' => 0,
                'operator_id' => 1,
            ],
            'metric.data.changed' => [
                'metric_id' => 1,
                'change_id' => 'demo-change-1',
                'change_type' => 'updated',
                'changed_fields' => ['name', 'unit'],
                'operator_id' => 1,
            ],
            'audit.event.created' => [
                'action' => 'kafka.demo',
                'resource_type' => 'kafka_message',
                'resource_id' => 1,
                'operator_id' => 1,
            ],
            default => throw new InvalidArgumentException("Unsupported Kafka event type [{$eventType}]."),
        };
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
