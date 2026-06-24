<?php

namespace App\Modules\Metadata\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpactAnalysisLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'asset_type',
        'asset_id',
        'change_type',
        'impact_result_json',
        'risk_level',
        'analyzed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_id' => 'integer',
            'impact_result_json' => 'array',
            'analyzed_by' => 'integer',
        ];
    }
}
