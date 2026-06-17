@extends('layouts.app')

@section('title', 'Upload Video')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Upload video</h1>
        <p class="mt-1 text-sm text-slate-600">Phase 1 stores the original file and plays it directly in the browser.</p>
    </div>

    <form action="{{ route('videos.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 rounded border border-slate-200 bg-white p-6">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700">Title</label>
            <input
                id="title"
                name="title"
                type="text"
                value="{{ old('title') }}"
                required
                maxlength="255"
                class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none"
            >
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none"
            >{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="video" class="block text-sm font-medium text-slate-700">Video file</label>
            <input
                id="video"
                name="video"
                type="file"
                required
                accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm"
                class="mt-2 block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-700"
            >
            <p class="mt-2 text-xs text-slate-500">Allowed: mp4, mov, webm. Maximum size: 500 MB.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Save video
            </button>
            <a href="{{ route('videos.index') }}" class="text-sm text-slate-600 hover:text-slate-950">Cancel</a>
        </div>
    </form>
@endsection
