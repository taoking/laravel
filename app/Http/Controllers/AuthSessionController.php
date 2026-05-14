<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthSessionController extends Controller
{
    public function store(LoginRequest $request)
    {
        $credentials = [
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'status' => 'active',
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $user->load('roles');

        if ($request->expectsJson()) {
            return ApiResponse::success([
                'user' => UserResource::make($user)->resolve(),
            ], 'Authenticated.');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return ApiResponse::success(message: 'Logged out.');
        }

        return redirect()->route('login');
    }
}
