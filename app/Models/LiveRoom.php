<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveRoom extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const PLAYBACK_VIDEO = 'video';

    public const PLAYBACK_HLS_URL = 'hls_url';

    public const PLAYBACK_MEDIAMTX = 'mediamtx';

    protected $fillable = [
        'title',
        'description',
        'status',
        'playback_type',
        'video_id',
        'stream_url',
        'stream_key',
        'push_url',
        'playback_url',
        'playback_protocol',
        'media_server',
        'owner_id',
        'started_at',
        'ended_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Get all supported room statuses.
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_LIVE,
            self::STATUS_ENDED,
        ];
    }

    /**
     * Get all supported playback source types.
     *
     * @return array<int, string>
     */
    public static function playbackTypes(): array
    {
        return [
            self::PLAYBACK_VIDEO,
            self::PLAYBACK_HLS_URL,
            self::PLAYBACK_MEDIAMTX,
        ];
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function usesBoundVideo(): bool
    {
        return ($this->playback_type ?: self::PLAYBACK_VIDEO) === self::PLAYBACK_VIDEO;
    }

    public function usesExternalHls(): bool
    {
        return $this->playback_type === self::PLAYBACK_HLS_URL;
    }

    public function usesMediaMtx(): bool
    {
        return $this->playback_type === self::PLAYBACK_MEDIAMTX;
    }

    public function effectivePlaybackUrl(): ?string
    {
        return $this->playback_url ?: $this->stream_url;
    }

    public function obsServerUrl(): string
    {
        return (string) config('live.mediamtx_rtmp_base_url', 'rtmp://127.0.0.1:1935/live');
    }
}
