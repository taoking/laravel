<?php

namespace App\Domains\Messaging\Handlers;

use App\Domains\Messaging\Contracts\KafkaMessageHandler;
use App\Domains\Messaging\KafkaMessage;
use Illuminate\Support\Facades\Cache;

class MetricCacheRefreshHandler implements KafkaMessageHandler
{
    #[\Override]
    public function supports(KafkaMessage $message, string $consumerGroup): bool
    {
        return $consumerGroup === 'cache-refresh-consumer'
            && in_array($message->eventType, ['metric.import.completed', 'metric.data.changed'], true);
    }

    #[\Override]
    public function handle(KafkaMessage $message, string $consumerGroup): void
    {
        unset($consumerGroup);

        $metricId = $message->payload['metric_id'] ?? null;

        if (is_numeric($metricId)) {
            Cache::forget('metric:'.((int) $metricId).':detail');
            Cache::forget('metrics:detail:'.((int) $metricId));
        }

        Cache::forget('metrics:index');
        Cache::increment('kafka:cache_refreshes');
    }
}
