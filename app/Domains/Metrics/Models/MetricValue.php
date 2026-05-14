<?php

namespace App\Domains\Metrics\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'metric_id',
    'region_id',
    'frequency_id',
    'period_date',
    'period_label',
    'value',
    'source',
    'metadata',
])]
class MetricValue extends Model
{
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function frequency(): BelongsTo
    {
        return $this->belongsTo(Frequency::class);
    }

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'value' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
