<?php

namespace App\Console\Commands;

use App\Domains\Metrics\Models\Frequency;
use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricCategory;
use App\Domains\Metrics\Models\MetricValue;
use App\Domains\Metrics\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeedMetricDatasetCommand extends Command
{
    protected $signature = 'metrics:seed-large-dataset
        {--rows=100000 : Number of metric value rows to generate}
        {--metrics=100 : Number of metric definitions to distribute rows across}
        {--batch=1000 : Batch size for upsert}
        {--dry-run : Show the plan without writing rows}';

    protected $description = 'Seed a large metric value dataset for query and pagination performance labs.';

    public function handle(): int
    {
        $rows = max(1, (int) $this->option('rows'));
        $metricCount = max(1, (int) $this->option('metrics'));
        $batchSize = max(1, (int) $this->option('batch'));

        if ($this->option('dry-run')) {
            $this->info("Would generate {$rows} metric value rows across {$metricCount} metrics in batches of {$batchSize}.");

            return self::SUCCESS;
        }

        $category = MetricCategory::query()->updateOrCreate(
            ['code' => 'performance_lab'],
            ['name' => 'Performance Lab', 'description' => 'Generated metrics for query performance labs.', 'is_active' => true],
        );
        $region = Region::query()->updateOrCreate(
            ['code' => 'PERF-CN'],
            ['name' => 'Performance Region', 'level' => 'lab', 'is_active' => true],
        );
        $frequency = Frequency::query()->updateOrCreate(
            ['code' => 'perf_daily'],
            ['name' => 'Performance Daily', 'is_active' => true],
        );
        $metrics = $this->metrics($category->id, $metricCount);
        $startDate = Carbon::parse('2024-01-01');
        $written = 0;

        for ($offset = 0; $offset < $rows; $offset += $batchSize) {
            $payload = [];
            $upper = min($rows, $offset + $batchSize);

            for ($index = $offset; $index < $upper; $index++) {
                $metric = $metrics[$index % $metrics->count()];
                $periodDate = $startDate->copy()->addDays(intdiv($index, $metrics->count()));
                $now = now();

                $payload[] = [
                    'metric_id' => $metric->id,
                    'region_id' => $region->id,
                    'frequency_id' => $frequency->id,
                    'period_date' => $periodDate->toDateString(),
                    'period_label' => $periodDate->toDateString(),
                    'value' => ($index % 10000) / 10,
                    'source' => 'large-dataset-lab',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            MetricValue::query()->upsert(
                $payload,
                ['metric_id', 'region_id', 'frequency_id', 'period_date'],
                ['period_label', 'value', 'source', 'updated_at'],
            );

            $written += count($payload);
        }

        $this->info("Generated {$written} metric value rows for performance labs.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Metric>
     */
    private function metrics(int $categoryId, int $metricCount): Collection
    {
        return collect(range(1, $metricCount))->map(function (int $number) use ($categoryId): Metric {
            $code = sprintf('perf_metric_%04d', $number);

            return Metric::query()->updateOrCreate(
                ['code' => $code],
                [
                    'metric_category_id' => $categoryId,
                    'name' => sprintf('Performance Metric %04d', $number),
                    'unit' => 'count',
                    'status' => 'active',
                    'description' => 'Generated metric for performance lab.',
                ],
            );
        });
    }
}
