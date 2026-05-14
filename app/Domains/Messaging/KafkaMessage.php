<?php

namespace App\Domains\Messaging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KafkaMessage
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $eventType,
        public readonly int $version,
        public readonly string $topic,
        public readonly string $key,
        public readonly string $idempotencyKey,
        public readonly string $traceId,
        public readonly string $createdAt,
        public readonly array $payload,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        string $eventType,
        string $topic,
        string $key,
        string $idempotencyKey,
        array $payload,
        array $meta = [],
        ?string $traceId = null,
        int $version = 1,
    ): self {
        return new self(
            messageId: (string) Str::uuid(),
            eventType: $eventType,
            version: $version,
            topic: $topic,
            key: $key,
            idempotencyKey: $idempotencyKey,
            traceId: $traceId ?: (string) Str::uuid(),
            createdAt: Carbon::now()->toIso8601String(),
            payload: $payload,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $topic = null, ?string $key = null): self
    {
        foreach (['message_id', 'event_type', 'version', 'idempotency_key', 'trace_id', 'created_at', 'payload'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Kafka message missing field [{$field}].");
            }
        }

        return new self(
            messageId: (string) $data['message_id'],
            eventType: (string) $data['event_type'],
            version: (int) $data['version'],
            topic: $topic ?: (string) ($data['topic'] ?? ''),
            key: $key ?: (string) ($data['key'] ?? ''),
            idempotencyKey: (string) $data['idempotency_key'],
            traceId: (string) $data['trace_id'],
            createdAt: (string) $data['created_at'],
            payload: is_array($data['payload']) ? $data['payload'] : [],
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    /**
     * @return array{
     *     message_id: string,
     *     event_type: string,
     *     version: int,
     *     topic: string,
     *     key: string,
     *     idempotency_key: string,
     *     trace_id: string,
     *     created_at: string,
     *     payload: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'event_type' => $this->eventType,
            'version' => $this->version,
            'topic' => $this->topic,
            'key' => $this->key,
            'idempotency_key' => $this->idempotencyKey,
            'trace_id' => $this->traceId,
            'created_at' => $this->createdAt,
            'payload' => $this->payload,
            'meta' => $this->meta,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
