<?php

namespace App\Modules\DataSource\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSourceTable extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'data_source_id',
        'table_name',
        'table_comment',
        'table_type',
        'row_count_estimate',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
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
     * @return HasMany<DataSourceField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DataSourceField::class, 'table_id');
    }
}
