<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedVideo;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_list_page_can_be_opened(): void
    {
        $response = $this->get(route('videos.index'));

        $response
            ->assertOk()
            ->assertSee('Videos');
    }

    public function test_video_upload_page_can_be_opened(): void
    {
        $response = $this->get(route('videos.create'));

        $response
            ->assertOk()
            ->assertSee('Upload video');
    }

    public function test_video_can_be_uploaded_and_saved_to_public_disk(): void
    {
        Storage::fake('public');
        Queue::fake();

        $file = UploadedFile::fake()->create('lesson.mp4', 1024, 'video/mp4');

        $response = $this->post(route('videos.store'), [
            'title' => 'Laravel Video Lesson',
            'description' => 'A short learning video.',
            'video' => $file,
        ]);

        $video = Video::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('videos.show', $video));

        $this->assertDatabaseHas('videos', [
            'title' => 'Laravel Video Lesson',
            'description' => 'A short learning video.',
            'original_disk' => 'public',
            'original_filename' => 'lesson.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024 * 1024,
            'status' => 'pending',
            'hls_status' => 'pending',
        ]);

        Storage::disk('public')->assertExists($video->original_path);
        Queue::assertPushed(ProcessUploadedVideo::class, fn (ProcessUploadedVideo $job): bool => $job->videoId === $video->id);
    }

    public function test_video_detail_page_can_be_opened(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()
            ->create('detail.mp4', 512, 'video/mp4')
            ->store('videos/originals', 'public');

        $video = Video::query()->create([
            'title' => 'Detail Video',
            'description' => 'Video detail page.',
            'original_disk' => 'public',
            'original_path' => $path,
            'original_filename' => 'detail.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 512 * 1024,
            'status' => 'pending',
        ]);

        $response = $this->get(route('videos.show', $video));

        $response
            ->assertOk()
            ->assertSee('Detail Video')
            ->assertSee('video/mp4')
            ->assertSee('pending');
    }
}
