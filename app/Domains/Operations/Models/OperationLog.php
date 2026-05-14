<?php

namespace App\Domains\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'method',
    'path',
    'status_code',
    'duration_ms',
    'ip_address',
    'trace_id',
    'user_agent',
])]
class OperationLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
