<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLiveRoomRequest;
use App\Http\Requests\UpdateLiveRoomStatusRequest;
use App\Models\LiveRoom;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LiveRoomController extends Controller
{
    public function index(): View
    {
        return view('rooms.index', [
            'rooms' => LiveRoom::query()
                ->with('video')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('rooms.create', [
            'room' => new LiveRoom([
                'status' => LiveRoom::STATUS_SCHEDULED,
            ]),
            'statuses' => LiveRoom::statuses(),
            'videos' => $this->videosForSelect(),
        ]);
    }

    public function store(SaveLiveRoomRequest $request): RedirectResponse
    {
        $room = new LiveRoom($this->validatedRoomData($request));
        $room->owner_id = $request->user()?->id;

        $this->applyStatusTimestamps($room, $room->status);

        $room->save();

        return redirect()
            ->route('rooms.show', $room)
            ->with('status', 'Live room created.');
    }

    public function show(LiveRoom $room): View
    {
        $room->load(['owner', 'video']);

        $messages = $room->messages()
            ->with('user')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return view('rooms.show', [
            'room' => $room,
            'messages' => $messages,
        ]);
    }

    public function edit(LiveRoom $room): View
    {
        return view('rooms.edit', [
            'room' => $room,
            'statuses' => LiveRoom::statuses(),
            'videos' => $this->videosForSelect(),
        ]);
    }

    public function update(SaveLiveRoomRequest $request, LiveRoom $room): RedirectResponse
    {
        $room->fill($this->validatedRoomData($request));

        $this->applyStatusTimestamps($room, $room->status);

        $room->save();

        return redirect()
            ->route('rooms.show', $room)
            ->with('status', 'Live room updated.');
    }

    public function updateStatus(UpdateLiveRoomStatusRequest $request, LiveRoom $room): RedirectResponse
    {
        $room->status = $request->validated('status');

        $this->applyStatusTimestamps($room, $room->status);

        $room->save();

        return redirect()
            ->route('rooms.show', $room)
            ->with('status', 'Room status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRoomData(SaveLiveRoomRequest $request): array
    {
        $data = $request->validated();

        $data['video_id'] = $data['video_id'] ?? null;
        $data['stream_url'] = filled($data['stream_url'] ?? null)
            ? $data['stream_url']
            : null;

        return $data;
    }

    private function applyStatusTimestamps(LiveRoom $room, string $status): void
    {
        if ($status === LiveRoom::STATUS_SCHEDULED) {
            $room->started_at = null;
            $room->ended_at = null;

            return;
        }

        if ($status === LiveRoom::STATUS_LIVE) {
            $room->started_at ??= now();
            $room->ended_at = null;

            return;
        }

        if ($status === LiveRoom::STATUS_ENDED) {
            $room->started_at ??= now();
            $room->ended_at ??= now();
        }
    }

    /**
     * @return Collection<int, Video>
     */
    private function videosForSelect(): Collection
    {
        return Video::query()
            ->latest()
            ->get(['id', 'title', 'hls_status', 'hls_path', 'original_filename']);
    }
}
