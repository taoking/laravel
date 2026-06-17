@extends('layouts.app')

@section('title', 'Videos')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Videos</h1>
            <p class="mt-1 text-sm text-slate-600">Uploaded source videos for the VOD learning phase.</p>
        </div>
        <a href="{{ route('videos.create') }}" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Upload video
        </a>
    </div>

    @if ($videos->isEmpty())
        <div class="rounded border border-dashed border-slate-300 bg-white p-8 text-center">
            <p class="text-slate-700">No videos uploaded yet.</p>
            <a href="{{ route('videos.create') }}" class="mt-4 inline-flex rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Upload the first video
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-slate-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Size</th>
                        <th class="px-4 py-3 font-semibold">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($videos as $video)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('videos.show', $video) }}" class="font-medium text-slate-900 hover:underline">
                                    {{ $video->title }}
                                </a>
                                @if ($video->original_filename)
                                    <div class="mt-1 text-xs text-slate-500">{{ $video->original_filename }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">{{ $video->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $video->file_size_for_humans }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $video->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $videos->links() }}
        </div>
    @endif
@endsection
