<?php

namespace App\Domains\Imports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'idempotency_key',
    'type',
    'status',
    'filters',
    'disk',
    'path',
    'total_rows',
    'processed_rows',
    'file_size',
    'attempts',
    'error_message',
    'failure_type',
    'last_failed_at',
    'started_at',
    'finished_at',
    'downloaded_at',
])]
class ExportTask extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'file_size' => 'integer',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }
}
