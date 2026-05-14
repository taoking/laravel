<?php

namespace App\Console\Commands;

use App\Domains\Metrics\Models\Metric;
use Illuminate\Console\Command;

class MetricSeekPageCommand extends Command
{
    protected $signature = 'metrics:seek-page
        {--after-id=0 : Last seen metric id for descending seek pagination}
        {--limit=20 : Page size}
        {--status=active : Metric status filter}';

    protected $description = 'Show ID seek pagination for large metric lists.';

    public function handle(): int
    {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = max(1, min(100, (int) $this->option('limit')));

        $metrics = Metric::query()
            ->select(['id', 'code', 'name', 'status'])
            ->where('status', (string) $this->option('status'))
            ->when($afterId > 0, fn ($query) => $query->where('id', '<', $afterId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $this->table(['ID', 'Code', 'Name', 'Status'], $metrics->map(fn (Metric $metric): array => [
            $metric->id,
            $metric->code,
            $metric->name,
            $metric->status,
        ])->all());

        $nextAfterId = $metrics->last()?->id;
        $this->line('next_after_id: '.($nextAfterId ?: 'null'));

        return self::SUCCESS;
    }
}
