<?php

namespace App\Domains\Imports\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_task_id',
    'row_number',
    'payload',
    'errors',
])]
class ImportFailure extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(ImportTask::class, 'import_task_id');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
        ];
    }
}
