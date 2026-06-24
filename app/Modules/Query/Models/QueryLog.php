<?php

namespace App\Modules\Query\Models;

use App\Models\User;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'dataset_id',
        'chart_id',
        'dashboard_id',
        'request_source',
        'query_mode',
        'query_hash',
        'logical_plan_hash',
        'permission_hash',
        'permission_applied',
        'sql',
        'bindings_json',
        'elapsed_ms',
        'row_count',
        'cached',
        'is_slow',
        'status',
        'error_message',
        'engine_type',
        'data_source_type',
        'semantic_layer_used',
        'semantic_metrics_json',
        'semantic_dimensions_json',
        'metric_versions_json',
        'acceleration_hit',
        'acceleration_profile_id',
        'acceleration_engine',
        'acceleration_mode',
        'fallback_used',
        'fallback_reason',
        'raw_duration_ms',
        'source_duration_ms',
        'accelerated_duration_ms',
        'total_duration_ms',
        'aggregate_definition_id',
        'aggregate_table',
        'detail_fallback_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bindings_json' => 'array',
            'cached' => 'boolean',
            'is_slow' => 'boolean',
            'permission_applied' => 'boolean',
            'acceleration_hit' => 'boolean',
            'fallback_used' => 'boolean',
            'detail_fallback_used' => 'boolean',
            'semantic_layer_used' => 'boolean',
            'semantic_metrics_json' => 'array',
            'semantic_dimensions_json' => 'array',
            'metric_versions_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * @return BelongsTo<AccelerationProfile, $this>
     */
    public function accelerationProfile(): BelongsTo
    {
        return $this->belongsTo(AccelerationProfile::class);
    }

    /**
     * @return BelongsTo<AccelerationAggregateDefinition, $this>
     */
    public function aggregateDefinition(): BelongsTo
    {
        return $this->belongsTo(AccelerationAggregateDefinition::class, 'aggregate_definition_id');
    }
}
