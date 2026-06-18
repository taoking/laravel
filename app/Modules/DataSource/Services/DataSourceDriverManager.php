<?php

namespace App\Modules\DataSource\Services;

use App\Modules\DataSource\Drivers\DatabaseDriverInterface;
use App\Modules\DataSource\Drivers\MySqlMetadataDriver;
use App\Modules\DataSource\Models\DataSource;
use InvalidArgumentException;

class DataSourceDriverManager
{
    public function driver(DataSource $dataSource): DatabaseDriverInterface
    {
        return match ($dataSource->type) {
            'mysql' => app(MySqlMetadataDriver::class),
            default => throw new InvalidArgumentException("Unsupported data source type [{$dataSource->type}]."),
        };
    }
}
