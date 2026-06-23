<?php

namespace App\Modules\Acceleration\Models;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Models\DataSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccelerationProfile extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'name',
        'engine_type',
        'mode',
        'status',
        'source_connection_id',
        'target_connection_id',
        'target_database',
        'target_table',
        'refresh_type',
        'refresh_interval_minutes',
        'last_refresh_at',
        'last_success_at',
        'last_error_message',
        'row_count',
        'version',
        'config_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_json' => 'array',
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
     * @return BelongsTo<DataSource, $this>
     */
    public function sourceConnection(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_connection_id');
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function targetConnection(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'target_connection_id');
    }

    /**
     * @return HasMany<AccelerationColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(AccelerationColumn::class)->orderByDesc('is_partition_key')->orderByDesc('is_order_key')->orderBy('id');
    }

    /**
     * @return HasMany<AccelerationTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AccelerationTask::class)->latest('id');
    }

    /**
     * @return HasMany<AccelerationAggregateDefinition, $this>
     */
    public function detailAggregateDefinitions(): HasMany
    {
        return $this->hasMany(AccelerationAggregateDefinition::class, 'detail_profile_id')->latest('id');
    }

    /**
     * @return HasOne<AccelerationAggregateDefinition, $this>
     */
    public function aggregateDefinition(): HasOne
    {
        return $this->hasOne(AccelerationAggregateDefinition::class, 'aggregate_profile_id');
    }
}
