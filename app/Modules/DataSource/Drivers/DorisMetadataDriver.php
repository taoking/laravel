<?php

namespace App\Modules\DataSource\Drivers;

class DorisMetadataDriver extends MySqlProtocolOlapMetadataDriver
{
    public function dialect(): string
    {
        return 'doris';
    }
}
