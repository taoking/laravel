<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoRendition;
use App\Support\Video\HlsMasterPlaylist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateAdaptiveHlsForVideo implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $videoId)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(HlsMasterPlaylist $masterPlaylist): void
    {
        $video = Video::query()->with('renditions')->findOrFail($this->videoId);

        $video->forceFill([
            'hls_status' => 'processing',
            'hls_error_message' => null,
        ])->save();

        $sourceDisk = Storage::disk($video->original_disk);

        if (! $sourceDisk->exists($video->original_path)) {
            $this->failVideo($video, 'Original video file does not exist: '.$video->original_path);
        }

        $hlsDiskName = (string) config('video.hls_disk', 'public');
        $hlsDisk = Storage::disk($hlsDiskName);
        $renditionConfigs = $this->configuredRenditions();
        $eligibleRenditions = $this->eligibleRenditions($video, $renditionConfigs);

        if ($eligibleRenditions === []) {
            $this->failVideo($video, 'No configured HLS renditions are eligible for this source resolution.');
        }

        $hlsDisk->deleteDirectory($video->hlsDirectory());
        $hlsDisk->makeDirectory($video->hlsDirectory());

        $video->renditions()
            ->whereNotIn('label', array_keys($eligibleRenditions))
            ->delete();

        foreach ($eligibleRenditions as $label => $settings) {
            $rendition = $this->prepareRendition($video, $label, $settings, $hlsDiskName);

            try {
                $rendition->forceFill([
                    'status' => VideoRendition::STATUS_PROCESSING,
                    'failure_reason' => null,
                    'processed_at' => null,
                ])->save();

                $this->runFfmpeg(
                    $sourceDisk->path($video->original_path),
                    (int) $settings['height'],
                    (string) $settings['video_bitrate'],
                    (string) $settings['audio_bitrate'],
                    $hlsDisk->path($rendition->directory_path.'/segment_%03d.ts'),
                    $hlsDisk->path($rendition->playlist_path),
                );

                if (! $hlsDisk->exists($rendition->playlist_path)) {
                    throw new RuntimeException('FFmpeg finished but rendition playlist was not created.');
                }

                $rendition->forceFill([
                    'status' => VideoRendition::STATUS_READY,
                    'failure_reason' => null,
                    'processed_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                $rendition->forceFill([
                    'status' => VideoRendition::STATUS_FAILED,
                    'failure_reason' => $this->failureMessage($exception),
                ])->save();
            }
        }

        $readyRenditions = $video->renditions()
            ->where('status', VideoRendition::STATUS_READY)
            ->orderBy('height')
            ->get();

        if ($readyRenditions->isEmpty()) {
            $this->failVideo($video, 'All configured HLS renditions failed.');
        }

        $masterPlaylistPath = $video->hlsMasterPlaylistPath();

        $hlsDisk->put($masterPlaylistPath, $masterPlaylist->make($readyRenditions));

        $video->forceFill([
            'hls_path' => $video->hlsDirectory(),
            'hls_playlist_path' => $masterPlaylistPath,
            'hls_status' => 'ready',
            'hls_error_message' => null,
        ])->save();
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function configuredRenditions(): array
    {
        $renditions = config('video.renditions', []);

        return is_array($renditions) ? $renditions : [];
    }

    /**
     * @param  array<string, array<string, int|string>>  $renditions
     * @return array<string, array<string, int|string>>
     */
    private function eligibleRenditions(Video $video, array $renditions): array
    {
        if ((bool) config('video.hls_allow_upscale', false) || ! $video->height) {
            return $renditions;
        }

        return array_filter(
            $renditions,
            fn (array $settings): bool => ! isset($settings['height']) || (int) $settings['height'] <= (int) $video->height,
        );
    }

    /**
     * @param  array<string, int|string>  $settings
     */
    private function prepareRendition(Video $video, string $label, array $settings, string $disk): VideoRendition
    {
        return $video->renditions()->updateOrCreate(
            ['label' => $label],
            [
                'width' => $settings['width'] ?? null,
                'height' => $settings['height'] ?? null,
                'video_bitrate' => $settings['video_bitrate'] ?? null,
                'audio_bitrate' => $settings['audio_bitrate'] ?? null,
                'disk' => $disk,
                'directory_path' => $video->hlsRenditionDirectory($label),
                'playlist_path' => $video->hlsRenditionPlaylistPath($label),
                'status' => VideoRendition::STATUS_PENDING,
                'failure_reason' => null,
                'processed_at' => null,
            ],
        );
    }

    private function runFfmpeg(
        string $sourcePath,
        int $height,
        string $videoBitrate,
        string $audioBitrate,
        string $segmentPattern,
        string $playlistPath,
    ): void {
        $process = new Process([
            (string) config('video.ffmpeg_path', 'ffmpeg'),
            '-y',
            '-i',
            $sourcePath,
            '-vf',
            'scale=-2:'.$height,
            '-c:v',
            'libx264',
            '-b:v',
            $videoBitrate,
            '-c:a',
            'aac',
            '-b:a',
            $audioBitrate,
            '-preset',
            'veryfast',
            '-f',
            'hls',
            '-hls_time',
            (string) config('video.hls_segment_time', 6),
            '-hls_playlist_type',
            'vod',
            '-hls_segment_filename',
            $segmentPattern,
            $playlistPath,
        ]);

        $process->setTimeout((float) config('video.process_timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException('ffmpeg adaptive HLS generation failed: '.($error ?: 'Unknown error.'));
        }
    }

    /**
     * @throws RuntimeException
     */
    private function failVideo(Video $video, string $message): void
    {
        $message = Str::limit($message, 2000);

        $video->forceFill([
            'hls_status' => 'failed',
            'hls_error_message' => $message,
        ])->save();

        throw new RuntimeException($message);
    }

    private function failureMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage(), 2000);
    }
}
