<?php

namespace App\Learning\LaravelInterview\Support;

use RuntimeException;

class PhpProcessInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $parentCounter = 0;
        $children = [];

        if (function_exists('pcntl_fork')) {
            for ($index = 1; $index <= 2; $index++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException('pcntl_fork failed.');
                }

                if ($pid === 0) {
                    $childCounter = $index;
                    exit($childCounter);
                }

                $children[] = [
                    'pid' => $pid,
                    'role' => "child-{$index}",
                ];
            }

            foreach ($children as $key => $child) {
                pcntl_waitpid($child['pid'], $status);

                $children[$key]['exit_code'] = pcntl_wexitstatus($status);
            }
        }

        return [
            'runtime' => [
                'php_version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'parent_pid' => getmypid(),
                'pcntl_available' => function_exists('pcntl_fork'),
            ],
            'process_isolation' => [
                'parent_counter_after_children' => $parentCounter,
                'children' => $children,
                'point' => 'fork 后子进程拥有父进程内存快照，子进程修改变量不会影响父进程。',
            ],
            'threading_note' => 'PHP-FPM 常见模型是多进程 worker；PHP 不是典型多线程 Web 运行模型，线程扩展不适合作为 Laravel-FPM 常规方案。',
            'octane_note' => 'Octane/Swoole/RoadRunner 是常驻内存模型，必须避免请求态数据泄漏到单例或静态变量。',
            'interview_points' => [
                'PHP-FPM master 负责管理 worker，worker 处理请求。',
                '普通 FPM 请求结束后内存释放，常驻进程不会自动重置所有状态。',
                '队列 worker、定时任务和 Octane 都要关注内存泄漏、信号处理和平滑重启。',
                '进程间不共享内存，通信通常依赖 Redis、数据库、MQ、文件或 socket。',
                '协程不是线程，适合 IO 并发，但不能自动解决 CPU 密集任务。',
            ],
        ];
    }
}
