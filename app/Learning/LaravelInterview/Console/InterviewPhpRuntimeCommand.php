<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\PhpRuntimeInterviewExamples;
use Illuminate\Console\Command;

class InterviewPhpRuntimeCommand extends Command
{
    protected $signature = 'interview:php-runtime
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 Composer autoload、OPcache、JIT、GC、FPM/CLI 差异等 PHP 运行机制面试点。';

    public function handle(PhpRuntimeInterviewExamples $examples): int
    {
        $payload = $examples->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('PHP runtime interview examples');

        $this->table(['Runtime', 'Value'], [
            ['PHP Version', $payload['runtime']['php_version']],
            ['SAPI', $payload['runtime']['sapi']],
            ['Memory Limit', $payload['runtime']['memory_limit']],
            ['OPcache Loaded', $payload['runtime']['loaded_extensions']['opcache'] ? 'yes' : 'no'],
            ['PDO MySQL Loaded', $payload['runtime']['loaded_extensions']['pdo_mysql'] ? 'yes' : 'no'],
        ]);

        $this->table(['Topic', 'Result'], [
            ['Composer Autoload', $payload['composer_autoload']['autoload_file_exists'] ? 'vendor/autoload.php exists' : 'missing'],
            ['OPcache', json_encode($payload['opcache'], JSON_UNESCAPED_UNICODE)],
            ['JIT', json_encode($payload['jit'], JSON_UNESCAPED_UNICODE)],
            ['GC', json_encode($payload['gc']['status'], JSON_UNESCAPED_UNICODE)],
        ]);

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
