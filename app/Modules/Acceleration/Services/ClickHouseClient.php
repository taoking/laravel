<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ClickHouseClient
{
    /**
     * @return array{ok: bool, body: string}
     */
    public function execute(string $sql, ?string $database = null): array
    {
        $response = $this->request()
            ->withBody($sql, 'text/plain')
            ->post($this->url($database));

        if ($response->failed()) {
            throw new RuntimeException('ClickHouse query failed: '.$response->body());
        }

        return [
            'ok' => true,
            'body' => $response->body(),
        ];
    }

    public function ping(): bool
    {
        try {
            $this->select('select 1 as ok');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, ?string $database = null): array
    {
        $sql = $this->withJsonFormat($sql);
        $response = $this->request()
            ->withBody($sql, 'text/plain')
            ->post($this->url($database));

        if ($response->failed()) {
            throw new RuntimeException('ClickHouse query failed: '.$response->body());
        }

        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        $data = $json['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertJsonEachRow(string $database, string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $body = 'INSERT INTO '.$this->quoteIdentifier($database).'.'.$this->quoteIdentifier($table)." FORMAT JSONEachRow\n";
        $body .= collect($rows)
            ->map(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR))
            ->implode("\n");

        $this->execute($body, $database);

        return count($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insert(string $table, array $rows): int
    {
        $database = (string) config('bi_acceleration.clickhouse.database', 'bi_accelerator');
        $targetTable = $table;

        if (str_contains($table, '.')) {
            [$database, $targetTable] = explode('.', $table, 2);
            $database = trim($database, '` ');
            $targetTable = trim($targetTable, '` ');
        }

        return $this->insertJsonEachRow($database, $targetTable, $rows);
    }

    private function request(): PendingRequest
    {
        $config = $this->connectionConfig();
        $request = Http::timeout((int) ($config['timeout'] ?? 30));
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        if (is_string($username) && $username !== '') {
            $request = $request->withBasicAuth($username, is_string($password) ? $password : '');
        }

        return $request;
    }

    private function url(?string $database = null): string
    {
        $config = $this->connectionConfig();
        $scheme = $config['scheme'] ?? 'http';
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 8123;
        $query = [];

        if ($database !== null && $database !== '') {
            $query['database'] = $database;
        }

        $url = "{$scheme}://{$host}:{$port}/";

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        $connection = (string) config('bi_acceleration.clickhouse.connection', 'clickhouse');
        $config = config("database.connections.{$connection}", []);

        $connectionConfig = is_array($config) ? $config : [];

        return [
            ...$connectionConfig,
            'scheme' => config('bi_acceleration.clickhouse.scheme', $connectionConfig['scheme'] ?? 'http'),
            'host' => config('bi_acceleration.clickhouse.host', $connectionConfig['host'] ?? '127.0.0.1'),
            'port' => config('bi_acceleration.clickhouse.port', $connectionConfig['port'] ?? 8123),
            'database' => config('bi_acceleration.clickhouse.database', $connectionConfig['database'] ?? 'bi_accelerator'),
            'username' => config('bi_acceleration.clickhouse.username', $connectionConfig['username'] ?? 'default'),
            'password' => config('bi_acceleration.clickhouse.password', $connectionConfig['password'] ?? ''),
            'timeout' => config('bi_acceleration.clickhouse.timeout', $connectionConfig['timeout'] ?? 30),
        ];
    }

    private function withJsonFormat(string $sql): string
    {
        return preg_match('/\bformat\s+json\b/i', $sql) === 1 ? $sql : rtrim($sql, " \t\n\r\0\x0B;").' FORMAT JSON';
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe ClickHouse identifier [{$identifier}].");
        }

        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
