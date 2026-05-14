<?php

namespace App\Domains\Messaging;

class KafkaRecord
{
    public function __construct(
        public readonly KafkaMessage $message,
        public readonly string $topic,
        public readonly int $partition,
        public readonly int $offset,
        public readonly string $key,
    ) {}
}
