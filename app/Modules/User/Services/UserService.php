<?php

namespace App\Modules\User\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return User::query()
            ->with(['organization', 'department', 'roles.permissions'])
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): User
    {
        return DB::transaction(function () use ($payload): User {
            $roleIds = Arr::pull($payload, 'role_ids', []);
            $payload['status'] ??= 'active';

            $user = User::query()->create($payload);
            $user->roles()->sync($roleIds);

            return $user->load(['organization', 'department', 'roles.permissions']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $user, array $payload): User
    {
        return DB::transaction(function () use ($user, $payload): User {
            $shouldSyncRoles = array_key_exists('role_ids', $payload);
            $roleIds = Arr::pull($payload, 'role_ids', []);

            $user->fill($payload);
            $user->save();

            if ($shouldSyncRoles) {
                $user->roles()->sync($roleIds);
            }

            return $user->load(['organization', 'department', 'roles.permissions']);
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->roles()->detach();
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
