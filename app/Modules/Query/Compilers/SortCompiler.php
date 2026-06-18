<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Query\DTO\SortDTO;

class SortCompiler
{
    public function __construct(private readonly SqlIdentifier $identifier) {}

    public function compile(SortDTO $sort): string
    {
        return $this->identifier->quote($sort->field).' '.$sort->direction;
    }
}
