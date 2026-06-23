<?php

namespace App\Modules\Dataset\Models;

use App\Models\User;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\DataSource\Models\DataSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dataset extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'data_source_id',
        'dataset_type',
        'main_table',
        'config_json',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /**
     * @return HasMany<DatasetTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(DatasetTable::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<DatasetField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DatasetField::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<DatasetFilter, $this>
     */
    public function filters(): HasMany
    {
        return $this->hasMany(DatasetFilter::class);
    }

    /**
     * @return HasMany<AccelerationProfile, $this>
     */
    public function accelerationProfiles(): HasMany
    {
        return $this->hasMany(AccelerationProfile::class)->latest('id');
    }

    /**
     * @return HasMany<AccelerationAggregateDefinition, $this>
     */
    public function accelerationAggregateDefinitions(): HasMany
    {
        return $this->hasMany(AccelerationAggregateDefinition::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
