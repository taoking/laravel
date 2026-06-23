<?php

namespace App\Modules\Acceleration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationAggregateColumn extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aggregate_definition_id',
        'source_field_name',
        'target_field_name',
        'column_role',
        'aggregate_function',
        'source_type',
        'target_type',
    ];

    /**
     * @return BelongsTo<AccelerationAggregateDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(AccelerationAggregateDefinition::class, 'aggregate_definition_id');
    }
}
