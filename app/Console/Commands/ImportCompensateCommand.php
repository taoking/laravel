<?php

namespace App\Console\Commands;

use App\Domains\Imports\Models\ImportTask;
use App\Jobs\ProcessMetricImportJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ImportCompensateCommand extends Command
{
    protected $signature = 'imports:compensate
        {--id=* : Specific import task IDs}
        {--status=failed : Comma separated task statuses when --id is omitted}
        {--older-than-minutes=0 : Only compensate tasks whose last failure or update is older than this value}
        {--limit=50 : Maximum number of tasks to compensate}
        {--dry-run : Show matched tasks without redispatching jobs}
        {--clear-failures : Delete row failure records before redispatching}';

    protected $description = 'Compensate failed metric import tasks and redispatch their queue jobs.';

    public function handle(): int
    {
        $tasks = $this->matchedTasks();

        if ($tasks->isEmpty()) {
            $this->info('No import tasks matched compensation criteria.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Status', 'Failure type', 'Attempts', 'Last failed at', 'Failures'], $tasks->map(fn (ImportTask $task): array => [
            $task->id,
            $task->status,
            $task->failure_type ?? '-',
            $task->attempts,
            $task->last_failed_at?->toDateTimeString() ?? '-',
            $task->getAttribute('failures_count') ?? 0,
        ])->all());

        if ($this->option('dry-run')) {
            $this->info('Dry run finished. No import tasks were changed.');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            if ($this->option('clear-failures')) {
                $task->failures()->delete();
            }

            $task->forceFill([
                'status' => 'pending',
                'total_rows' => 0,
                'success_rows' => 0,
                'failed_rows' => 0,
                'error_message' => null,
                'failure_type' => null,
                'last_failed_at' => null,
                'started_at' => null,
                'finished_at' => null,
                'compensated_at' => now(),
                'compensation_reason' => 'manual compensation via imports:compensate',
            ])->save();

            ProcessMetricImportJob::dispatch($task->id);
            $this->line("Redispatched import task [{$task->id}].");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, ImportTask>
     */
    private function matchedTasks(): Collection
    {
        $ids = $this->ids();
        $limit = max(1, (int) $this->option('limit'));

        $query = ImportTask::query()
            ->withCount('failures')
            ->orderBy('id')
            ->limit($limit);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('status', $this->statuses());
            $this->applyAgeFilter($query);
        }

        return $query->get();
    }

    /**
     * @return list<int>
     */
    private function ids(): array
    {
        $ids = $this->option('id');

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, $ids)));
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        $status = $this->option('status');
        $value = is_string($status) && $status !== '' ? $status : 'failed';

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @param  Builder<ImportTask>  $query
     */
    private function applyAgeFilter(Builder $query): void
    {
        $minutes = (int) $this->option('older-than-minutes');

        if ($minutes <= 0) {
            return;
        }

        $cutoff = now()->subMinutes($minutes);

        $query->where(function (Builder $query) use ($cutoff): void {
            $query->where(function (Builder $query) use ($cutoff): void {
                $query->whereNotNull('last_failed_at')
                    ->where('last_failed_at', '<=', $cutoff);
            })->orWhere(function (Builder $query) use ($cutoff): void {
                $query->whereNull('last_failed_at')
                    ->where('updated_at', '<=', $cutoff);
            });
        });
    }
}
