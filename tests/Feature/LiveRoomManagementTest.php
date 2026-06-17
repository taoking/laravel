<?php

namespace Tests\Feature;

use App\Models\LiveRoom;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveRoomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_room_create_page_shows_video_options(): void
    {
        $video = $this->video([
            'title' => 'Processed Lesson',
            'hls_status' => 'ready',
        ]);

        $response = $this->get(route('rooms.create'));

        $response
            ->assertOk()
            ->assertSee('Create Live Room')
            ->assertSee($video->title);
    }

    public function test_live_room_can_be_created_with_bound_video(): void
    {
        $video = $this->video();

        $response = $this->post(route('rooms.store'), [
            'title' => 'Pseudo Live Class',
            'description' => 'Playing a processed HLS video as a live room.',
            'status' => LiveRoom::STATUS_LIVE,
            'video_id' => $video->id,
            'stream_url' => null,
        ]);

        $room = LiveRoom::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rooms.show', $room));

        $this->assertDatabaseHas('live_rooms', [
            'title' => 'Pseudo Live Class',
            'status' => LiveRoom::STATUS_LIVE,
            'video_id' => $video->id,
        ]);
        $this->assertNotNull($room->started_at);
        $this->assertNull($room->ended_at);
    }

    public function test_live_room_can_be_updated_and_ended(): void
    {
        $room = LiveRoom::query()->create([
            'title' => 'Old Room',
            'status' => LiveRoom::STATUS_SCHEDULED,
        ]);

        $video = $this->video(['title' => 'Replay Source']);

        $response = $this->patch(route('rooms.update', $room), [
            'title' => 'Updated Room',
            'description' => 'Updated details.',
            'status' => LiveRoom::STATUS_ENDED,
            'video_id' => $video->id,
            'stream_url' => 'https://example.test/live/index.m3u8',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rooms.show', $room));

        $room->refresh();

        $this->assertSame('Updated Room', $room->title);
        $this->assertSame(LiveRoom::STATUS_ENDED, $room->status);
        $this->assertSame($video->id, $room->video_id);
        $this->assertSame('https://example.test/live/index.m3u8', $room->stream_url);
        $this->assertNotNull($room->started_at);
        $this->assertNotNull($room->ended_at);
    }

    public function test_live_room_status_can_be_updated_quickly(): void
    {
        $room = LiveRoom::query()->create([
            'title' => 'Manual Status Room',
            'status' => LiveRoom::STATUS_SCHEDULED,
        ]);

        $this->patch(route('rooms.status.update', $room), [
            'status' => LiveRoom::STATUS_LIVE,
        ])->assertRedirect(route('rooms.show', $room));

        $room->refresh();

        $this->assertSame(LiveRoom::STATUS_LIVE, $room->status);
        $this->assertNotNull($room->started_at);
        $this->assertNull($room->ended_at);

        $this->patch(route('rooms.status.update', $room), [
            'status' => LiveRoom::STATUS_ENDED,
        ])->assertRedirect(route('rooms.show', $room));

        $room->refresh();

        $this->assertSame(LiveRoom::STATUS_ENDED, $room->status);
        $this->assertNotNull($room->ended_at);
    }

    public function test_room_detail_prefers_bound_video_hls_for_pseudo_live(): void
    {
        Storage::fake('local');

        $video = $this->video([
            'status' => 'ready',
            'hls_status' => 'ready',
        ]);

        $video->forceFill([
            'hls_path' => $video->hlsDirectory(),
            'hls_playlist_path' => $video->hlsPlaylistPath(),
        ])->save();

        Storage::disk('local')->put($video->hlsPlaylistPath(), "#EXTM3U\n#EXT-X-ENDLIST\n");

        $room = LiveRoom::query()->create([
            'title' => 'HLS Room',
            'status' => LiveRoom::STATUS_LIVE,
            'video_id' => $video->id,
        ]);

        $response = $this->get(route('rooms.show', $room));

        $response
            ->assertOk()
            ->assertSee("Pseudo live is playing this room's bound HLS video.", false)
            ->assertSee(route('videos.hls.playlist', $video), false);
    }

    private function video(array $attributes = []): Video
    {
        return Video::query()->create(array_merge([
            'title' => 'Source Video',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 100,
            'status' => 'ready',
            'hls_status' => 'pending',
        ], $attributes));
    }
}
