<?php

namespace App\Support\Live;

use Illuminate\Support\Str;

class MediaMtxUrls
{
    public function streamKey(?string $streamKey = null): string
    {
        $streamKey = trim((string) $streamKey);

        if ($streamKey !== '') {
            return $this->cleanStreamKey($streamKey);
        }

        return $this->cleanStreamKey((string) config('live.default_stream_key', 'test'));
    }

    public function pushUrl(string $streamKey): string
    {
        return rtrim((string) config('live.mediamtx_rtmp_base_url'), '/').'/'.$this->cleanStreamKey($streamKey);
    }

    public function playbackUrl(string $streamKey): string
    {
        return rtrim((string) config('live.mediamtx_hls_base_url'), '/').'/'.$this->cleanStreamKey($streamKey).'/index.m3u8';
    }

    private function cleanStreamKey(string $streamKey): string
    {
        $streamKey = Str::of($streamKey)
            ->trim()
            ->replaceMatches('/[^A-Za-z0-9._-]/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();

        return $streamKey !== '' ? $streamKey : 'test';
    }
}
