<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\User\Resources\UserResource;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        $result = $authService->login($request->validated(), $request->ip(), $request->userAgent());

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'user' => (new UserResource($result['user']))->resolve($request),
        ]);
    }

    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request->user());

        return ApiResponse::noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organization', 'department', 'roles.permissions']);

        return ApiResponse::success((new UserResource($user))->resolve($request));
    }
}
