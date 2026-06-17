<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'original_disk',
        'original_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'duration_seconds',
        'width',
        'height',
        'video_codec',
        'audio_codec',
        'cover_path',
        'thumbnail_path',
        'hls_path',
        'hls_playlist_path',
        'hls_status',
        'hls_error_message',
        'metadata',
        'status',
        'failure_reason',
        'error_message',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFileSizeForHumansAttribute(): string
    {
        if ($this->size_bytes === null) {
            return 'Unknown';
        }

        $bytes = (float) $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        foreach ($units as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return number_format($bytes, $unit === 'B' ? 0 : 2).' '.$unit;
            }

            $bytes /= 1024;
        }

        return number_format($bytes, 2).' TB';
    }

    public function getPlaybackUrlAttribute(): ?string
    {
        if (! $this->original_path) {
            return null;
        }

        return Storage::disk($this->original_disk)->url($this->original_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->original_disk)->url($this->thumbnail_path);
    }

    public function isPlayable(): bool
    {
        return (bool) $this->original_path
            && Storage::disk($this->original_disk)->exists($this->original_path);
    }

    public function hasThumbnail(): bool
    {
        return (bool) $this->thumbnail_path
            && Storage::disk($this->original_disk)->exists($this->thumbnail_path);
    }

    public function hlsReady(): bool
    {
        return $this->hls_status === 'ready'
            && $this->hls_path === $this->hlsDirectory()
            && $this->hls_playlist_path === $this->hlsPlaylistPath()
            && Storage::disk('local')->exists($this->hlsPlaylistPath());
    }

    public function hlsDirectory(): string
    {
        return 'videos/hls/'.$this->id;
    }

    public function hlsPlaylistPath(): string
    {
        return $this->hlsDirectory().'/index.m3u8';
    }
}
