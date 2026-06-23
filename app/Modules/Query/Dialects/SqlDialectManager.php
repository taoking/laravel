<?php

namespace App\Modules\Query\Dialects;

use App\Modules\DataSource\Models\DataSource;
use InvalidArgumentException;

class SqlDialectManager
{
    public function dialect(DataSource $dataSource): SqlDialectInterface
    {
        return match ($dataSource->type) {
            'mysql' => app(MySqlDialect::class),
            'starrocks' => app(StarRocksDialect::class),
            'doris' => app(DorisDialect::class),
            default => throw new InvalidArgumentException("Unsupported SQL dialect [{$dataSource->type}]."),
        };
    }
}
