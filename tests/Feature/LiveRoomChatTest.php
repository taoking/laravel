<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\LiveRoom;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveRoomChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_rooms_page_can_be_opened(): void
    {
        LiveRoom::query()->create([
            'title' => 'Morning Class',
            'status' => 'scheduled',
        ]);

        $response = $this->get(route('rooms.index'));

        $response
            ->assertOk()
            ->assertSee('Live Rooms')
            ->assertSee('Morning Class');
    }

    public function test_live_room_detail_page_shows_existing_messages(): void
    {
        $room = LiveRoom::query()->create([
            'title' => 'Chat Room',
            'status' => 'live',
        ]);

        $room->messages()->create([
            'nickname' => 'Tao',
            'content' => 'Hello from history.',
        ]);

        $response = $this->get(route('rooms.show', $room));

        $response
            ->assertOk()
            ->assertSee('Chat Room')
            ->assertSee('Hello from history.');
    }

    public function test_message_post_is_stored_and_broadcast(): void
    {
        Event::fake([MessageSent::class]);

        $room = LiveRoom::query()->create([
            'title' => 'Broadcast Room',
            'status' => 'live',
        ]);

        $response = $this->postJson(route('rooms.messages.store', $room), [
            'nickname' => 'Student',
            'content' => 'Is this live?',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message.nickname', 'Student')
            ->assertJsonPath('message.content', 'Is this live?');

        $this->assertDatabaseHas('chat_messages', [
            'live_room_id' => $room->id,
            'nickname' => 'Student',
            'content' => 'Is this live?',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($room): bool {
            return $event->message->live_room_id === $room->id
                && $event->message->content === 'Is this live?';
        });
    }

    public function test_message_payload_does_not_expose_sensitive_user_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $room = LiveRoom::query()->create([
            'title' => 'Private Payload Room',
            'status' => 'live',
        ]);

        $message = $room->messages()->create([
            'user_id' => $user->id,
            'nickname' => $user->name,
            'content' => 'Payload check.',
        ]);

        $event = new MessageSent($message);
        $payload = $event->broadcastWith();
        $channels = $event->broadcastOn();

        $this->assertSame('Teacher', $payload['nickname']);
        $this->assertSame('Payload check.', $payload['content']);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertContainsOnlyInstancesOf(Channel::class, $channels);
        $this->assertInstanceOf(PresenceChannel::class, $channels[0]);
        $this->assertSame('presence-live-room.'.$room->id, $channels[0]->name);
        $this->assertSame('live-room.'.$room->id, $channels[1]->name);
    }
}
