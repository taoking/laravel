<?php

namespace App\Modules\Semantic\Services;

use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Models\MetricDependency;
use Illuminate\Validation\ValidationException;

class MetricDependencyService
{
    public function __construct(private readonly MetricFormulaParser $formulaParser) {}

    public function sync(Metric $metric): void
    {
        $dependencies = $this->dependenciesFor($metric);
        $metricIds = collect($dependencies)
            ->where('dependency_type', 'metric')
            ->pluck('depends_on_metric_id')
            ->filter()
            ->values()
            ->all();

        $this->assertNoCycle($metric, $metricIds);

        $metric->dependencies()->delete();

        foreach ($dependencies as $dependency) {
            MetricDependency::query()->create([
                'metric_id' => $metric->id,
                ...$dependency,
            ]);
        }
    }

    /**
     * @return list<array{depends_on_metric_id?: int|null, depends_on_field_name?: string|null, dependency_type: string}>
     */
    private function dependenciesFor(Metric $metric): array
    {
        if ($metric->metric_type === 'base') {
            return $metric->source_field !== null
                ? [[
                    'depends_on_metric_id' => null,
                    'depends_on_field_name' => $metric->source_field,
                    'dependency_type' => 'field',
                ]]
                : [];
        }

        $codes = $metric->formula !== null ? $this->formulaParser->dependencies($metric->formula) : [];

        return Metric::query()
            ->where('dataset_id', $metric->dataset_id)
            ->whereIn('code', $codes)
            ->get()
            ->map(fn (Metric $dependency): array => [
                'depends_on_metric_id' => (int) $dependency->id,
                'depends_on_field_name' => null,
                'dependency_type' => 'metric',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $newDependencyIds
     */
    public function assertNoCycle(Metric $metric, array $newDependencyIds): void
    {
        foreach ($newDependencyIds as $dependencyId) {
            if ((int) $dependencyId === (int) $metric->id || $this->dependsOn($dependencyId, (int) $metric->id, [])) {
                throw ValidationException::withMessages([
                    'formula' => ['Metric dependencies cannot contain a cycle.'],
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $seen
     */
    private function dependsOn(int $metricId, int $targetMetricId, array $seen): bool
    {
        if (in_array($metricId, $seen, true)) {
            return false;
        }

        $seen[] = $metricId;
        $dependencies = MetricDependency::query()
            ->where('metric_id', $metricId)
            ->where('dependency_type', 'metric')
            ->pluck('depends_on_metric_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($dependencies as $dependencyId) {
            if ($dependencyId === $targetMetricId || $this->dependsOn($dependencyId, $targetMetricId, $seen)) {
                return true;
            }
        }

        return false;
    }
}
