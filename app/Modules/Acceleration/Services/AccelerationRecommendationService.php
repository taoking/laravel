<?php

namespace App\Modules\Acceleration\Services;

use App\Models\User;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationRecommendation;
use App\Modules\Acceleration\Models\AccelerationTask;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccelerationRecommendationService
{
    public function __construct(
        private readonly QueryLogAnalysisService $analysisService,
        private readonly AccelerationRecommendationBuilder $builder,
        private readonly AggregateDefinitionService $aggregateDefinitionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateRecommendations(int $days = 7, ?int $datasetId = null, bool $dryRun = false): array
    {
        if (! (bool) config('bi_acceleration.recommendation.enabled', true)) {
            return [
                ...$this->analysisService->summary($days, $datasetId),
                'generated_count' => 0,
                'expired_count' => 0,
                'dry_run' => $dryRun,
                'enabled' => false,
                'recommendations' => [],
            ];
        }

        $summary = $this->analysisService->summary($days, $datasetId);
        $expiredCount = $dryRun ? 0 : $this->expireOldRecommendations();
        $candidates = [
            ...$this->aggregateCandidates($this->analysisService->analyzeSlowQueries($days, $datasetId), 'Slow chart query pattern.'),
            ...$this->aggregateCandidates($this->analysisService->analyzeFrequentCharts($days, $datasetId), 'High frequency chart query pattern.'),
            ...$this->fallbackCandidates($this->analysisService->analyzeFallbackReasons($days, $datasetId)),
            ...$this->rawDetailCandidates($this->analysisService->analyzeRawDatasets($days, $datasetId)),
        ];
        $created = [];

        foreach ($candidates as $candidate) {
            if ($this->isDuplicate($candidate) || $this->isCoveredByActiveAggregate($candidate)) {
                continue;
            }

            if ($dryRun) {
                $created[] = $candidate;

                continue;
            }

            $created[] = AccelerationRecommendation::query()->create($candidate)->toArray();
        }

        return [
            ...$summary,
            'generated_count' => count($created),
            'expired_count' => $expiredCount,
            'dry_run' => $dryRun,
            'enabled' => true,
            'recommendations' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listRecommendations(array $filters = [], int $pageSize = 20): LengthAwarePaginator
    {
        return AccelerationRecommendation::query()
            ->with(['dataset', 'chart', 'createdAggregateDefinition'])
            ->when(isset($filters['dataset_id']), fn ($query) => $query->where('dataset_id', $filters['dataset_id']))
            ->when(isset($filters['chart_id']), fn ($query) => $query->where('chart_id', $filters['chart_id']))
            ->when(isset($filters['recommendation_type']), fn ($query) => $query->where('recommendation_type', $filters['recommendation_type']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['priority']), fn ($query) => $query->where('priority', $filters['priority']))
            ->orderByDesc('estimated_benefit_score')
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @return array{recommendation: AccelerationRecommendation, aggregate_definition: AccelerationAggregateDefinition, task: AccelerationTask|null}
     */
    public function acceptRecommendation(AccelerationRecommendation $recommendation, User $user, bool $build = true): array
    {
        if ($recommendation->recommendation_type !== 'aggregate_table') {
            throw ValidationException::withMessages([
                'recommendation_type' => ['Only aggregate_table recommendations can be accepted automatically.'],
            ]);
        }

        if (! in_array($recommendation->status, ['pending', 'accepted'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Recommendation [{$recommendation->status}] cannot be accepted."],
            ]);
        }

        try {
            return DB::transaction(function () use ($recommendation, $user, $build): array {
                $recommendation->forceFill([
                    'status' => 'accepted',
                    'accepted_by' => $user->id,
                    'accepted_at' => now(),
                ])->save();

                $dataset = Dataset::query()->with('fields')->findOrFail($recommendation->dataset_id);
                $definition = $this->findEquivalentDefinition($recommendation)
                    ?? $this->aggregateDefinitionService->create([
                        'dataset_id' => $dataset->id,
                        'name' => 'Recommended '.$dataset->name.' Aggregate',
                        'time_field' => $recommendation->time_field,
                        'time_grain' => $recommendation->time_grain ?: 'none',
                        'dimensions' => $recommendation->dimensions_json ?? [],
                        'metrics' => $recommendation->metrics_json ?? [],
                        'filters' => $recommendation->filters_json ?? [],
                    ], $user, $dataset);

                $task = null;

                if ($build && $definition->status !== 'active') {
                    $task = $this->aggregateDefinitionService->build($definition, $user);
                }

                $recommendation->forceFill([
                    'status' => 'created',
                    'created_profile_id' => $definition->aggregate_profile_id,
                    'created_aggregate_definition_id' => $definition->id,
                ])->save();

                return [
                    'recommendation' => $recommendation->refresh()->load(['createdAggregateDefinition', 'createdProfile']),
                    'aggregate_definition' => $definition->refresh()->load(['columns', 'aggregateProfile', 'detailProfile']),
                    'task' => $task,
                ];
            });
        } catch (Throwable $exception) {
            $recommendation->forceFill([
                'status' => 'accepted',
                'accepted_by' => $user->id,
                'accepted_at' => $recommendation->accepted_at ?? now(),
                'reason' => $recommendation->reason."\nAccept failed: ".$exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function rejectRecommendation(AccelerationRecommendation $recommendation, User $user, ?string $reason = null): AccelerationRecommendation
    {
        $recommendation->forceFill([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'reason' => $reason !== null && $reason !== '' ? $recommendation->reason."\nRejected: {$reason}" : $recommendation->reason,
        ])->save();

        return $recommendation->refresh();
    }

    public function expireOldRecommendations(): int
    {
        return AccelerationRecommendation::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function aggregateCandidates(array $groups, string $reasonPrefix): array
    {
        $candidates = [];
        $minCount = (int) config('bi_acceleration.recommendation.min_query_count', 5);

        foreach ($groups as $group) {
            $chart = $group['chart'] ?? null;

            if (! $chart instanceof Chart || $group['query_count'] < $minCount) {
                continue;
            }

            $draft = $this->builder->aggregateDraftFromChart($chart);

            if ($draft === null) {
                continue;
            }

            $score = (int) $group['total_duration_ms'];
            $candidates[] = [
                'dataset_id' => $draft['dataset_id'],
                'chart_id' => $draft['chart_id'],
                'dashboard_id' => $group['dashboard_id'] ?? null,
                'recommendation_type' => 'aggregate_table',
                'status' => 'pending',
                'priority' => $this->priority($score),
                'reason' => $reasonPrefix.' '.$this->durationReason($group),
                'dimensions_json' => $draft['dimensions'],
                'metrics_json' => $draft['metrics'],
                'filters_json' => $draft['filters'],
                'time_field' => $draft['time_field'],
                'time_grain' => $draft['time_grain'],
                'estimated_query_count' => $group['query_count'],
                'estimated_avg_duration_ms' => $group['avg_duration_ms'],
                'estimated_max_duration_ms' => $group['max_duration_ms'],
                'estimated_total_duration_ms' => $group['total_duration_ms'],
                'estimated_benefit_score' => $score,
                'source_query_log_ids_json' => $group['source_query_log_ids'],
                'expires_at' => now()->addDays(30),
            ];
        }

        return $candidates;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function fallbackCandidates(array $groups): array
    {
        return collect($groups)
            ->map(fn (array $group): array => [
                'dataset_id' => $group['dataset_id'],
                'chart_id' => $group['chart_id'],
                'dashboard_id' => $group['dashboard_id'],
                'recommendation_type' => 'detail_table',
                'status' => 'pending',
                'priority' => 'medium',
                'reason' => 'Frequent acceleration fallback: '.($group['reason_detail'] ?? 'unknown'),
                'dimensions_json' => [],
                'metrics_json' => [],
                'filters_json' => [],
                'time_field' => null,
                'time_grain' => 'none',
                'estimated_query_count' => $group['query_count'],
                'estimated_avg_duration_ms' => $group['avg_duration_ms'],
                'estimated_max_duration_ms' => $group['max_duration_ms'],
                'estimated_total_duration_ms' => $group['total_duration_ms'],
                'estimated_benefit_score' => $group['total_duration_ms'],
                'source_query_log_ids_json' => $group['source_query_log_ids'],
                'expires_at' => now()->addDays(30),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function rawDetailCandidates(array $groups): array
    {
        return collect($groups)
            ->reject(fn (array $group): bool => AccelerationProfile::query()
                ->where('dataset_id', $group['dataset_id'])
                ->where('mode', 'detail_table')
                ->where('status', 'active')
                ->exists())
            ->map(fn (array $group): array => [
                'dataset_id' => $group['dataset_id'],
                'chart_id' => $group['chart_id'],
                'dashboard_id' => $group['dashboard_id'],
                'recommendation_type' => 'detail_table',
                'status' => 'pending',
                'priority' => 'high',
                'reason' => 'Dataset has frequent slow raw queries and no active detail_table acceleration profile.',
                'dimensions_json' => [],
                'metrics_json' => [],
                'filters_json' => [],
                'time_field' => null,
                'time_grain' => 'none',
                'estimated_query_count' => $group['query_count'],
                'estimated_avg_duration_ms' => $group['avg_duration_ms'],
                'estimated_max_duration_ms' => $group['max_duration_ms'],
                'estimated_total_duration_ms' => $group['total_duration_ms'],
                'estimated_benefit_score' => $group['total_duration_ms'],
                'source_query_log_ids_json' => $group['source_query_log_ids'],
                'expires_at' => now()->addDays(30),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function isDuplicate(array $candidate): bool
    {
        return AccelerationRecommendation::query()
            ->where('dataset_id', $candidate['dataset_id'])
            ->where('recommendation_type', $candidate['recommendation_type'])
            ->whereIn('status', ['pending', 'accepted', 'created'])
            ->get()
            ->contains(fn (AccelerationRecommendation $recommendation): bool => $this->sameRecommendation($recommendation, $candidate));
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function isCoveredByActiveAggregate(array $candidate): bool
    {
        if ($candidate['recommendation_type'] !== 'aggregate_table') {
            return false;
        }

        $dataset = Dataset::query()->find($candidate['dataset_id']);

        return $dataset instanceof Dataset && $this->builder->activeAggregateCovers(
            $dataset,
            $candidate['dimensions_json'] ?? [],
            $candidate['metrics_json'] ?? [],
            $candidate['time_field'] ?? null,
            $candidate['time_grain'] ?? 'none',
        );
    }

    private function findEquivalentDefinition(AccelerationRecommendation $recommendation): ?AccelerationAggregateDefinition
    {
        return AccelerationAggregateDefinition::query()
            ->where('dataset_id', $recommendation->dataset_id)
            ->get()
            ->first(fn (AccelerationAggregateDefinition $definition): bool => $this->builder->sameDraft(
                $definition,
                $recommendation->dimensions_json ?? [],
                $recommendation->metrics_json ?? [],
                $recommendation->time_field,
                $recommendation->time_grain,
            ));
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function sameRecommendation(AccelerationRecommendation $recommendation, array $candidate): bool
    {
        if ($recommendation->recommendation_type !== $candidate['recommendation_type']) {
            return false;
        }

        if ($recommendation->recommendation_type !== 'aggregate_table') {
            return (int) $recommendation->chart_id === (int) ($candidate['chart_id'] ?? 0)
                && (int) $recommendation->dataset_id === (int) ($candidate['dataset_id'] ?? 0);
        }

        return $this->builder->normalizeDimensions($recommendation->dimensions_json ?? []) === $this->builder->normalizeDimensions($candidate['dimensions_json'] ?? [])
            && $this->builder->normalizeMetrics($recommendation->metrics_json ?? []) === $this->builder->normalizeMetrics($candidate['metrics_json'] ?? [])
            && ($recommendation->time_field ?? null) === ($candidate['time_field'] ?? null)
            && ($recommendation->time_grain ?? 'none') === ($candidate['time_grain'] ?? 'none');
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function durationReason(array $group): string
    {
        return "query_count={$group['query_count']}, avg={$group['avg_duration_ms']}ms, max={$group['max_duration_ms']}ms.";
    }

    private function priority(int $score): string
    {
        return match (true) {
            $score >= 300000 => 'high',
            $score >= 60000 => 'medium',
            default => 'low',
        };
    }
}
