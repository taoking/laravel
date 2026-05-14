<?php

namespace App\Http\Controllers\Api\V1\Metrics;

use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Queries\MetricQuery;
use App\Domains\Metrics\Services\HotMetricService;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Metrics\MetricUpsertRequest;
use App\Http\Resources\MetricResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function store(MetricUpsertRequest $request): JsonResponse
    {
        $metric = Metric::query()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        AuditEvent::dispatch('metric.created', Metric::class, $metric->id, [
            'code' => $metric->code,
        ], $request);

        return ApiResponse::success([
            'metric' => MetricResource::make($metric->load(['category', 'latestValue']))->resolve(),
        ], 'Created.', 201);
    }

    public function show(Metric $metric, HotMetricService $hotMetrics): JsonResponse
    {
        $hotMetrics->record($metric);

        $metricData = Cache::remember("metrics:detail:{$metric->id}", 600, function () use ($metric) {
            return MetricResource::make($metric->load(['category', 'latestValue.region', 'latestValue.frequency']))->resolve();
        });

        return ApiResponse::success([
            'metric' => $metricData,
        ]);
    }

    public function update(MetricUpsertRequest $request, Metric $metric): JsonResponse
    {
        $metric->fill($request->validated())->save();
        Cache::forget("metrics:detail:{$metric->id}");

        AuditEvent::dispatch('metric.updated', Metric::class, $metric->id, [
            'code' => $metric->code,
        ], $request);

        return ApiResponse::success([
            'metric' => MetricResource::make($metric->refresh()->load(['category', 'latestValue.region', 'latestValue.frequency']))->resolve(),
        ]);
    }

    public function destroy(Request $request, Metric $metric): JsonResponse
    {
        $metricId = $metric->id;
        $metricCode = $metric->code;

        $metric->delete();
        Cache::forget("metrics:detail:{$metricId}");

        AuditEvent::dispatch('metric.deleted', Metric::class, $metricId, [
            'code' => $metricCode,
        ], $request);

        return ApiResponse::success(message: 'Deleted.');
    }
}
