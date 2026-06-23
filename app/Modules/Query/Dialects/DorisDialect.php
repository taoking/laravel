<?php

namespace App\Modules\Query\Dialects;

class DorisDialect extends MySqlDialect
{
    public function getName(): string
    {
        return 'doris';
    }
}
