<?php

use App\Models\LiveRoom;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live-room.{roomId}', function ($user, int $roomId) {
    if (! LiveRoom::query()->whereKey($roomId)->exists()) {
        return false;
    }

    return [
        'id' => (string) $user->getAuthIdentifier(),
        'name' => $user->name ?: 'Viewer',
    ];
});
