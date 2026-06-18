<?php

namespace App\Modules\DataSource\Services;

class IdentifierGuard
{
    public static function isSafe(string $identifier): bool
    {
        return preg_match('/\A[A-Za-z0-9_]+\z/', $identifier) === 1;
    }
}
