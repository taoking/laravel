<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Query\Dialects\SqlDialectInterface;
use App\Modules\Query\DTO\SortDTO;

class SortCompiler
{
    public function compile(SortDTO $sort, SqlDialectInterface $dialect): string
    {
        return $dialect->quoteIdentifier($sort->field).' '.$sort->direction;
    }
}
