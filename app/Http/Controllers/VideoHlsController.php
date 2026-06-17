<?php

namespace App\Http\Controllers;

use App\Models\Video;
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

        $disk = Storage::disk('local');
        $contents = $disk->get($video->hlsPlaylistPath());

        return response($this->rewritePlaylist($contents, $video), 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function segment(Video $video, string $filename): BinaryFileResponse
    {
        $this->authorizeHlsAccess($video);

        abort_unless($video->hlsReady(), 404);
        abort_unless($this->isSafeHlsFilename($filename), 404);

        $disk = Storage::disk('local');
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

    private function rewritePlaylist(string $contents, Video $video): string
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

            $filename = basename($trimmed);

            if (! $this->isSafeHlsFilename($filename)) {
                return $line;
            }

            return route('videos.hls.segment', [
                'video' => $video,
                'filename' => $filename,
            ]);
        }, $lines);

        return implode("\n", $rewritten);
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
