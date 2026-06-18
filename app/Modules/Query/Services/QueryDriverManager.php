<?php

namespace App\Modules\Query\Services;

use App\Modules\DataSource\Models\DataSource;
use App\Modules\Query\Drivers\DatabaseDriverInterface;
use App\Modules\Query\Drivers\MySqlQueryDriver;
use InvalidArgumentException;

class QueryDriverManager
{
    public function driver(DataSource $dataSource): DatabaseDriverInterface
    {
        return match ($dataSource->type) {
            'mysql' => app(MySqlQueryDriver::class),
            default => throw new InvalidArgumentException("Unsupported query data source type [{$dataSource->type}]."),
        };
    }
}
