@csrf

@isset($method)
    @method($method)
@endisset

<div class="space-y-6">
    <div>
        <label for="title" class="block text-sm font-medium text-slate-700">Title</label>
        <input
            id="title"
            type="text"
            name="title"
            value="{{ old('title', $room->title) }}"
            required
            class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
        >
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
        >{{ old('description', $room->description) }}</textarea>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
            <select
                id="status"
                name="status"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $room->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="playback_type" class="block text-sm font-medium text-slate-700">Playback type</label>
            <select
                id="playback_type"
                name="playback_type"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
                @foreach ($playbackTypes as $type)
                    <option value="{{ $type }}" @selected(old('playback_type', $room->playback_type ?: \App\Models\LiveRoom::PLAYBACK_VIDEO) === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="video_id" class="block text-sm font-medium text-slate-700">Bound video</label>
            <select
                id="video_id"
                name="video_id"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
                <option value="">No video</option>
                @foreach ($videos as $video)
                    <option value="{{ $video->id }}" @selected((string) old('video_id', $room->video_id) === (string) $video->id)>
                        #{{ $video->id }} {{ $video->title }} @if ($video->hls_status) (HLS: {{ $video->hls_status }}) @endif
                    </option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-slate-500">Used when playback type is video.</p>
        </div>

        <div>
            <label for="playback_url" class="block text-sm font-medium text-slate-700">External HLS playback URL</label>
            <input
                id="playback_url"
                type="url"
                name="playback_url"
                value="{{ old('playback_url', $room->playback_url ?: $room->stream_url) }}"
                placeholder="https://example.test/live/index.m3u8"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
            <p class="mt-2 text-xs text-slate-500">Used when playback type is hls_url.</p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="stream_key" class="block text-sm font-medium text-slate-700">MediaMTX stream key</label>
            <input
                id="stream_key"
                type="text"
                name="stream_key"
                value="{{ old('stream_key', $room->stream_key ?: config('live.default_stream_key', 'test')) }}"
                placeholder="test"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
            <p class="mt-2 text-xs text-slate-500">Used when playback type is mediamtx.</p>
        </div>

        <div class="rounded border border-slate-200 bg-slate-50 p-3 text-sm">
            <div class="font-medium text-slate-700">OBS Server</div>
            <div class="mt-1 break-all text-slate-900">{{ config('live.mediamtx_rtmp_base_url', 'rtmp://127.0.0.1:1935/live') }}</div>
            <div class="mt-3 font-medium text-slate-700">Current playback URL</div>
            <div class="mt-1 break-all text-slate-900">{{ $room->effectivePlaybackUrl() ?? 'Generated after save for MediaMTX.' }}</div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $room->exists ? route('rooms.show', $room) : route('rooms.index') }}" class="rounded border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">
            Cancel
        </a>
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            {{ $submitLabel }}
        </button>
    </div>
</div>
