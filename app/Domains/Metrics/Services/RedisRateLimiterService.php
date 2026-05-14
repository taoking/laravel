<?php

namespace App\Domains\Metrics\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisRateLimiterService
{
    /**
     * @var array<string, array{count: int, expires_at: int}>
     */
    private array $memoryBuckets = [];

    public function allow(string $key, int $limit, int $decaySeconds): bool
    {
        $redisKey = "lua-rate-limit:{$key}";

        try {
            $allowed = Redis::connection()->command('eval', [
                $this->luaScript(),
                [$redisKey, (string) $limit, (string) $decaySeconds],
                1,
            ]);

            return (int) $allowed === 1;
        } catch (Throwable) {
            return $this->allowWithCacheFallback($redisKey, $limit, $decaySeconds);
        }
    }

    public function luaScript(): string
    {
        return <<<'LUA'
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[2])
end
if current > tonumber(ARGV[1]) then
    return 0
end
return 1
LUA;
    }

    private function allowWithCacheFallback(string $key, int $limit, int $decaySeconds): bool
    {
        try {
            return $this->allowWithCacheStore($key, $limit, $decaySeconds);
        } catch (Throwable) {
            return $this->allowWithMemory($key, $limit, $decaySeconds);
        }
    }

    private function allowWithCacheStore(string $key, int $limit, int $decaySeconds): bool
    {
        $now = now()->timestamp;
        $bucket = Cache::get($key);

        if (! is_array($bucket) || ($bucket['expires_at'] ?? 0) <= $now) {
            $bucket = [
                'count' => 0,
                'expires_at' => $now + $decaySeconds,
            ];
        }

        $bucket['count'] = ((int) $bucket['count']) + 1;
        Cache::put($key, $bucket, $decaySeconds);

        return $bucket['count'] <= $limit;
    }

    private function allowWithMemory(string $key, int $limit, int $decaySeconds): bool
    {
        $now = now()->timestamp;
        $bucket = $this->memoryBuckets[$key] ?? null;

        if (! $bucket || $bucket['expires_at'] <= $now) {
            $bucket = [
                'count' => 0,
                'expires_at' => $now + $decaySeconds,
            ];
        }

        $bucket['count']++;
        $this->memoryBuckets[$key] = $bucket;

        return $bucket['count'] <= $limit;
    }
}
