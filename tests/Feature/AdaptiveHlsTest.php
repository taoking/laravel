<?php

namespace Tests\Feature;

use App\Jobs\GenerateAdaptiveHlsForVideo;
use App\Models\Video;
use App\Models\VideoRendition;
use App\Support\Video\HlsMasterPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdaptiveHlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_has_many_renditions(): void
    {
        $video = $this->video();

        $video->renditions()->create([
            'label' => '360p',
            'height' => 360,
            'status' => VideoRendition::STATUS_PENDING,
        ]);

        $this->assertCount(1, $video->renditions);
        $this->assertSame('360p', $video->renditions->first()->label);
    }

    public function test_master_playlist_builder_lists_ready_renditions(): void
    {
        $video = $this->video();

        $renditions = collect([
            $video->renditions()->create([
                'label' => '360p',
                'width' => 640,
                'height' => 360,
                'video_bitrate' => '800k',
                'audio_bitrate' => '96k',
                'status' => VideoRendition::STATUS_READY,
            ]),
            $video->renditions()->create([
                'label' => '720p',
                'width' => 1280,
                'height' => 720,
                'video_bitrate' => '2800k',
                'audio_bitrate' => '128k',
                'status' => VideoRendition::STATUS_READY,
            ]),
        ]);

        $playlist = (new HlsMasterPlaylist)->make($renditions);

        $this->assertStringContainsString('BANDWIDTH=896000,RESOLUTION=640x360', $playlist);
        $this->assertStringContainsString('360p/index.m3u8', $playlist);
        $this->assertStringContainsString('BANDWIDTH=2928000,RESOLUTION=1280x720', $playlist);
        $this->assertStringContainsString('720p/index.m3u8', $playlist);
    }

    public function test_adaptive_hls_job_generates_renditions_and_master_playlist(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/originals/source.mp4', 'fake-video');

        $video = $this->video(['height' => 1080]);

        config([
            'video.ffmpeg_path' => $this->fakeHlsFfmpegCommand(),
            'video.hls_disk' => 'public',
            'video.hls_allow_upscale' => false,
        ]);

        (new GenerateAdaptiveHlsForVideo($video->id))->handle(new HlsMasterPlaylist);

        $video->refresh();

        $this->assertSame('ready', $video->hls_status);
        $this->assertSame($video->hlsMasterPlaylistPath(), $video->hls_playlist_path);
        $this->assertTrue($video->adaptiveHlsReady());

        Storage::disk('public')->assertExists($video->hlsMasterPlaylistPath());
        Storage::disk('public')->assertExists($video->hlsRenditionPlaylistPath('360p'));
        Storage::disk('public')->assertExists($video->hlsRenditionDirectory('360p').'/segment_000.ts');
        Storage::disk('public')->assertExists($video->hlsRenditionPlaylistPath('720p'));

        $this->assertDatabaseHas('video_renditions', [
            'video_id' => $video->id,
            'label' => '360p',
            'status' => VideoRendition::STATUS_READY,
        ]);
        $this->assertDatabaseHas('video_renditions', [
            'video_id' => $video->id,
            'label' => '720p',
            'status' => VideoRendition::STATUS_READY,
        ]);
    }

    public function test_adaptive_hls_job_skips_renditions_above_source_height(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/originals/source.mp4', 'fake-video');

        $video = $this->video(['height' => 480]);

        config([
            'video.ffmpeg_path' => $this->fakeHlsFfmpegCommand(),
            'video.hls_disk' => 'public',
            'video.hls_allow_upscale' => false,
        ]);

        (new GenerateAdaptiveHlsForVideo($video->id))->handle(new HlsMasterPlaylist);

        $master = Storage::disk('public')->get($video->hlsMasterPlaylistPath());

        $this->assertStringContainsString('360p/index.m3u8', $master);
        $this->assertStringNotContainsString('720p/index.m3u8', $master);
        $this->assertDatabaseHas('video_renditions', [
            'video_id' => $video->id,
            'label' => '360p',
            'status' => VideoRendition::STATUS_READY,
        ]);
        $this->assertDatabaseMissing('video_renditions', [
            'video_id' => $video->id,
            'label' => '720p',
        ]);
    }

    public function test_adaptive_hls_routes_rewrite_master_and_rendition_playlists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/originals/source.mp4', 'fake-video');

        $video = $this->video(['height' => 1080]);

        config([
            'video.ffmpeg_path' => $this->fakeHlsFfmpegCommand(),
            'video.hls_disk' => 'public',
        ]);

        (new GenerateAdaptiveHlsForVideo($video->id))->handle(new HlsMasterPlaylist);

        $this->get(route('videos.hls.playlist', $video))
            ->assertOk()
            ->assertSee(route('videos.hls.rendition.playlist', ['video' => $video, 'label' => '360p']), false);

        $this->get(route('videos.hls.rendition.playlist', ['video' => $video, 'label' => '360p']))
            ->assertOk()
            ->assertSee(route('videos.hls.rendition.segment', [
                'video' => $video,
                'label' => '360p',
                'filename' => 'segment_000.ts',
            ]), false);

        $this->get(route('videos.hls.rendition.segment', [
            'video' => $video,
            'label' => '360p',
            'filename' => 'segment_000.ts',
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp2t');

        $this->get('/videos/'.$video->id.'/hls/renditions/360p/segment/..%2Fsecret.ts')
            ->assertNotFound();
    }

    private function video(array $attributes = []): Video
    {
        return Video::query()->create(array_merge([
            'title' => 'Adaptive Source',
            'original_disk' => 'public',
            'original_path' => 'videos/originals/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'status' => 'ready',
            'hls_status' => 'pending',
            'width' => 1920,
            'height' => 1080,
        ], $attributes));
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

    private function temporaryCommand(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'adaptive-hls-command-');

        file_put_contents($path, $contents);
        chmod($path, 0755);

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
