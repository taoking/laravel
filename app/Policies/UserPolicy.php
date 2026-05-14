<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('access.users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $user->hasPermission('access.users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('access.users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('access.users.manage') && ! $target->isSuperAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission('access.users.manage') && ! $user->is($target) && ! $target->isSuperAdmin();
    }
}
