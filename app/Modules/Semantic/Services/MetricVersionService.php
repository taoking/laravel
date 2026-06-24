<?php

namespace App\Modules\Semantic\Services;

use App\Models\User;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Models\MetricVersion;

class MetricVersionService
{
    /**
     * @var list<string>
     */
    public const VERSIONED_FIELDS = [
        'name',
        'description',
        'metric_type',
        'aggregate_function',
        'source_field',
        'formula',
        'unit',
        'precision',
        'format_type',
        'status',
    ];

    public function snapshot(Metric $metric, ?User $actor, ?string $summary = null): MetricVersion
    {
        return MetricVersion::query()->create([
            'metric_id' => $metric->id,
            'version' => $metric->version,
            'name' => $metric->name,
            'description' => $metric->description,
            'metric_type' => $metric->metric_type,
            'aggregate_function' => $metric->aggregate_function,
            'source_field' => $metric->source_field,
            'formula' => $metric->formula,
            'unit' => $metric->unit,
            'precision' => $metric->precision,
            'format_type' => $metric->format_type,
            'status' => $metric->status,
            'change_summary' => $summary,
            'created_by' => $actor?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasVersionedChanges(Metric $metric, array $payload): bool
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== $metric->{$field}) {
                return true;
            }
        }

        return false;
    }
}
