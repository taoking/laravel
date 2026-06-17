<?php

namespace Tests\Feature;

use App\Jobs\GenerateAdaptiveHlsForVideo;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessUploadedVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_extracts_metadata_and_creates_thumbnail(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('videos/originals/source.mp4', 'fake-video');

        $video = Video::query()->create([
            'title' => 'Queued Video',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'status' => 'pending',
        ]);

        config([
            'video.ffprobe_path' => $this->fakeFfprobeCommand(),
            'video.ffmpeg_path' => $this->fakeFfmpegCommand(),
            'video.thumbnail_time' => '00:00:01',
        ]);
        Queue::fake([GenerateAdaptiveHlsForVideo::class]);

        (new ProcessUploadedVideo($video->id))->handle();

        $video->refresh();

        $this->assertSame('ready', $video->status);
        $this->assertSame(13, $video->duration_seconds);
        $this->assertSame(1920, $video->width);
        $this->assertSame(1080, $video->height);
        $this->assertSame('h264', $video->video_codec);
        $this->assertSame('aac', $video->audio_codec);
        $this->assertSame('videos/thumbnails/'.$video->id.'/thumbnail.jpg', $video->thumbnail_path);
        $this->assertSame($video->thumbnail_path, $video->cover_path);
        $this->assertSame('h264', data_get($video->metadata, 'streams.0.codec_name'));
        $this->assertNull($video->error_message);

        Storage::disk('public')->assertExists($video->thumbnail_path);
        Queue::assertPushed(GenerateAdaptiveHlsForVideo::class, fn (GenerateAdaptiveHlsForVideo $job): bool => $job->videoId === $video->id);
    }

    public function test_job_marks_video_as_failed_when_processing_fails(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('videos/originals/broken.mp4', 'fake-video');

        $video = Video::query()->create([
            'title' => 'Broken Video',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/broken.mp4',
            'original_filename' => 'broken.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'status' => 'pending',
        ]);

        config([
            'video.ffprobe_path' => $this->failingCommand('probe exploded'),
            'video.ffmpeg_path' => $this->fakeFfmpegCommand(),
        ]);
        Queue::fake([GenerateAdaptiveHlsForVideo::class]);

        try {
            (new ProcessUploadedVideo($video->id))->handle();
            $this->fail('Expected video processing to throw an exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ffprobe failed', $exception->getMessage());
        }

        $video->refresh();

        $this->assertSame('failed', $video->status);
        $this->assertStringContainsString('probe exploded', $video->error_message);
        Queue::assertNotPushed(GenerateAdaptiveHlsForVideo::class);
    }

    private function fakeFfprobeCommand(): string
    {
        return $this->temporaryCommand(<<<'PHP'
#!/usr/bin/env php
<?php

echo json_encode([
    'streams' => [
        [
            'codec_type' => 'video',
            'codec_name' => 'h264',
            'width' => 1920,
            'height' => 1080,
            'duration' => '12.7',
        ],
        [
            'codec_type' => 'audio',
            'codec_name' => 'aac',
        ],
    ],
    'format' => [
        'duration' => '12.7',
    ],
]);
PHP);
    }

    private function fakeFfmpegCommand(): string
    {
        return $this->temporaryCommand(<<<'PHP'
#!/usr/bin/env php
<?php

$output = $argv[count($argv) - 1];

if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0777, true);
}

file_put_contents($output, 'thumbnail');
PHP);
    }

    private function failingCommand(string $message): string
    {
        return $this->temporaryCommand(<<<PHP
#!/usr/bin/env php
<?php

fwrite(STDERR, '$message');
exit(1);
PHP);
    }

    private function temporaryCommand(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'video-command-');

        file_put_contents($path, $contents);
        chmod($path, 0755);

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
