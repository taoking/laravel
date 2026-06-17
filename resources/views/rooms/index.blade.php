@extends('layouts.app')

@section('title', 'Live Rooms')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Live Rooms</h1>
            <p class="mt-1 text-sm text-slate-600">Rooms combine a playback source with a realtime chat stream.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('videos.index') }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                Videos
            </a>
            <a href="{{ route('rooms.create') }}" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Create room
            </a>
        </div>
    </div>

    @if ($rooms->isEmpty())
        <div class="rounded border border-dashed border-slate-300 bg-white p-8 text-center">
            <p class="text-slate-700">No live rooms yet.</p>
            <a href="{{ route('rooms.create') }}" class="mt-4 inline-flex rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Create the first room
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-slate-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Source</th>
                        <th class="px-4 py-3 font-semibold">Created</th>
                        <th class="px-4 py-3 font-semibold">Manage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($rooms as $room)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('rooms.show', $room) }}" class="font-medium text-slate-900 hover:underline">
                                    {{ $room->title }}
                                </a>
                                @if ($room->description)
                                    <div class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $room->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $room->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                @if ($room->usesBoundVideo() && $room->video)
                                    Video: {{ $room->video->title }}
                                @elseif ($room->usesMediaMtx())
                                    MediaMTX: {{ $room->stream_key ?? 'test' }}
                                @elseif ($room->usesExternalHls())
                                    External HLS
                                @else
                                    None
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $room->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('rooms.edit', $room) }}" class="text-slate-700 hover:text-slate-950 hover:underline">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $rooms->links() }}
        </div>
    @endif
@endsection
