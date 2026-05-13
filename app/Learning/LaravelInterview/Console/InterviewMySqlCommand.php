<?php

namespace App\Learning\LaravelInterview\Console;

use App\Learning\LaravelInterview\Support\MySqlInterviewExamples;
use Illuminate\Console\Command;

class InterviewMySqlCommand extends Command
{
    protected $signature = 'interview:mysql
        {--json : Output the complete payload as JSON}';

    protected $description = '演示 MySQL 索引、EXPLAIN、事务锁、唯一约束幂等和 keyset 分页。';

    public function handle(MySqlInterviewExamples $examples): int
    {
        $payload = $examples->run();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('MySQL interview examples');
        $this->line('Table: '.$payload['table']);

        $firstExplain = $payload['explain'][0] ?? [];

        $this->table(['Topic', 'Result'], [
            ['Isolation', $payload['transaction_isolation']],
            ['EXPLAIN key', (string) ($firstExplain['key'] ?? 'null')],
            ['EXPLAIN type', (string) ($firstExplain['type'] ?? 'null')],
            ['EXPLAIN extra', (string) ($firstExplain['Extra'] ?? '')],
            ['Duplicate Insert', 'affected='.$payload['unique_idempotency']['duplicate_insert_count']],
            ['Lock', json_encode($payload['transaction_lock'], JSON_UNESCAPED_UNICODE)],
            ['Keyset Page', json_encode($payload['keyset_pagination']['rows'], JSON_UNESCAPED_UNICODE)],
        ]);

        foreach ($payload['interview_points'] as $point) {
            $this->line('- '.$point);
        }

        return self::SUCCESS;
    }
}
