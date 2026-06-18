<?php

namespace App\Modules\Dataset\Models;

use App\Modules\DataSource\Models\DataSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetTable extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'data_source_id',
        'table_name',
        'alias',
        'join_type',
        'join_condition',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
