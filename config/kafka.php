<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Driver
    |--------------------------------------------------------------------------
    |
    | local: file-backed driver for automated tests and offline demos.
    | docker: talks to the Kafka container through docker compose CLI.
    |
    */
    'driver' => env('KAFKA_DRIVER', 'local'),

    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),

    'local_path' => env('KAFKA_LOCAL_PATH', storage_path('app/kafka')),

    'docker' => [
        'compose' => ['docker', 'compose'],
        'service' => env('KAFKA_DOCKER_SERVICE', 'kafka'),
        'timeout' => (int) env('KAFKA_DOCKER_TIMEOUT', 30),
    ],

    'producer' => [
        'name' => env('KAFKA_PRODUCER_NAME', 'laravel.metrics-platform'),
    ],

    'retry' => [
        'max_attempts' => (int) env('KAFKA_MAX_ATTEMPTS', 3),
    ],

    'topics' => [
        'metric.import.completed' => [
            'topic' => 'metrics.import.completed',
            'partitions' => 3,
            'dead_letter_topic' => 'metrics.import.completed.dlq',
            'key_template' => 'import_task:{import_task_id}',
            'idempotency_template' => 'metric-import:{import_task_id}:completed:v1',
        ],
        'metric.data.changed' => [
            'topic' => 'metrics.data.changed',
            'partitions' => 6,
            'dead_letter_topic' => 'metrics.data.changed.dlq',
            'key_template' => 'metric:{metric_id}',
            'idempotency_template' => 'metric-data:{metric_id}:{change_id}:v1',
        ],
        'audit.event.created' => [
            'topic' => 'audit.events',
            'partitions' => 3,
            'dead_letter_topic' => 'audit.events.dlq',
            'key_template' => 'audit:{resource_type}:{resource_id}',
            'idempotency_template' => 'audit:{resource_type}:{resource_id}:{action}:v1',
        ],
    ],

    'consumer_groups' => [
        'audit-log-consumer' => [
            'topics' => ['metrics.import.completed', 'audit.events'],
        ],
        'cache-refresh-consumer' => [
            'topics' => ['metrics.import.completed', 'metrics.data.changed'],
        ],
        'metric-summary-consumer' => [
            'topics' => ['metrics.import.completed', 'metrics.data.changed'],
        ],
    ],
];
