@extends('layouts.app')

@section('title', $video->title)

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $video->title }}</h1>
            @if ($video->description)
                <p class="mt-2 text-slate-600">{{ $video->description }}</p>
            @endif
        </div>
        <a href="{{ route('videos.index') }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
            Back to videos
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
        <section class="rounded border border-slate-200 bg-white p-4">
            @if ($video->hasThumbnail())
                <img src="{{ $video->thumbnail_url }}" alt="Thumbnail for {{ $video->title }}" class="mb-4 aspect-video w-full rounded object-cover">
            @endif

            @if ($video->isPlayable())
                <video
                    controls
                    preload="metadata"
                    class="aspect-video w-full rounded bg-black"
                    data-hls-player
                    data-fallback-url="{{ $video->playback_url }}"
                    @if ($video->hlsReady())
                        data-hls-url="{{ route('videos.hls.playlist', $video) }}"
                    @endif
                >
                    @unless ($video->hlsReady())
                        <source src="{{ $video->playback_url }}" type="{{ $video->mime_type }}">
                    @endunless
                    Your browser does not support HTML5 video playback.
                </video>

                @if ($video->hlsReady())
                    <p class="mt-3 text-sm text-emerald-700">HLS is ready. The player will prefer the HLS playlist and fall back to the original file if needed.</p>
                @elseif ($video->hls_status === 'processing')
                    <p class="mt-3 text-sm text-amber-700">HLS is still processing. The original file is available as a fallback.</p>
                @elseif ($video->hls_status === 'failed')
                    <p class="mt-3 text-sm text-red-700">HLS generation failed. The original file is still available.</p>
                @else
                    <p class="mt-3 text-sm text-slate-600">HLS has not been generated yet. The original file is available.</p>
                @endif
            @else
                <div class="flex aspect-video items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">
                    The original video file is missing or unavailable. Run <code class="mx-1 rounded bg-slate-200 px-1 py-0.5">php artisan storage:link</code> and confirm the file exists on the public disk.
                </div>
            @endif
        </section>

        <aside class="rounded border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold">Details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-slate-500">Status</dt>
                    <dd class="mt-1 text-slate-900">{{ $video->status }}</dd>
                </div>
                @if ($video->duration_seconds !== null)
                    <div>
                        <dt class="font-medium text-slate-500">Duration</dt>
                        <dd class="mt-1 text-slate-900">{{ $video->duration_seconds }} seconds</dd>
                    </div>
                @endif
                @if ($video->width || $video->height)
                    <div>
                        <dt class="font-medium text-slate-500">Resolution</dt>
                        <dd class="mt-1 text-slate-900">{{ $video->width ?? '?' }} x {{ $video->height ?? '?' }}</dd>
                    </div>
                @endif
                @if ($video->video_codec || $video->audio_codec)
                    <div>
                        <dt class="font-medium text-slate-500">Codecs</dt>
                        <dd class="mt-1 text-slate-900">
                            Video: {{ $video->video_codec ?? 'Unknown' }}<br>
                            Audio: {{ $video->audio_codec ?? 'Unknown' }}
                        </dd>
                    </div>
                @endif
                @if ($video->thumbnail_path)
                    <div>
                        <dt class="font-medium text-slate-500">Thumbnail path</dt>
                        <dd class="mt-1 break-all text-slate-900">{{ $video->thumbnail_path }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="font-medium text-slate-500">HLS status</dt>
                    <dd class="mt-1 text-slate-900">{{ $video->hls_status ?? 'pending' }}</dd>
                </div>
                @if ($video->hls_path)
                    <div>
                        <dt class="font-medium text-slate-500">HLS path</dt>
                        <dd class="mt-1 break-all text-slate-900">local:{{ $video->hls_path }}</dd>
                    </div>
                @endif
                @if ($video->hls_error_message)
                    <div>
                        <dt class="font-medium text-slate-500">HLS error</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-red-700">{{ $video->hls_error_message }}</dd>
                    </div>
                @endif
                @if ($video->error_message)
                    <div>
                        <dt class="font-medium text-slate-500">Error</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-red-700">{{ $video->error_message }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="font-medium text-slate-500">Original filename</dt>
                    <dd class="mt-1 break-all text-slate-900">{{ $video->original_filename ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">MIME type</dt>
                    <dd class="mt-1 text-slate-900">{{ $video->mime_type ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">File size</dt>
                    <dd class="mt-1 text-slate-900">{{ $video->file_size_for_humans }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Storage path</dt>
                    <dd class="mt-1 break-all text-slate-900">{{ $video->original_disk }}:{{ $video->original_path }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Created</dt>
                    <dd class="mt-1 text-slate-900">{{ $video->created_at?->format('Y-m-d H:i:s') }}</dd>
                </div>
            </dl>
        </aside>
    </div>
@endsection
