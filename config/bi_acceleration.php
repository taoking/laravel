<?php

return [
    'enabled' => env('BI_ACCELERATION_ENABLED', true),

    'default_engine' => env('BI_ACCELERATION_ENGINE', 'clickhouse'),

    'query' => [
        'fallback_on_error' => true,
        'max_rows_for_original_query' => 100000,
        'slow_query_threshold_ms' => 3000,
        'compare_original_query' => false,
    ],

    'sync' => [
        'chunk_size' => 5000,
        'max_retry' => 3,
        'timeout_seconds' => 600,
    ],

    'aggregate' => [
        'enabled' => env('BI_AGGREGATE_ACCELERATION_ENABLED', true),
        'max_dimensions' => env('BI_AGGREGATE_MAX_DIMENSIONS', 5),
        'max_metrics' => env('BI_AGGREGATE_MAX_METRICS', 10),
        'fallback_to_detail' => true,
        'fallback_to_source' => true,
    ],

    'recommendation' => [
        'enabled' => env('BI_ACCELERATION_RECOMMENDATION_ENABLED', true),
        'analysis_days' => env('BI_ACCELERATION_ANALYSIS_DAYS', 7),
        'slow_query_threshold_ms' => env('BI_ACCELERATION_RECOMMEND_SLOW_MS', 3000),
        'min_query_count' => env('BI_ACCELERATION_RECOMMEND_MIN_QUERY_COUNT', 5),
        'high_frequency_threshold' => env('BI_ACCELERATION_RECOMMEND_HIGH_FREQUENCY', 30),
        'max_dimensions' => env('BI_ACCELERATION_RECOMMEND_MAX_DIMENSIONS', 5),
        'max_metrics' => env('BI_ACCELERATION_RECOMMEND_MAX_METRICS', 10),
    ],

    'refresh' => [
        'enabled' => env('BI_ACCELERATION_REFRESH_ENABLED', true),
        'scheduler_interval_minutes' => env('BI_ACCELERATION_REFRESH_INTERVAL_MINUTES', 5),
    ],

    'clickhouse' => [
        'connection' => env('BI_CLICKHOUSE_CONNECTION', 'clickhouse'),
        'scheme' => env('BI_CLICKHOUSE_SCHEME', 'http'),
        'host' => env('BI_CLICKHOUSE_HOST', 'clickhouse'),
        'port' => env('BI_CLICKHOUSE_PORT', 8123),
        'database' => env('BI_CLICKHOUSE_DATABASE', 'bi_accelerator'),
        'username' => env('BI_CLICKHOUSE_USERNAME', 'default'),
        'password' => env('BI_CLICKHOUSE_PASSWORD', ''),
        'timeout' => env('BI_CLICKHOUSE_TIMEOUT', 30),
    ],
];
