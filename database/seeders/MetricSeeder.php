<?php

namespace Database\Seeders;

use App\Domains\Metrics\Models\Frequency;
use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricCategory;
use App\Domains\Metrics\Models\MetricValue;
use App\Domains\Metrics\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class MetricSeeder extends Seeder
{
    public function run(): void
    {
        $category = MetricCategory::query()->updateOrCreate(
            ['code' => 'business'],
            [
                'name' => 'Business',
                'description' => 'Business operating indicators.',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        $finance = MetricCategory::query()->updateOrCreate(
            ['code' => 'finance'],
            [
                'name' => 'Finance',
                'description' => 'Financial indicators.',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        $china = Region::query()->updateOrCreate(
            ['code' => 'CN'],
            [
                'name' => 'China',
                'level' => 'country',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        $shanghai = Region::query()->updateOrCreate(
            ['code' => 'CN-SH'],
            [
                'parent_id' => $china->id,
                'name' => 'Shanghai',
                'level' => 'city',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        $monthly = Frequency::query()->updateOrCreate(
            ['code' => 'monthly'],
            [
                'name' => 'Monthly',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        $daily = Frequency::query()->updateOrCreate(
            ['code' => 'daily'],
            [
                'name' => 'Daily',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $revenue = Metric::query()->updateOrCreate(
            ['code' => 'revenue_amount'],
            [
                'metric_category_id' => $finance->id,
                'created_by' => $admin?->id,
                'name' => 'Revenue Amount',
                'unit' => 'CNY',
                'status' => 'active',
                'description' => 'Revenue amount by period and region.',
                'sort_order' => 10,
            ],
        );

        $activeUsers = Metric::query()->updateOrCreate(
            ['code' => 'active_users'],
            [
                'metric_category_id' => $category->id,
                'created_by' => $admin?->id,
                'name' => 'Active Users',
                'unit' => 'users',
                'status' => 'active',
                'description' => 'Active users by period and region.',
                'sort_order' => 20,
            ],
        );

        $this->upsertMetricValue($revenue->id, $shanghai->id, $monthly->id, '2026-04-01', [
            'period_label' => '2026-04',
            'value' => 1250000.00,
            'source' => 'seed',
        ]);

        $this->upsertMetricValue($activeUsers->id, $china->id, $daily->id, '2026-05-13', [
            'period_label' => '2026-05-13',
            'value' => 23000,
            'source' => 'seed',
        ]);
    }

    private function upsertMetricValue(int $metricId, int $regionId, int $frequencyId, string $periodDate, array $attributes): void
    {
        $value = MetricValue::query()
            ->where([
                'metric_id' => $metricId,
                'region_id' => $regionId,
                'frequency_id' => $frequencyId,
            ])
            ->whereDate('period_date', $periodDate)
            ->first();

        if ($value) {
            $value->fill($attributes)->save();

            return;
        }

        MetricValue::query()->create($attributes + [
            'metric_id' => $metricId,
            'region_id' => $regionId,
            'frequency_id' => $frequencyId,
            'period_date' => $periodDate,
        ]);
    }
}
