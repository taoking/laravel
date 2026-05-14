<?php

namespace App\Domains\Messaging\Contracts;

use App\Domains\Messaging\KafkaMessage;

interface KafkaMessageHandler
{
    public function supports(KafkaMessage $message, string $consumerGroup): bool;

    public function handle(KafkaMessage $message, string $consumerGroup): void;
}
