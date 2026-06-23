<?php

namespace App\Modules\DataSource\Drivers;

class StarRocksMetadataDriver extends MySqlProtocolOlapMetadataDriver
{
    public function dialect(): string
    {
        return 'starrocks';
    }
}
