<?php

namespace App\Domains\Messaging\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'message_id',
    'idempotency_key',
    'consumer_group',
    'topic',
    'partition',
    'offset',
    'event_type',
    'status',
    'attempts',
    'payload',
    'error_message',
    'processed_at',
    'failed_at',
])]
class ConsumedMessage extends Model
{
    #[\Override]
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
