<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\User\Requests\StoreUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Resources\UserResource;
use App\Modules\User\Services\UserService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request, UserService $userService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $users = $userService->paginate($pageSize);

        return ApiResponse::paginated(
            $users,
            UserResource::collection($users->items())->resolve($request),
        );
    }

    public function store(StoreUserRequest $request, UserService $userService): JsonResponse
    {
        $user = $userService->create($request->validated());

        return ApiResponse::created((new UserResource($user))->resolve($request));
    }

    public function show(User $user, Request $request): JsonResponse
    {
        $user->load(['organization', 'department', 'roles.permissions']);

        return ApiResponse::success((new UserResource($user))->resolve($request));
    }

    public function update(UpdateUserRequest $request, User $user, UserService $userService): JsonResponse
    {
        $user = $userService->update($user, $request->validated());

        return ApiResponse::success((new UserResource($user))->resolve($request));
    }

    public function destroy(User $user, UserService $userService): JsonResponse
    {
        $userService->delete($user);

        return ApiResponse::noContent();
    }
}
