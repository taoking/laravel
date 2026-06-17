<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_room_id',
        'user_id',
        'nickname',
        'content',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'live_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->nickname ?: $this->user?->name ?: 'Guest';
    }
}
