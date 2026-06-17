@extends('layouts.app')

@section('title', $room->title)

@section('content')
    @php
        $video = $room->video;
        $externalPlaybackUrl = $room->effectivePlaybackUrl();
        $streamPath = $externalPlaybackUrl ? parse_url($externalPlaybackUrl, PHP_URL_PATH) : null;
        $streamIsHls = $streamPath && str_contains($streamPath, '.m3u8');
    @endphp

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <div class="mb-2">
                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $room->status }}</span>
            </div>
            <h1 class="text-2xl font-semibold">{{ $room->title }}</h1>
            @if ($room->description)
                <p class="mt-2 text-slate-600">{{ $room->description }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($room->status !== \App\Models\LiveRoom::STATUS_LIVE)
                <form method="POST" action="{{ route('rooms.status.update', $room) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ \App\Models\LiveRoom::STATUS_LIVE }}">
                    <button type="submit" class="rounded bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Go live</button>
                </form>
            @endif
            @if ($room->status !== \App\Models\LiveRoom::STATUS_ENDED)
                <form method="POST" action="{{ route('rooms.status.update', $room) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ \App\Models\LiveRoom::STATUS_ENDED }}">
                    <button type="submit" class="rounded bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-950">End room</button>
                </form>
            @endif
            <a href="{{ route('rooms.edit', $room) }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                Edit
            </a>
            <a href="{{ route('rooms.index') }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                Back
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <section class="space-y-4">
            <div class="rounded border border-slate-200 bg-white p-4">
                @if ($room->usesBoundVideo() && $video && $video->hlsReady())
                    <video
                        controls
                        preload="metadata"
                        class="aspect-video w-full rounded bg-black"
                        data-hls-player
                        data-hls-url="{{ route('videos.hls.playlist', $video) }}"
                        data-fallback-url="{{ $video->isPlayable() ? $video->playback_url : route('videos.hls.playlist', $video) }}"
                    >
                        Your browser does not support HTML5 video playback.
                    </video>
                    <p class="mt-3 text-sm text-emerald-700">Pseudo live is playing this room's bound video through {{ $video->hlsSourceLabel() }}.</p>
                @elseif ($room->usesBoundVideo() && $video && $video->isPlayable())
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
                    <p class="mt-3 text-sm text-amber-700">The bound video is using MP4 fallback because HLS is not ready yet.</p>
                @elseif (($room->usesExternalHls() || $room->usesMediaMtx()) && $externalPlaybackUrl)
                    <video
                        controls
                        preload="metadata"
                        class="aspect-video w-full rounded bg-black"
                        @if ($streamIsHls)
                            data-hls-player
                            data-hls-url="{{ $externalPlaybackUrl }}"
                            data-fallback-url="{{ $externalPlaybackUrl }}"
                        @else
                            src="{{ $externalPlaybackUrl }}"
                        @endif
                    >
                        Your browser does not support HTML5 video playback.
                    </video>
                    @if ($room->usesMediaMtx())
                        <p class="mt-3 text-sm text-amber-700">If the live HLS does not start yet, confirm MediaMTX is running, OBS is pushing, the stream key matches, and the m3u8 request is not 404.</p>
                    @else
                        <p class="mt-3 text-sm text-emerald-700">This room is playing an external HLS URL.</p>
                    @endif
                @else
                    <div class="flex aspect-video items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">
                        No playback source has been attached to this room.
                    </div>
                @endif
            </div>

            <div class="rounded border border-slate-200 bg-white p-4">
                <h2 class="text-lg font-semibold">Room Details</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium text-slate-500">Status</dt>
                        <dd class="mt-1 text-slate-900">{{ $room->status }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Playback type</dt>
                        <dd class="mt-1 text-slate-900">{{ $room->playback_type ?: \App\Models\LiveRoom::PLAYBACK_VIDEO }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Owner</dt>
                        <dd class="mt-1 text-slate-900">{{ $room->owner?->name ?? 'None' }}</dd>
                    </div>
                    @if ($video)
                        <div>
                            <dt class="font-medium text-slate-500">Video</dt>
                            <dd class="mt-1">
                                <a href="{{ route('videos.show', $video) }}" class="text-slate-900 hover:underline">{{ $video->title }}</a>
                            </dd>
                        </div>
                    @endif
                    @if ($externalPlaybackUrl)
                        <div>
                            <dt class="font-medium text-slate-500">Playback URL</dt>
                            <dd class="mt-1 break-all text-slate-900">{{ $externalPlaybackUrl }}</dd>
                        </div>
                    @endif
                    @if ($room->usesMediaMtx())
                        <div>
                            <dt class="font-medium text-slate-500">Media server</dt>
                            <dd class="mt-1 text-slate-900">{{ $room->media_server ?? 'mediamtx' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Playback protocol</dt>
                            <dd class="mt-1 text-slate-900">{{ $room->playback_protocol ?? 'hls' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Stream key</dt>
                            <dd class="mt-1 break-all text-slate-900">{{ $room->stream_key }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">OBS Server</dt>
                            <dd class="mt-1 break-all text-slate-900">{{ $obsServerUrl }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">OBS Stream Key</dt>
                            <dd class="mt-1 break-all text-slate-900">{{ $room->stream_key }}</dd>
                        </div>
                        @if ($room->push_url)
                            <div>
                                <dt class="font-medium text-slate-500">Full push URL</dt>
                                <dd class="mt-1 break-all text-slate-900">{{ $room->push_url }}</dd>
                            </div>
                        @endif
                    @endif
                    @if ($room->started_at)
                        <div>
                            <dt class="font-medium text-slate-500">Started</dt>
                            <dd class="mt-1 text-slate-900">{{ $room->started_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    @endif
                    @if ($room->ended_at)
                        <div>
                            <dt class="font-medium text-slate-500">Ended</dt>
                            <dd class="mt-1 text-slate-900">{{ $room->ended_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        <aside
            class="rounded border border-slate-200 bg-white p-4"
            data-live-chat
            data-room-id="{{ $room->id }}"
            data-messages-url="{{ route('rooms.messages.store', $room) }}"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Chat</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Online: <span data-chat-online-count>0</span>
                    </p>
                </div>
                <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600" data-chat-status>Connecting</span>
            </div>

            <div class="mt-4 flex h-[28rem] flex-col">
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto rounded border border-slate-200 bg-slate-50 p-3" data-chat-messages>
                    @if ($messages->isEmpty())
                        <p class="py-8 text-center text-sm text-slate-500" data-chat-empty>No messages yet.</p>
                    @endif

                    @foreach ($messages as $message)
                        <article class="rounded border border-slate-200 bg-white px-3 py-2" data-message-id="{{ $message->id }}">
                            <div class="flex items-center justify-between gap-3 text-xs text-slate-500">
                                <span class="font-medium text-slate-800">{{ $message->displayName() }}</span>
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('H:i') }}</time>
                            </div>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800">{{ $message->content }}</p>
                        </article>
                    @endforeach
                </div>

                <form class="mt-4 space-y-3" data-chat-form>
                    <div>
                        <label for="chat-nickname" class="block text-sm font-medium text-slate-700">Nickname</label>
                        <input
                            id="chat-nickname"
                            type="text"
                            name="nickname"
                            maxlength="40"
                            value="{{ old('nickname', session('live_chat_nickname')) }}"
                            class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            data-chat-nickname
                        >
                    </div>
                    <div>
                        <label for="chat-content" class="block text-sm font-medium text-slate-700">Message</label>
                        <textarea
                            id="chat-content"
                            name="content"
                            rows="3"
                            maxlength="1000"
                            required
                            class="mt-1 w-full resize-none rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                            data-chat-content
                        ></textarea>
                    </div>
                    <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400" data-chat-submit>
                        Send
                    </button>
                    <p class="hidden text-sm text-red-700" data-chat-error></p>
                </form>
            </div>
        </aside>
    </div>
@endsection
