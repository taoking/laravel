<?php

namespace App\Modules\Semantic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricUsage extends Model
{
    use HasFactory;

    public const TYPES = ['chart', 'dashboard', 'dataset', 'aggregate_definition'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'metric_id',
        'metric_version',
        'used_by_type',
        'used_by_id',
        'usage_context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
