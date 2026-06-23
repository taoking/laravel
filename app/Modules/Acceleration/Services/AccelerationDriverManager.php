<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Drivers\AccelerationDriverInterface;
use App\Modules\Acceleration\Drivers\ClickHouseAccelerationDriver;
use App\Modules\Acceleration\Models\AccelerationProfile;
use InvalidArgumentException;

class AccelerationDriverManager
{
    public function __construct(private readonly ClickHouseAccelerationDriver $clickHouseDriver) {}

    public function driver(AccelerationProfile|string $profileOrEngine): AccelerationDriverInterface
    {
        $engine = $profileOrEngine instanceof AccelerationProfile ? $profileOrEngine->engine_type : $profileOrEngine;

        return match ($engine) {
            'clickhouse' => $this->clickHouseDriver,
            default => throw new InvalidArgumentException("Acceleration engine [{$engine}] is not supported."),
        };
    }
}
