<?php

namespace App\Console\Commands;

use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricValue;
use Illuminate\Console\Command;

class ComputeMetricDailySummary extends Command
{
    protected $signature = 'metrics:daily-summary';

    protected $description = 'Compute the daily metric summary for operations checks.';

    public function handle(): int
    {
        $this->info('Metric summary');
        $this->line('Metrics: '.Metric::query()->count());
        $this->line('Metric values: '.MetricValue::query()->count());

        return self::SUCCESS;
    }
}
