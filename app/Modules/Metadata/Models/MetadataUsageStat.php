<?php

namespace App\Modules\Metadata\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetadataUsageStat extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'asset_type',
        'asset_id',
        'usage_date',
        'query_count',
        'view_count',
        'edit_count',
        'last_used_at',
        'avg_duration_ms',
        'slow_query_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_id' => 'integer',
            'usage_date' => 'date',
            'query_count' => 'integer',
            'view_count' => 'integer',
            'edit_count' => 'integer',
            'last_used_at' => 'datetime',
            'avg_duration_ms' => 'integer',
            'slow_query_count' => 'integer',
        ];
    }
}
