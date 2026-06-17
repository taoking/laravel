<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoRequest;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(): View
    {
        return view('videos.index', [
            'videos' => Video::query()
                ->latest()
                ->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('videos.create');
    }

    public function store(StoreVideoRequest $request): RedirectResponse
    {
        $file = $request->file('video');
        $path = $file->store('videos/originals', 'public');

        $video = Video::create([
            'user_id' => $request->user()?->id,
            'title' => $request->string('title')->toString(),
            'description' => $request->filled('description')
                ? $request->string('description')->toString()
                : null,
            'original_disk' => 'public',
            'original_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => 'pending',
        ]);

        ProcessUploadedVideo::dispatch($video->id);

        return redirect()
            ->route('videos.show', $video)
            ->with('status', 'Video uploaded successfully. Processing has been queued.');
    }

    public function show(Video $video): View
    {
        $video->load('renditions');

        return view('videos.show', [
            'video' => $video,
        ]);
    }
}
