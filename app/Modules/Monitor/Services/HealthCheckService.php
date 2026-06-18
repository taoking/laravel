<?php

namespace App\Modules\Monitor\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $checks = [
            'database' => $this->database(),
            'redis' => $this->redis(),
            'storage' => $this->storage(),
            'queue' => $this->queue(),
        ];

        return [
            'status' => collect($checks)->every(fn (array $check): bool => $check['status'] === 'ok') ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function database(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');

            return $this->ok($startedAt, [
                'connection' => config('database.default'),
            ]);
        } catch (Throwable $exception) {
            return $this->failed($startedAt, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function redis(): array
    {
        $startedAt = microtime(true);

        try {
            $pong = Redis::connection()->ping();

            return $this->ok($startedAt, [
                'response' => is_string($pong) ? $pong : 'PONG',
            ]);
        } catch (Throwable $exception) {
            return $this->failed($startedAt, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function storage(): array
    {
        $startedAt = microtime(true);
        $disk = (string) config('filesystems.export_disk', config('filesystems.default', 'local'));
        $path = 'health/.check';

        try {
            Storage::disk($disk)->put($path, now()->toISOString());
            Storage::disk($disk)->delete($path);

            return $this->ok($startedAt, [
                'disk' => $disk,
            ]);
        } catch (Throwable $exception) {
            return $this->failed($startedAt, $exception, [
                'disk' => $disk,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function queue(): array
    {
        $startedAt = microtime(true);

        try {
            return $this->ok($startedAt, [
                'connection' => config('queue.default'),
                'pending_jobs' => $this->pendingJobs(),
            ]);
        } catch (Throwable $exception) {
            return $this->failed($startedAt, $exception);
        }
    }

    private function pendingJobs(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        $connection = config('queue.connections.database.connection') ?: config('database.default');
        $table = config('queue.connections.database.table', 'jobs');

        return (int) DB::connection($connection)->table($table)->count();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ok(float $startedAt, array $extra = []): array
    {
        return [
            'status' => 'ok',
            'elapsed_ms' => $this->elapsedMs($startedAt),
            ...$extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function failed(float $startedAt, Throwable $exception, array $extra = []): array
    {
        return [
            'status' => 'failed',
            'elapsed_ms' => $this->elapsedMs($startedAt),
            'message' => $exception->getMessage(),
            ...$extra,
        ];
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
