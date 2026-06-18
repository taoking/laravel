<?php

namespace App\Modules\Dataset\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetFilter extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'field_id',
        'operator',
        'value_type',
        'value_json',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'is_required' => 'boolean',
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
     * @return BelongsTo<DatasetField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(DatasetField::class, 'field_id');
    }
}
