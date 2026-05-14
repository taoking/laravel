<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Access\Services\PermissionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->string('keyword')->toString(), function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: UserResource::collection($users->getCollection())->resolve(),
            meta: [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => $validated['status'] ?? 'active',
            ]);

            $user->roles()->sync($validated['role_ids'] ?? []);

            return $user->load('roles');
        });

        app(PermissionService::class)->clearForUser($user);

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
        ], 'Created.', 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success([
            'user' => UserResource::make($user->load('roles'))->resolve(),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'email' => ['sometimes', 'required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->fill(collect($validated)->except('role_ids')->all())->save();

            if (array_key_exists('role_ids', $validated)) {
                $user->roles()->sync($validated['role_ids']);
            }
        });

        app(PermissionService::class)->clearForUser($user);

        return ApiResponse::success([
            'user' => UserResource::make($user->refresh()->load('roles'))->resolve(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->roles()->detach();
        $user->delete();

        app(PermissionService::class)->clearForUser($user);

        return ApiResponse::success(message: 'Deleted.');
    }
}
