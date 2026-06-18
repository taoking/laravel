<?php

namespace App\Modules\Monitor\Services;

use App\Modules\Export\Models\ExportTask;
use App\Modules\Import\Models\ImportTask;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class MetricsService
{
    public function prometheus(): string
    {
        return implode("\n", [
            '# HELP bi_query_total Total BI queries.',
            '# TYPE bi_query_total counter',
            'bi_query_total '.$this->queryTotal(),
            '# HELP bi_query_failed_total Total failed BI queries.',
            '# TYPE bi_query_failed_total counter',
            'bi_query_failed_total '.$this->queryFailedTotal(),
            '# HELP bi_query_duration_ms Total query duration in milliseconds.',
            '# TYPE bi_query_duration_ms counter',
            'bi_query_duration_ms '.$this->queryDurationMs(),
            '# HELP bi_query_cache_hit_total Total cached BI query hits.',
            '# TYPE bi_query_cache_hit_total counter',
            'bi_query_cache_hit_total '.$this->queryCacheHitTotal(),
            '# HELP bi_import_task_total Total import tasks.',
            '# TYPE bi_import_task_total counter',
            'bi_import_task_total '.ImportTask::query()->count(),
            '# HELP bi_export_task_total Total export tasks.',
            '# TYPE bi_export_task_total counter',
            'bi_export_task_total '.ExportTask::query()->count(),
            '# HELP bi_queue_pending_jobs Pending queue jobs.',
            '# TYPE bi_queue_pending_jobs gauge',
            'bi_queue_pending_jobs '.$this->pendingJobs(),
            '',
        ]);
    }

    private function queryTotal(): int
    {
        return QueryLog::query()->count();
    }

    private function queryFailedTotal(): int
    {
        return QueryLog::query()->where('status', 'failed')->count();
    }

    private function queryDurationMs(): int
    {
        return (int) QueryLog::query()->sum('elapsed_ms');
    }

    private function queryCacheHitTotal(): int
    {
        return QueryLog::query()->where('cached', true)->count();
    }

    private function pendingJobs(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        try {
            $connection = config('queue.connections.database.connection') ?: config('database.default');
            $table = config('queue.connections.database.table', 'jobs');

            return (int) DB::connection($connection)->table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
