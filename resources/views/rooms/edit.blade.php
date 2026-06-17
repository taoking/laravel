@extends('layouts.app')

@section('title', 'Edit '.$room->title)

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Edit Live Room</h1>
        <p class="mt-1 text-sm text-slate-600">Update the room source and lifecycle status.</p>
    </div>

    <form method="POST" action="{{ route('rooms.update', $room) }}" class="rounded border border-slate-200 bg-white p-6">
        @include('rooms._form', [
            'method' => 'PATCH',
            'room' => $room,
            'videos' => $videos,
            'statuses' => $statuses,
            'playbackTypes' => $playbackTypes,
            'submitLabel' => 'Save changes',
        ])
    </form>
@endsection
