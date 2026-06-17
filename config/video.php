<?php

return [
    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
    'thumbnail_time' => env('VIDEO_THUMBNAIL_TIME', '00:00:01'),
    'process_timeout' => (int) env('VIDEO_PROCESS_TIMEOUT', 300),
    'hls_time' => (int) env('VIDEO_HLS_TIME', 6),
];
