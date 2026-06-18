<?php

namespace App\Modules\DataSource\Services;

use App\Modules\DataSource\Models\DataSource;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;

class DataSourceConnectionFactory
{
    public function __construct(private readonly DataSourcePasswordEncryptor $passwordEncryptor) {}

    public function make(DataSource $dataSource): ConnectionInterface
    {
        $connectionName = $this->connectionName($dataSource);
        $options = $dataSource->options_json ?? [];
        $timeout = (int) ($options['timeout'] ?? 5);

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $dataSource->host,
            'port' => $dataSource->port,
            'database' => $dataSource->database_name,
            'username' => $dataSource->username,
            'password' => $this->passwordEncryptor->decrypt($dataSource->password_encrypted),
            'unix_socket' => '',
            'charset' => $dataSource->charset ?: 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_TIMEOUT => max(1, min($timeout, 30)),
            ],
        ]);

        DB::purge($connectionName);

        return DB::connection($connectionName);
    }

    public function disconnect(DataSource $dataSource): void
    {
        DB::disconnect($this->connectionName($dataSource));
    }

    private function connectionName(DataSource $dataSource): string
    {
        return 'data_source_'.$dataSource->getKey();
    }
}
