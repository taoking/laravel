<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
        $this->message->loadMissing('user');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel|PresenceChannel>
     */
    public function broadcastOn(): array
    {
        $roomChannel = 'live-room.'.$this->message->live_room_id;

        return [
            new PresenceChannel($roomChannel),
            new Channel($roomChannel),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'live_room_id' => $this->message->live_room_id,
            'user_id' => $this->message->user_id,
            'nickname' => $this->message->displayName(),
            'content' => $this->message->content,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
