<?php

namespace App\Console\Commands;

use App\Domains\Metrics\Services\RedisRateLimiterService;
use Illuminate\Console\Command;

class RedisCacheLabCommand extends Command
{
    protected $signature = 'redis:cache-lab
        {action=lua-rate-limit : Lab action}
        {--key=metric-query-demo : Rate limit key}
        {--limit=3 : Max allowed hits}
        {--decay=60 : Decay seconds}';

    protected $description = 'Run Redis cache reliability labs for senior interview practice.';

    public function handle(RedisRateLimiterService $limiter): int
    {
        $action = (string) $this->argument('action');

        if ($action !== 'lua-rate-limit') {
            $this->error("Unsupported Redis lab action [{$action}].");

            return self::FAILURE;
        }

        $allowed = $limiter->allow(
            key: (string) $this->option('key'),
            limit: (int) $this->option('limit'),
            decaySeconds: (int) $this->option('decay'),
        );

        $this->line($allowed ? 'allowed' : 'blocked');

        return self::SUCCESS;
    }
}
