<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AdminAuthorizer
{
    public function assertAdmin(?User $user): void
    {
        if ($user === null || ! $user->roles()->where('code', 'admin')->exists()) {
            throw new AuthorizationException('Only administrators can use this endpoint.');
        }
    }
}
