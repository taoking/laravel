<?php

namespace App\Modules\Import\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedTable extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'import_task_id',
        'table_name',
        'display_name',
        'schema_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ImportTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ImportTask::class, 'import_task_id');
    }
}
