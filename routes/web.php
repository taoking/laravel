<?php

use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\LiveRoomController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoHlsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');
Route::get('/videos/create', [VideoController::class, 'create'])->name('videos.create');
Route::post('/videos', [VideoController::class, 'store'])->name('videos.store');
Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');
Route::get('/videos/{video}/hls/playlist', [VideoHlsController::class, 'playlist'])->name('videos.hls.playlist');
Route::get('/videos/{video}/hls/renditions/{label}/playlist', [VideoHlsController::class, 'renditionPlaylist'])
    ->where('label', '[A-Za-z0-9._-]+')
    ->name('videos.hls.rendition.playlist');
Route::get('/videos/{video}/hls/renditions/{label}/segment/{filename}', [VideoHlsController::class, 'renditionSegment'])
    ->where('label', '[A-Za-z0-9._-]+')
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('videos.hls.rendition.segment');
Route::get('/videos/{video}/hls/segment/{filename}', [VideoHlsController::class, 'segment'])
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('videos.hls.segment');

Route::get('/rooms', [LiveRoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/create', [LiveRoomController::class, 'create'])->name('rooms.create');
Route::post('/rooms', [LiveRoomController::class, 'store'])->name('rooms.store');
Route::get('/rooms/{room}/edit', [LiveRoomController::class, 'edit'])->name('rooms.edit');
Route::patch('/rooms/{room}', [LiveRoomController::class, 'update'])->name('rooms.update');
Route::patch('/rooms/{room}/status', [LiveRoomController::class, 'updateStatus'])->name('rooms.status.update');
Route::get('/rooms/{room}', [LiveRoomController::class, 'show'])->name('rooms.show');
Route::post('/rooms/{room}/messages', [ChatMessageController::class, 'store'])->name('rooms.messages.store');
