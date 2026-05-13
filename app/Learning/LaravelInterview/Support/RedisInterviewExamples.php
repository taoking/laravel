<?php

namespace App\Learning\LaravelInterview\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(string $suffix = 'demo'): array
    {
        $safeSuffix = Str::slug($suffix) ?: 'demo';
        $prefix = "interview:redis:{$safeSuffix}";
        $redis = Redis::connection();

        $keys = [
            "{$prefix}:string",
            "{$prefix}:hash",
            "{$prefix}:list",
            "{$prefix}:set",
            "{$prefix}:zset",
            "{$prefix}:stream",
            "{$prefix}:pipeline",
            "{$prefix}:lua",
            "{$prefix}:rate:login",
        ];

        $redis->del($keys);

        $redis->setex("{$prefix}:string", 300, json_encode([
            'order_no' => 'ORDER-DEMO',
            'status' => 'paid',
        ], JSON_UNESCAPED_UNICODE));

        $redis->hset("{$prefix}:hash", 'name', 'Ada');
        $redis->hset("{$prefix}:hash", 'role', 'interviewer');
        $redis->hset("{$prefix}:hash", 'level', 'senior');

        $redis->rpush("{$prefix}:list", 'created', 'paid', 'shipped');

        $redis->sadd("{$prefix}:set", 'php', 'laravel', 'redis');

        $redis->zadd("{$prefix}:zset", 98, 'cache-design');
        $redis->zadd("{$prefix}:zset", 95, 'distributed-lock');
        $redis->zadd("{$prefix}:zset", 90, 'rate-limit');

        $streamId = $redis->xadd("{$prefix}:stream", '*', [
            'event' => 'order_paid',
            'order_no' => 'ORDER-DEMO',
        ]);

        $pipelineResults = $redis->pipeline(function ($pipe) use ($prefix): void {
            $pipe->incr("{$prefix}:pipeline");
            $pipe->incr("{$prefix}:pipeline");
            $pipe->expire("{$prefix}:pipeline", 300);
        });

        $luaValue = $redis->eval(
            "return redis.call('INCR', KEYS[1])",
            1,
            "{$prefix}:lua",
        );
        $redis->expire("{$prefix}:lua", 300);

        $lockAcquired = Cache::store('redis')
            ->lock("{$prefix}:lock", 5)
            ->get(fn (): bool => true);

        $rateLimit = $this->slidingWindowRateLimit("{$prefix}:rate:login", 3, 60);

        $setMembers = $redis->smembers("{$prefix}:set");
        sort($setMembers);

        return [
            'key_prefix' => $prefix,
            'data_structures' => [
                'string_with_ttl' => [
                    'value' => json_decode((string) $redis->get("{$prefix}:string"), true),
                    'ttl_seconds' => $redis->ttl("{$prefix}:string"),
                ],
                'hash' => $redis->hgetall("{$prefix}:hash"),
                'list' => $redis->lrange("{$prefix}:list", 0, -1),
                'set' => $setMembers,
                'sorted_set' => $redis->zrevrange("{$prefix}:zset", 0, -1, true),
                'stream' => [
                    'last_id' => $streamId,
                    'length' => $redis->xlen("{$prefix}:stream"),
                ],
            ],
            'pipeline' => [
                'results' => $pipelineResults,
                'final_counter' => (int) $redis->get("{$prefix}:pipeline"),
            ],
            'lua_atomic_increment' => [
                'value' => (int) $luaValue,
                'point' => 'Lua 脚本在 Redis 内单线程执行，适合封装原子读改写。',
            ],
            'cache_lock' => [
                'acquired' => (bool) $lockAcquired,
                'point' => '分布式锁必须设置过期时间，并让业务逻辑具备幂等兜底。',
            ],
            'sliding_window_limiter' => $rateLimit,
            'interview_points' => [
                'String 适合缓存标量或 JSON，必须关注 TTL 和穿透保护。',
                'Hash 适合对象局部字段读写，但大 Hash 会带来迁移和阻塞风险。',
                'List 常用于简单队列，但可靠队列应考虑 Stream 或专业 MQ。',
                'Set/ZSet 适合去重、标签、排行榜、延迟任务等场景。',
                'Pipeline 减少网络往返，不保证事务原子性。',
                'Lua 保证脚本内原子性，但脚本不能执行过久。',
                'Redis 锁只是并发保护手段，不能替代数据库唯一约束和业务幂等。',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slidingWindowRateLimit(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $redis = Redis::connection();
        $windowMilliseconds = $windowSeconds * 1000;
        $attempts = [];

        for ($attempt = 1; $attempt <= $maxAttempts + 1; $attempt++) {
            $now = (int) floor(microtime(true) * 1000);

            $redis->zremrangebyscore($key, '-inf', $now - $windowMilliseconds);

            $currentAttempts = (int) $redis->zcard($key);
            $allowed = $currentAttempts < $maxAttempts;

            if ($allowed) {
                $redis->zadd($key, $now, (string) Str::uuid());
                $redis->expire($key, $windowSeconds);
            }

            $attempts[] = [
                'attempt' => $attempt,
                'allowed' => $allowed,
                'remaining' => max(0, $maxAttempts - (int) $redis->zcard($key)),
            ];
        }

        return [
            'algorithm' => 'sorted-set sliding window',
            'max_attempts' => $maxAttempts,
            'window_seconds' => $windowSeconds,
            'attempts' => $attempts,
        ];
    }
}
