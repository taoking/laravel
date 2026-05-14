<?php

namespace App\Domains\Messaging\Handlers;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Messaging\Contracts\KafkaMessageHandler;
use App\Domains\Messaging\KafkaMessage;

class AuditLogEventHandler implements KafkaMessageHandler
{
    #[\Override]
    public function supports(KafkaMessage $message, string $consumerGroup): bool
    {
        return $consumerGroup === 'audit-log-consumer'
            && in_array($message->eventType, ['metric.import.completed', 'audit.event.created'], true);
    }

    #[\Override]
    public function handle(KafkaMessage $message, string $consumerGroup): void
    {
        unset($consumerGroup);

        $payload = $message->payload;

        AuditLog::query()->create([
            'user_id' => $this->integerOrNull($payload['operator_id'] ?? $payload['user_id'] ?? null),
            'action' => (string) ($payload['action'] ?? $message->eventType),
            'resource_type' => (string) ($payload['resource_type'] ?? $this->resourceType($message)),
            'resource_id' => $this->integerOrNull($payload['resource_id'] ?? $payload['import_task_id'] ?? null),
            'ip_address' => null,
            'trace_id' => $message->traceId,
            'user_agent' => 'kafka-consumer',
            'metadata' => [
                'message_id' => $message->messageId,
                'idempotency_key' => $message->idempotencyKey,
                'event_type' => $message->eventType,
                'payload' => $payload,
            ],
        ]);
    }

    private function resourceType(KafkaMessage $message): string
    {
        return match ($message->eventType) {
            'metric.import.completed' => 'import_task',
            default => 'kafka_event',
        };
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
