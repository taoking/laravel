<?php

namespace App\Http\Controllers\Api\V1\Metrics;

use App\Domains\Messaging\KafkaProducer;
use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Queries\MetricQuery;
use App\Domains\Metrics\Services\HotMetricService;
use App\Domains\Metrics\Services\MetricCacheService;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Metrics\MetricUpsertRequest;
use App\Http\Resources\MetricResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricController extends Controller
{
    public function index(Request $request, MetricQuery $query): JsonResponse
    {
        $filters = $request->validate([
            'keyword' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'in:active,disabled'],
            'category_id' => ['sometimes', 'integer', 'exists:metric_categories,id'],
            'category_code' => ['sometimes', 'string', 'max:120'],
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'frequency_code' => ['sometimes', 'string', 'max:40'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort' => ['sometimes', 'string', 'max:40'],
            'direction' => ['sometimes', 'string', 'max:4'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $metrics = $query->paginate($filters);

        return ApiResponse::success(
            data: MetricResource::collection($metrics->getCollection())->resolve(),
            meta: [
                'current_page' => $metrics->currentPage(),
                'per_page' => $metrics->perPage(),
                'total' => $metrics->total(),
            ],
        );
    }

    public function store(MetricUpsertRequest $request, KafkaProducer $producer): JsonResponse
    {
        $metric = Metric::query()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);
        $this->publishMetricChanged($producer, $metric, 'created', array_keys($request->validated()), $request);

        AuditEvent::dispatch('metric.created', Metric::class, $metric->id, [
            'code' => $metric->code,
        ], $request);

        return ApiResponse::success([
            'metric' => MetricResource::make($metric->load(['category', 'latestValue']))->resolve(),
        ], 'Created.', 201);
    }

    public function show(Metric $metric, HotMetricService $hotMetrics, MetricCacheService $metricCache): JsonResponse
    {
        $hotMetrics->record($metric);

        return ApiResponse::success([
            'metric' => $metricCache->detail($metric),
        ]);
    }

    public function update(MetricUpsertRequest $request, Metric $metric, KafkaProducer $producer, MetricCacheService $metricCache): JsonResponse
    {
        $validated = $request->validated();
        $metric->fill($validated)->save();
        $metricCache->forget((int) $metric->id);
        $this->publishMetricChanged($producer, $metric, 'updated', array_keys($validated), $request);

        AuditEvent::dispatch('metric.updated', Metric::class, $metric->id, [
            'code' => $metric->code,
        ], $request);

        return ApiResponse::success([
            'metric' => MetricResource::make($metric->refresh()->load(['category', 'latestValue.region', 'latestValue.frequency']))->resolve(),
        ]);
    }

    public function destroy(Request $request, Metric $metric, KafkaProducer $producer, MetricCacheService $metricCache): JsonResponse
    {
        $metricId = $metric->id;
        $metricCode = $metric->code;

        $metric->delete();
        $metricCache->forget((int) $metricId);
        $this->publishMetricChanged($producer, $metric, 'deleted', ['deleted_at'], $request);

        AuditEvent::dispatch('metric.deleted', Metric::class, $metricId, [
            'code' => $metricCode,
        ], $request);

        return ApiResponse::success(message: 'Deleted.');
    }

    /**
     * @param  list<string>  $changedFields
     */
    private function publishMetricChanged(KafkaProducer $producer, Metric $metric, string $changeType, array $changedFields, Request $request): void
    {
        $producer->publishEvent('metric.data.changed', [
            'metric_id' => $metric->id,
            'change_id' => $metric->id.':'.$changeType.':'.now()->timestamp,
            'change_type' => $changeType,
            'changed_fields' => $changedFields,
            'occurred_at' => now()->toIso8601String(),
            'operator_id' => $request->user()?->id,
        ], traceId: (string) $request->attributes->get('trace_id'));
    }
}
