<?php

namespace App\Support\Video;

use App\Models\VideoRendition;
use Illuminate\Support\Collection;

class HlsMasterPlaylist
{
    /**
     * @param  iterable<int, VideoRendition>  $renditions
     */
    public function make(iterable $renditions): string
    {
        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
        ];

        foreach (Collection::make($renditions)->sortBy('height') as $rendition) {
            if (! $rendition->isReady() || ! $rendition->width || ! $rendition->height) {
                continue;
            }

            $lines[] = sprintf(
                '#EXT-X-STREAM-INF:BANDWIDTH=%d,RESOLUTION=%dx%d',
                $this->bandwidth($rendition),
                $rendition->width,
                $rendition->height,
            );
            $lines[] = $rendition->label.'/index.m3u8';
        }

        return implode("\n", $lines)."\n";
    }

    private function bandwidth(VideoRendition $rendition): int
    {
        return $this->bitrateToBits($rendition->video_bitrate)
            + $this->bitrateToBits($rendition->audio_bitrate);
    }

    private function bitrateToBits(?string $bitrate): int
    {
        if (! $bitrate) {
            return 0;
        }

        $value = (float) $bitrate;

        if (str_ends_with(strtolower($bitrate), 'm')) {
            return (int) ($value * 1_000_000);
        }

        return (int) ($value * 1_000);
    }
}
