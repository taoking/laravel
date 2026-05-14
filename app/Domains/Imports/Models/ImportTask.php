<?php

namespace App\Domains\Imports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'idempotency_key',
    'original_name',
    'disk',
    'path',
    'status',
    'total_rows',
    'success_rows',
    'failed_rows',
    'attempts',
    'error_message',
    'failure_type',
    'last_failed_at',
    'started_at',
    'finished_at',
    'compensated_at',
    'compensation_reason',
])]
class ImportTask extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_failed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'compensated_at' => 'datetime',
        ];
    }
}
