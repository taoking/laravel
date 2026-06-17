<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateHlsForVideo implements ShouldQueue
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
    public function handle(): void
    {
        $video = Video::query()->findOrFail($this->videoId);

        $video->forceFill([
            'hls_status' => 'processing',
            'hls_error_message' => null,
        ])->save();

        try {
            $sourceDisk = Storage::disk($video->original_disk);

            if (! $sourceDisk->exists($video->original_path)) {
                throw new RuntimeException('Original video file does not exist: '.$video->original_path);
            }

            $hlsDisk = Storage::disk('local');
            $hlsDirectory = $video->hlsDirectory();
            $playlistPath = $video->hlsPlaylistPath();

            $hlsDisk->deleteDirectory($hlsDirectory);
            $hlsDisk->makeDirectory($hlsDirectory);

            $this->runFfmpeg(
                $sourceDisk->path($video->original_path),
                $hlsDisk->path($hlsDirectory.'/segment_%03d.ts'),
                $hlsDisk->path($playlistPath),
            );

            if (! $hlsDisk->exists($playlistPath)) {
                throw new RuntimeException('FFmpeg finished but index.m3u8 was not created.');
            }

            $video->forceFill([
                'hls_path' => $hlsDirectory,
                'hls_playlist_path' => $playlistPath,
                'hls_status' => 'ready',
                'hls_error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $video->forceFill([
                'hls_status' => 'failed',
                'hls_error_message' => $this->failureMessage($exception),
            ])->save();

            throw $exception;
        }
    }

    private function runFfmpeg(string $sourcePath, string $segmentPattern, string $playlistPath): void
    {
        $process = new Process([
            (string) config('video.ffmpeg_path', 'ffmpeg'),
            '-y',
            '-i',
            $sourcePath,
            '-codec:v',
            'libx264',
            '-codec:a',
            'aac',
            '-preset',
            'veryfast',
            '-f',
            'hls',
            '-hls_time',
            (string) config('video.hls_time', 6),
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

            throw new RuntimeException('ffmpeg HLS generation failed: '.($error ?: 'Unknown error.'));
        }
    }

    private function failureMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage(), 4000);
    }
}
