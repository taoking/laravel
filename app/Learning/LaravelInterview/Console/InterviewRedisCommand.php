<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\RedisInterviewExamples;
use Illuminate\Console\Command;

class InterviewRedisCommand extends Command
{
    protected $signature = 'interview:redis
        {--key=demo : Redis key suffix}
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 Redis 数据结构、Pipeline、Lua、分布式锁和滑动窗口限流。';

    public function handle(RedisInterviewExamples $examples): int
    {
        $payload = $examples->run((string) $this->option('key'));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Redis interview examples');
        $this->line('Key prefix: '.$payload['key_prefix']);

        $this->table(['Topic', 'Result'], [
            ['String + TTL', json_encode($payload['data_structures']['string_with_ttl'], JSON_UNESCAPED_UNICODE)],
            ['Hash', json_encode($payload['data_structures']['hash'], JSON_UNESCAPED_UNICODE)],
            ['List', implode(' -> ', $payload['data_structures']['list'])],
            ['Set', implode(', ', $payload['data_structures']['set'])],
            ['Sorted Set', json_encode($payload['data_structures']['sorted_set'], JSON_UNESCAPED_UNICODE)],
            ['Stream', json_encode($payload['data_structures']['stream'], JSON_UNESCAPED_UNICODE)],
            ['Pipeline', 'counter='.$payload['pipeline']['final_counter']],
            ['Lua', 'counter='.$payload['lua_atomic_increment']['value']],
            ['Lock', $payload['cache_lock']['acquired'] ? 'acquired and released' : 'not acquired'],
            ['Rate Limit', json_encode($payload['sliding_window_limiter']['attempts'], JSON_UNESCAPED_UNICODE)],
        ]);

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
