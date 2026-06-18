<?php

namespace App\Modules\DataSource\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSourceField extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'data_source_id',
        'table_id',
        'table_name',
        'field_name',
        'field_comment',
        'data_type',
        'normalized_type',
        'is_nullable',
        'is_primary_key',
        'default_value',
        'ordinal_position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_nullable' => 'boolean',
            'is_primary_key' => 'boolean',
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
     * @return BelongsTo<DataSourceTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DataSourceTable::class, 'table_id');
    }
}
