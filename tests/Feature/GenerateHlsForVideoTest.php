<?php

namespace Tests\Feature;

use App\Jobs\GenerateHlsForVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GenerateHlsForVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_generates_hls_playlist_and_segments(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('public')->put('videos/originals/source.mp4', 'fake-video');

        $video = Video::query()->create([
            'title' => 'HLS Video',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'status' => 'ready',
            'hls_status' => 'pending',
        ]);

        config([
            'video.ffmpeg_path' => $this->fakeHlsFfmpegCommand(),
            'video.hls_time' => 6,
        ]);

        (new GenerateHlsForVideo($video->id))->handle();

        $video->refresh();

        $this->assertSame('ready', $video->hls_status);
        $this->assertSame('videos/hls/'.$video->id, $video->hls_path);
        $this->assertSame('videos/hls/'.$video->id.'/index.m3u8', $video->hls_playlist_path);
        $this->assertNull($video->hls_error_message);

        Storage::disk('local')->assertExists($video->hls_playlist_path);
        Storage::disk('local')->assertExists($video->hls_path.'/segment_000.ts');
    }

    public function test_job_marks_hls_as_failed_when_ffmpeg_fails(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('public')->put('videos/originals/source.mp4', 'fake-video');

        $video = Video::query()->create([
            'title' => 'Broken HLS Video',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'status' => 'ready',
            'hls_status' => 'pending',
        ]);

        config(['video.ffmpeg_path' => $this->failingCommand('hls exploded')]);

        try {
            (new GenerateHlsForVideo($video->id))->handle();
            $this->fail('Expected HLS generation to throw an exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ffmpeg HLS generation failed', $exception->getMessage());
        }

        $video->refresh();

        $this->assertSame('failed', $video->hls_status);
        $this->assertStringContainsString('hls exploded', $video->hls_error_message);
    }

    public function test_playlist_route_rewrites_segments_to_secure_routes(): void
    {
        Storage::fake('local');

        $video = $this->readyHlsVideo();

        Storage::disk('local')->put($video->hls_playlist_path, "#EXTM3U\n#EXTINF:6.0,\nsegment_000.ts\n#EXT-X-ENDLIST\n");
        Storage::disk('local')->put($video->hls_path.'/segment_000.ts', 'segment');

        $response = $this->get(route('videos.hls.playlist', $video));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')
            ->assertSee(route('videos.hls.segment', ['video' => $video, 'filename' => 'segment_000.ts']), false);
    }

    public function test_segment_route_serves_only_safe_files_from_video_directory(): void
    {
        Storage::fake('local');

        $video = $this->readyHlsVideo();

        Storage::disk('local')->put($video->hls_playlist_path, "#EXTM3U\nsegment_000.ts\n");
        Storage::disk('local')->put($video->hls_path.'/segment_000.ts', 'segment');

        $this->get(route('videos.hls.segment', ['video' => $video, 'filename' => 'segment_000.ts']))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp2t');

        $this->get('/videos/'.$video->id.'/hls/segment/..%2Fsecret.ts')
            ->assertNotFound();
    }

    public function test_hls_routes_reject_private_video_for_guest(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $video = $this->readyHlsVideo(['user_id' => $user->id]);

        Storage::disk('local')->put($video->hls_playlist_path, "#EXTM3U\nsegment_000.ts\n");

        $this->get(route('videos.hls.playlist', $video))
            ->assertForbidden();
    }

    private function readyHlsVideo(array $attributes = []): Video
    {
        $video = Video::query()->create(array_merge([
            'title' => 'Ready HLS Video',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'status' => 'ready',
            'hls_status' => 'ready',
        ], $attributes));

        $video->forceFill([
            'hls_path' => $video->hlsDirectory(),
            'hls_playlist_path' => $video->hlsPlaylistPath(),
        ])->save();

        return $video;
    }

    private function fakeHlsFfmpegCommand(): string
    {
        return $this->temporaryCommand(<<<'PHP'
#!/usr/bin/env php
<?php

$playlist = $argv[count($argv) - 1];
$segmentPattern = $argv[array_search('-hls_segment_filename', $argv, true) + 1];
$segment = str_replace('%03d', '000', $segmentPattern);

if (! is_dir(dirname($playlist))) {
    mkdir(dirname($playlist), 0777, true);
}

file_put_contents($segment, 'segment');
file_put_contents($playlist, "#EXTM3U\n#EXT-X-VERSION:3\n#EXTINF:6.0,\nsegment_000.ts\n#EXT-X-ENDLIST\n");
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
        $path = tempnam(sys_get_temp_dir(), 'hls-command-');

        file_put_contents($path, $contents);
        chmod($path, 0755);

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
