<?php

namespace App\Modules\Acceleration\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AccelerationManagementAuthorizer
{
    public function assertCanManage(?User $user): void
    {
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        $isAdmin = $user->roles()->where('code', 'admin')->exists();

        if ($isAdmin || $user->hasPermission('acceleration.manage') || $user->hasPermission('datasets.manage')) {
            return;
        }

        throw new AuthorizationException;
    }
}
