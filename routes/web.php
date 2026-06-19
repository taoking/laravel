<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/{any?}', function () {
    return view('welcome');
})
    ->where('any', '^(?!api).*$')
    ->withoutMiddleware([PreventRequestForgery::class, StartSession::class, ShareErrorsFromSession::class]);
