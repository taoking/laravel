<?php

namespace App\Modules\Metadata\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetadataLineageRelation extends Model
{
    use HasFactory;

    public const RELATION_TYPES = [
        'depends_on',
        'contains',
        'uses',
        'maps_to',
        'built_from',
        'derived_from',
        'generated_by',
    ];

    public const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_asset_type',
        'source_asset_id',
        'target_asset_type',
        'target_asset_id',
        'relation_type',
        'relation_detail_json',
        'confidence',
        'created_by_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_asset_id' => 'integer',
            'target_asset_id' => 'integer',
            'relation_detail_json' => 'array',
            'created_by_system' => 'boolean',
        ];
    }
}
