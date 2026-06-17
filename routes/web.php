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
Route::get('/videos/{video}/hls/segment/{filename}', [VideoHlsController::class, 'segment'])
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('videos.hls.segment');

Route::get('/rooms', [LiveRoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room}', [LiveRoomController::class, 'show'])->name('rooms.show');
Route::post('/rooms/{room}/messages', [ChatMessageController::class, 'store'])->name('rooms.messages.store');
