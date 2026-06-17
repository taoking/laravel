<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessUploadedVideo implements ShouldQueue
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
            'status' => 'processing',
            'error_message' => null,
            'failure_reason' => null,
        ])->save();

        try {
            $disk = Storage::disk($video->original_disk);

            if (! $disk->exists($video->original_path)) {
                throw new RuntimeException('Original video file does not exist: '.$video->original_path);
            }

            $originalPath = $disk->path($video->original_path);
            $metadata = $this->probe($originalPath);
            $videoStream = $this->firstStreamOfType($metadata, 'video');
            $audioStream = $this->firstStreamOfType($metadata, 'audio');
            $thumbnailPath = $this->createThumbnail($disk, $video, $originalPath);

            $video->forceFill([
                'duration_seconds' => $this->durationSeconds($metadata, $videoStream),
                'width' => isset($videoStream['width']) ? (int) $videoStream['width'] : null,
                'height' => isset($videoStream['height']) ? (int) $videoStream['height'] : null,
                'video_codec' => $videoStream['codec_name'] ?? null,
                'audio_codec' => $audioStream['codec_name'] ?? null,
                'thumbnail_path' => $thumbnailPath,
                'cover_path' => $thumbnailPath,
                'metadata' => $metadata,
                'status' => 'ready',
                'error_message' => null,
                'failure_reason' => null,
                'processed_at' => now(),
            ])->save();

            GenerateAdaptiveHlsForVideo::dispatch($video->id);
        } catch (Throwable $exception) {
            $message = $this->failureMessage($exception);

            $video->forceFill([
                'status' => 'failed',
                'error_message' => $message,
                'failure_reason' => $message,
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function probe(string $path): array
    {
        $output = $this->runProcess([
            (string) config('video.ffprobe_path', 'ffprobe'),
            '-v',
            'error',
            '-print_format',
            'json',
            '-show_format',
            '-show_streams',
            $path,
        ], 'ffprobe');

        $metadata = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($metadata)) {
            throw new RuntimeException('ffprobe returned an invalid JSON payload.');
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function firstStreamOfType(array $metadata, string $type): array
    {
        foreach (($metadata['streams'] ?? []) as $stream) {
            if (($stream['codec_type'] ?? null) === $type) {
                return is_array($stream) ? $stream : [];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $videoStream
     */
    private function durationSeconds(array $metadata, array $videoStream): ?int
    {
        $duration = $videoStream['duration']
            ?? data_get($metadata, 'format.duration');

        if ($duration === null || ! is_numeric($duration)) {
            return null;
        }

        return (int) round((float) $duration);
    }

    private function createThumbnail(FilesystemAdapter $disk, Video $video, string $originalPath): string
    {
        $thumbnailPath = 'videos/thumbnails/'.$video->id.'/thumbnail.jpg';

        $disk->makeDirectory(dirname($thumbnailPath));

        $this->runProcess([
            (string) config('video.ffmpeg_path', 'ffmpeg'),
            '-y',
            '-ss',
            (string) config('video.thumbnail_time'),
            '-i',
            $originalPath,
            '-frames:v',
            '1',
            '-q:v',
            '2',
            $disk->path($thumbnailPath),
        ], 'ffmpeg');

        return $thumbnailPath;
    }

    /**
     * @param  array<int, string|null>  $command
     */
    private function runProcess(array $command, string $name): string
    {
        $process = new Process($command);
        $process->setTimeout((float) config('video.process_timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException($name.' failed: '.($error ?: 'Unknown error.'));
        }

        return $process->getOutput();
    }

    private function failureMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage(), 4000);
    }
}
