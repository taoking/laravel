<?php

namespace App\Modules\Acceleration\Models;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccelerationAggregateDefinition extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'detail_profile_id',
        'aggregate_profile_id',
        'name',
        'status',
        'target_database',
        'target_table',
        'time_field',
        'time_grain',
        'dimensions_json',
        'metrics_json',
        'filters_json',
        'refresh_type',
        'last_refresh_at',
        'last_success_at',
        'last_error_message',
        'row_count',
        'version',
        'created_by',
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
            'last_refresh_at' => 'datetime',
            'last_success_at' => 'datetime',
            'row_count' => 'integer',
            'version' => 'integer',
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
     * @return BelongsTo<AccelerationProfile, $this>
     */
    public function detailProfile(): BelongsTo
    {
        return $this->belongsTo(AccelerationProfile::class, 'detail_profile_id');
    }

    /**
     * @return BelongsTo<AccelerationProfile, $this>
     */
    public function aggregateProfile(): BelongsTo
    {
        return $this->belongsTo(AccelerationProfile::class, 'aggregate_profile_id');
    }

    /**
     * @return HasMany<AccelerationAggregateColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(AccelerationAggregateColumn::class, 'aggregate_definition_id')
            ->orderByRaw("case column_role when 'time_grain' then 0 when 'dimension' then 1 else 2 end")
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
