<?php

namespace App\Domains\Messaging;

use App\Domains\Messaging\Contracts\KafkaClient;

class KafkaProducer
{
    public function __construct(
        private readonly KafkaClient $client,
        private readonly KafkaMessageFactory $factory,
    ) {}

    public function publish(KafkaMessage $message): KafkaMessage
    {
        $this->client->publish($message);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishEvent(
        string $eventType,
        array $payload,
        ?string $key = null,
        ?string $idempotencyKey = null,
        ?string $traceId = null,
    ): KafkaMessage {
        return $this->publish($this->factory->make(
            eventType: $eventType,
            payload: $payload,
            key: $key,
            idempotencyKey: $idempotencyKey,
            traceId: $traceId,
        ));
    }
}
