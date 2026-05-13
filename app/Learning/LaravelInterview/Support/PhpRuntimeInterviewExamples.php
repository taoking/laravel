<?php

namespace App\Learning\LaravelInterview\Support;

class PhpRuntimeInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'runtime' => [
                'php_version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'loaded_extensions' => [
                    'opcache' => extension_loaded('Zend OPcache'),
                    'pcntl' => extension_loaded('pcntl'),
                    'redis' => extension_loaded('redis'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                ],
            ],
            'composer_autoload' => [
                'autoload_file_exists' => file_exists(base_path('vendor/autoload.php')),
                'composer_json_exists' => file_exists(base_path('composer.json')),
                'psr4_app_namespace' => 'App\\ => app/',
                'optimized_autoload_command' => 'composer dump-autoload -o',
                'interview_point' => 'Composer 通过 autoload 规则把命名空间映射到文件路径，生产环境常用 optimized autoload 降低类查找成本。',
            ],
            'opcache' => [
                'enabled' => (bool) ini_get('opcache.enable'),
                'cli_enabled' => (bool) ini_get('opcache.enable_cli'),
                'memory_consumption' => ini_get('opcache.memory_consumption'),
                'validate_timestamps' => ini_get('opcache.validate_timestamps'),
                'revalidate_freq' => ini_get('opcache.revalidate_freq'),
                'interview_point' => 'OPcache 缓存 PHP 编译后的 opcode，减少每次请求解析和编译 PHP 文件的成本。',
            ],
            'jit' => [
                'jit' => ini_get('opcache.jit') ?: null,
                'jit_buffer_size' => ini_get('opcache.jit_buffer_size') ?: null,
                'interview_point' => 'JIT 对典型 IO 密集型 Laravel Web 请求收益通常有限，性能瓶颈更常见于 SQL、缓存、网络和序列化。',
            ],
            'gc' => [
                'enabled' => gc_enabled(),
                'status' => function_exists('gc_status') ? gc_status() : null,
                'interview_point' => 'PHP 主要依赖引用计数释放内存，循环引用由 GC 处理；长进程要关注对象残留和内存增长。',
            ],
            'fpm_vs_cli' => [
                'fpm' => [
                    'request_lifecycle' => '请求级生命周期，worker 处理完请求后会清理大部分请求态状态。',
                    'risk' => 'worker 数量、慢请求、内存上限和 OPcache 配置影响吞吐。',
                ],
                'cli_worker' => [
                    'request_lifecycle' => 'queue:work、schedule:work、Octane 等是常驻进程。',
                    'risk' => '单例、静态变量、全局状态和大对象可能跨任务残留。',
                ],
            ],
            'interview_points' => [
                'Composer autoload 解决类加载，OPcache 解决 PHP 文件重复编译，两者不是一回事。',
                '生产部署如果开启 OPcache 且关闭时间戳校验，发布后要明确 reload 或重启 FPM。',
                'JIT 不是 Laravel 慢接口的通用解法，先用 trace 拆 SQL、Redis、HTTP、PHP CPU。',
                'GC 能处理循环引用，但常驻进程仍要主动控制批量大小和对象生命周期。',
                'CLI 和 FPM 可能加载不同 php.ini，排查问题时要分别确认版本、扩展和配置。',
            ],
        ];
    }
}
