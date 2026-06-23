<?php

namespace App\Modules\Query\Dialects;

class StarRocksDialect extends MySqlDialect
{
    public function getName(): string
    {
        return 'starrocks';
    }
}
