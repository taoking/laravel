<?php

namespace App\Modules\Metadata\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetadataAsset extends Model
{
    use HasFactory;

    public const TYPES = [
        'data_source',
        'physical_table',
        'physical_column',
        'dataset',
        'dataset_field',
        'dimension',
        'metric',
        'chart',
        'dashboard',
        'acceleration_profile',
        'aggregate_definition',
        'materialized_view',
    ];

    public const STATUSES = ['active', 'deprecated', 'archived', 'disabled', 'draft', 'building', 'failed'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'asset_type',
        'asset_id',
        'name',
        'code',
        'description',
        'data_source_id',
        'dataset_id',
        'status',
        'owner_id',
        'tags_json',
        'properties_json',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_id' => 'integer',
            'data_source_id' => 'integer',
            'dataset_id' => 'integer',
            'owner_id' => 'integer',
            'tags_json' => 'array',
            'properties_json' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MetadataAssetTag, $this>
     */
    public function tagLinks(): HasMany
    {
        return $this->hasMany(MetadataAssetTag::class, 'asset_id', 'asset_id')
            ->where('asset_type', $this->asset_type);
    }

    /**
     * @return HasMany<MetadataUsageStat, $this>
     */
    public function usageStats(): HasMany
    {
        return $this->hasMany(MetadataUsageStat::class, 'asset_id', 'asset_id')
            ->where('asset_type', $this->asset_type);
    }
}
