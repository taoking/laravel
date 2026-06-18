<?php

namespace App\Modules\Dataset\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetField extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'table_name',
        'field_name',
        'field_alias',
        'display_name',
        'source_type',
        'normalized_type',
        'semantic_type',
        'is_dimension',
        'is_metric',
        'is_visible',
        'is_filterable',
        'default_aggregate',
        'expression',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dimension' => 'boolean',
            'is_metric' => 'boolean',
            'is_visible' => 'boolean',
            'is_filterable' => 'boolean',
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
     * @return HasMany<DatasetFilter, $this>
     */
    public function filters(): HasMany
    {
        return $this->hasMany(DatasetFilter::class, 'field_id');
    }
}
