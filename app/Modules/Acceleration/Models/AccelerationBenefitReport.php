<?php

namespace App\Modules\Acceleration\Models;

use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationBenefitReport extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'chart_id',
        'dashboard_id',
        'acceleration_profile_id',
        'aggregate_definition_id',
        'report_date',
        'query_count',
        'raw_query_count',
        'detail_hit_count',
        'aggregate_hit_count',
        'cache_hit_count',
        'fallback_count',
        'avg_raw_duration_ms',
        'avg_detail_duration_ms',
        'avg_aggregate_duration_ms',
        'avg_cache_duration_ms',
        'estimated_saved_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'query_count' => 'integer',
            'raw_query_count' => 'integer',
            'detail_hit_count' => 'integer',
            'aggregate_hit_count' => 'integer',
            'cache_hit_count' => 'integer',
            'fallback_count' => 'integer',
            'avg_raw_duration_ms' => 'integer',
            'avg_detail_duration_ms' => 'integer',
            'avg_aggregate_duration_ms' => 'integer',
            'avg_cache_duration_ms' => 'integer',
            'estimated_saved_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return BelongsTo<Chart, $this>
     */
    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
