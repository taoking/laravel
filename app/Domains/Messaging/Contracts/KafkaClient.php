<?php

namespace App\Domains\Messaging\Contracts;

use App\Domains\Messaging\KafkaMessage;
use App\Domains\Messaging\KafkaRecord;

interface KafkaClient
{
    public function publish(KafkaMessage $message): void;

    /**
     * @return list<KafkaRecord>
     */
    public function consume(string $topic, string $consumerGroup, int $maxMessages, int $timeoutMs): array;

    /**
     * @return list<string>
     */
    public function topics(): array;

    /**
     * @return list<string>
     */
    public function createTopics(): array;

    public function lag(string $topic, string $consumerGroup): int;
}
