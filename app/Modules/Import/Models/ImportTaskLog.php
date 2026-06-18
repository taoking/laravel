<?php

namespace App\Modules\Import\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportTaskLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_task_id',
        'row_number',
        'status',
        'message',
        'raw_data_json',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data_json' => 'array',
            'created_at' => 'datetime',
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
