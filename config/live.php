<?php

return [
    'media_server' => env('LIVE_MEDIA_SERVER', 'mediamtx'),
    'mediamtx_rtmp_base_url' => env('MEDIAMTX_RTMP_BASE_URL', 'rtmp://127.0.0.1:1935/live'),
    'mediamtx_hls_base_url' => env('MEDIAMTX_HLS_BASE_URL', 'http://127.0.0.1:8888/live'),
    'default_stream_key' => env('MEDIAMTX_DEFAULT_STREAM_KEY', 'test'),
];
