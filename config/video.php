<?php

return [
    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
    'thumbnail_time' => env('VIDEO_THUMBNAIL_TIME', '00:00:01'),
    'process_timeout' => (int) env('VIDEO_PROCESS_TIMEOUT', 300),
    'hls_time' => (int) env('VIDEO_HLS_TIME', 6),
    'hls_disk' => env('VIDEO_HLS_DISK', 'public'),
    'hls_directory' => env('VIDEO_HLS_DIRECTORY', 'videos/hls'),
    'hls_segment_time' => (int) env('VIDEO_HLS_SEGMENT_TIME', env('VIDEO_HLS_TIME', 6)),
    'hls_allow_upscale' => (bool) env('VIDEO_HLS_ALLOW_UPSCALE', false),
    'hls_master_playlist_name' => env('VIDEO_HLS_MASTER_PLAYLIST_NAME', 'master.m3u8'),
    'renditions' => [
        '360p' => [
            'width' => 640,
            'height' => 360,
            'video_bitrate' => '800k',
            'audio_bitrate' => '96k',
        ],
        '720p' => [
            'width' => 1280,
            'height' => 720,
            'video_bitrate' => '2800k',
            'audio_bitrate' => '128k',
        ],
    ],
];
