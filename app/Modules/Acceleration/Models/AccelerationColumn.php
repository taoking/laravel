<?php

namespace App\Modules\Acceleration\Models;

use App\Modules\Dataset\Models\DatasetField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationColumn extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'acceleration_profile_id',
        'dataset_field_id',
        'source_field_name',
        'target_field_name',
        'source_type',
        'target_type',
        'is_dimension',
        'is_metric',
        'aggregate_functions_json',
        'is_partition_key',
        'is_order_key',
        'is_nullable',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aggregate_functions_json' => 'array',
            'is_dimension' => 'boolean',
            'is_metric' => 'boolean',
            'is_partition_key' => 'boolean',
            'is_order_key' => 'boolean',
            'is_nullable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AccelerationProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AccelerationProfile::class, 'acceleration_profile_id');
    }

    /**
     * @return BelongsTo<DatasetField, $this>
     */
    public function datasetField(): BelongsTo
    {
        return $this->belongsTo(DatasetField::class);
    }
}
