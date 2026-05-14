<?php

namespace App\Domains\Messaging;

use InvalidArgumentException;

class KafkaMessageFactory
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function make(
        string $eventType,
        array $payload,
        ?string $key = null,
        ?string $idempotencyKey = null,
        ?string $traceId = null,
    ): KafkaMessage {
        $definition = $this->definition($eventType);

        return KafkaMessage::make(
            eventType: $eventType,
            topic: (string) $definition['topic'],
            key: $key ?: $this->renderTemplate((string) $definition['key_template'], $payload),
            idempotencyKey: $idempotencyKey ?: $this->renderTemplate((string) $definition['idempotency_template'], $payload),
            payload: $payload,
            meta: [
                'producer' => config('kafka.producer.name', 'laravel.metrics-platform'),
                'environment' => app()->environment(),
            ],
            traceId: $traceId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $eventType): array
    {
        $topics = config('kafka.topics', []);
        $definition = is_array($topics) ? ($topics[$eventType] ?? null) : null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unsupported Kafka event type [{$eventType}].");
        }

        return $definition;
    }

    public function deadLetterTopic(string $eventType): string
    {
        return (string) ($this->definition($eventType)['dead_letter_topic'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderTemplate(string $template, array $payload): string
    {
        return (string) preg_replace_callback('/\{([^}]+)}/', function (array $matches) use ($payload): string {
            $key = (string) $matches[1];
            $value = $payload[$key] ?? 'missing';

            return is_scalar($value) ? (string) $value : 'complex';
        }, $template);
    }
}
