<?php

namespace App\Modules\Acceleration\Models;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationRecommendation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'chart_id',
        'dashboard_id',
        'recommendation_type',
        'status',
        'priority',
        'reason',
        'dimensions_json',
        'metrics_json',
        'filters_json',
        'time_field',
        'time_grain',
        'estimated_query_count',
        'estimated_avg_duration_ms',
        'estimated_max_duration_ms',
        'estimated_total_duration_ms',
        'estimated_benefit_score',
        'source_query_log_ids_json',
        'created_profile_id',
        'created_aggregate_definition_id',
        'accepted_by',
        'accepted_at',
        'rejected_by',
        'rejected_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimensions_json' => 'array',
            'metrics_json' => 'array',
            'filters_json' => 'array',
            'source_query_log_ids_json' => 'array',
            'estimated_query_count' => 'integer',
            'estimated_avg_duration_ms' => 'integer',
            'estimated_max_duration_ms' => 'integer',
            'estimated_total_duration_ms' => 'integer',
            'estimated_benefit_score' => 'integer',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expires_at' => 'datetime',
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

    /**
     * @return BelongsTo<AccelerationProfile, $this>
     */
    public function createdProfile(): BelongsTo
    {
        return $this->belongsTo(AccelerationProfile::class, 'created_profile_id');
    }

    /**
     * @return BelongsTo<AccelerationAggregateDefinition, $this>
     */
    public function createdAggregateDefinition(): BelongsTo
    {
        return $this->belongsTo(AccelerationAggregateDefinition::class, 'created_aggregate_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
