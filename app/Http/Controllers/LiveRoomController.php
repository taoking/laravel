<?php

namespace App\Http\Controllers;

use App\Models\LiveRoom;
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
}
