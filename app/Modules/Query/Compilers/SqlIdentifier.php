<?php

namespace App\Modules\Query\Compilers;

use App\Modules\DataSource\Services\IdentifierGuard;
use InvalidArgumentException;

class SqlIdentifier
{
    public function quote(string $identifier): string
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe SQL identifier [{$identifier}].");
        }

        return '`'.$identifier.'`';
    }
}
