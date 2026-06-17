@extends('layouts.app')

@section('title', 'Create Live Room')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Create Live Room</h1>
        <p class="mt-1 text-sm text-slate-600">Create a room and optionally bind an existing HLS-processed video for pseudo live playback.</p>
    </div>

    <form method="POST" action="{{ route('rooms.store') }}" class="rounded border border-slate-200 bg-white p-6">
        @include('rooms._form', [
            'room' => $room,
            'videos' => $videos,
            'statuses' => $statuses,
            'submitLabel' => 'Create room',
        ])
    </form>
@endsection
