<?php

namespace App\Modules\Semantic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricDependency extends Model
{
    use HasFactory;

    public const TYPES = ['metric', 'field', 'dimension'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'metric_id',
        'depends_on_metric_id',
        'depends_on_field_name',
        'dependency_type',
    ];

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function dependsOnMetric(): BelongsTo
    {
        return $this->belongsTo(Metric::class, 'depends_on_metric_id');
    }
}
