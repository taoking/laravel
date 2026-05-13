<?php

namespace App\Learning\LaravelInterview\Support;

use App\Learning\LaravelInterview\Jobs\ProcessInterviewOrderJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

class LaravelQueueInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $job = new ProcessInterviewOrderJob('ORDER-DEMO-1001');
        $reflection = new ReflectionClass($job);

        return [
            'runtime' => [
                'default_connection' => config('queue.default'),
                'failed_driver' => config('queue.failed.driver'),
                'failed_table' => config('queue.failed.table'),
                'redis_queue' => config('queue.connections.redis.queue'),
                'database_queue_table' => config('queue.connections.database.table'),
                'tables' => [
                    'jobs' => $this->hasTable('jobs'),
                    'job_batches' => $this->hasTable('job_batches'),
                    'failed_jobs' => $this->hasTable('failed_jobs'),
                ],
            ],
            'job' => [
                'class' => ProcessInterviewOrderJob::class,
                'implements_should_queue' => $reflection->implementsInterface(ShouldQueue::class),
                'implements_should_be_unique' => $reflection->implementsInterface(ShouldBeUnique::class),
                'queue' => $job->queue,
                'tries' => $job->tries,
                'timeout' => $job->timeout,
                'unique_for_seconds' => $job->uniqueFor,
                'unique_id' => $job->uniqueId(),
                'backoff_seconds' => $job->backoff(),
                'middleware' => array_map(fn (object $middleware): string => $middleware::class, $job->middleware()),
            ],
            'worker_commands' => [
                'start' => 'php artisan queue:work redis --queue=high,default,low --tries=3 --backoff=10 --timeout=60 --memory=128',
                'restart_after_deploy' => 'php artisan queue:restart',
                'failed_jobs' => 'php artisan queue:failed',
                'retry_all' => 'php artisan queue:retry all',
                'flush_failed' => 'php artisan queue:flush',
            ],
            'schedule_and_supervisor' => [
                'cron' => '* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1',
                'supervisor_program' => [
                    '[program:laravel-worker]',
                    'process_name=%(program_name)s_%(process_num)02d',
                    'command=php /path/to/app/artisan queue:work redis --sleep=3 --tries=3 --timeout=60 --memory=128',
                    'numprocs=4',
                    'autostart=true',
                    'autorestart=true',
                    'stopwaitsecs=3600',
                ],
            ],
            'reliability_patterns' => [
                'idempotency' => '业务幂等键 + 唯一索引 + 状态机，避免重复消费造成资损。',
                'retry' => '短暂故障用 backoff 重试，永久失败进入 failed_jobs 或死信/补偿流程。',
                'timeout' => 'job timeout 必须小于 queue retry_after，避免同一任务被并发执行。',
                'after_commit' => '事务内派发任务时考虑 after_commit，避免任务先于事务提交被消费。',
                'isolation' => '高优先级、低优先级、重 CPU、第三方慢接口任务应拆队列。',
            ],
            'interview_points' => [
                'Laravel Queue 是抽象层，Redis/database/SQS/RabbitMQ 是不同后端实现。',
                'queue:work 是常驻进程，部署后要 queue:restart，任务代码不会每次自动重新加载。',
                'tries、backoff、timeout、retry_after 必须一起设计，单独设置一个值容易产生重复执行。',
                'ShouldBeUnique 防止重复入队，WithoutOverlapping 防止同一业务 key 并发处理。',
                '失败任务要能告警、重试、丢弃或补偿，不能只堆在 failed_jobs 表里。',
            ],
        ];
    }

    private function hasTable(string $table): ?bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return null;
        }
    }
}
