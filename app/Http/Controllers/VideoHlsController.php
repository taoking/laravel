<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoRendition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoHlsController extends Controller
{
    public function playlist(Video $video): Response
    {
        $this->authorizeHlsAccess($video);

        abort_unless($video->hlsReady(), 404);

        $disk = Storage::disk($video->hlsDiskName());
        $contents = $disk->get($video->hls_playlist_path);
        $rewritten = $video->adaptiveHlsReady()
            ? $this->rewriteMasterPlaylist($contents, $video)
            : $this->rewriteMediaPlaylist($contents, $video);

        return response($rewritten, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function renditionPlaylist(Video $video, string $label): Response
    {
        $this->authorizeHlsAccess($video);

        abort_unless($this->isSafeHlsLabel($label), 404);

        $rendition = $this->readyRendition($video, $label);
        $disk = Storage::disk($rendition->disk);

        abort_unless($rendition->playlist_path && $disk->exists($rendition->playlist_path), 404);

        return response($this->rewriteMediaPlaylist($disk->get($rendition->playlist_path), $video, $label), 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function renditionSegment(Video $video, string $label, string $filename): BinaryFileResponse
    {
        $this->authorizeHlsAccess($video);

        abort_unless($this->isSafeHlsLabel($label), 404);
        abort_unless($this->isSafeHlsFilename($filename), 404);

        $rendition = $this->readyRendition($video, $label);
        $disk = Storage::disk($rendition->disk);
        $path = $rendition->directory_path.'/'.$filename;

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $this->contentType($filename),
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function segment(Video $video, string $filename): BinaryFileResponse
    {
        $this->authorizeHlsAccess($video);

        abort_unless($video->hlsReady(), 404);
        abort_unless($this->isSafeHlsFilename($filename), 404);

        $disk = Storage::disk($video->hlsDiskName());
        $path = $video->hlsDirectory().'/'.$filename;

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $this->contentType($filename),
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    private function authorizeHlsAccess(Video $video): void
    {
        abort_if($video->user_id !== null && auth()->id() !== $video->user_id, 403);
    }

    private function rewriteMasterPlaylist(string $contents, Video $video): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        if ($lines === false) {
            return $contents;
        }

        $rewritten = array_map(function (string $line) use ($video): string {
            $trimmed = trim($line);

            if ($trimmed === '' || Str::startsWith($trimmed, '#')) {
                return $line;
            }

            $parts = explode('/', str_replace('\\', '/', $trimmed));

            if (count($parts) !== 2 || basename($parts[1]) !== 'index.m3u8') {
                return $line;
            }

            $label = $parts[0];

            if (! $this->isSafeHlsLabel($label)) {
                return $line;
            }

            return route('videos.hls.rendition.playlist', [
                'video' => $video,
                'label' => $label,
            ]);
        }, $lines);

        return implode("\n", $rewritten);
    }

    private function rewriteMediaPlaylist(string $contents, Video $video, ?string $label = null): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        if ($lines === false) {
            return $contents;
        }

        $rewritten = array_map(function (string $line) use ($video, $label): string {
            $trimmed = trim($line);

            if ($trimmed === '' || Str::startsWith($trimmed, '#')) {
                return $line;
            }

            $filename = basename($trimmed);

            if (! $this->isSafeHlsFilename($filename)) {
                return $line;
            }

            if ($label !== null) {
                return route('videos.hls.rendition.segment', [
                    'video' => $video,
                    'label' => $label,
                    'filename' => $filename,
                ]);
            }

            return route('videos.hls.segment', [
                'video' => $video,
                'filename' => $filename,
            ]);
        }, $lines);

        return implode("\n", $rewritten);
    }

    private function readyRendition(Video $video, string $label): VideoRendition
    {
        return $video->renditions()
            ->where('label', $label)
            ->where('status', VideoRendition::STATUS_READY)
            ->firstOrFail();
    }

    private function isSafeHlsLabel(string $label): bool
    {
        return $label === basename($label)
            && ! str_contains($label, '..')
            && preg_match('/\A[A-Za-z0-9._-]+\z/', $label) === 1;
    }

    private function isSafeHlsFilename(string $filename): bool
    {
        return $filename === basename($filename)
            && ! str_contains($filename, '..')
            && preg_match('/\A[A-Za-z0-9._-]+\z/', $filename) === 1
            && preg_match('/\.(ts|m4s|mp4)\z/i', $filename) === 1;
    }

    private function contentType(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'm4s', 'mp4' => 'video/mp4',
            default => 'video/mp2t',
        };
    }
}
