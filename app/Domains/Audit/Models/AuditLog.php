<?php

namespace App\Domains\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'resource_type',
    'resource_id',
    'ip_address',
    'trace_id',
    'user_agent',
    'metadata',
])]
class AuditLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
