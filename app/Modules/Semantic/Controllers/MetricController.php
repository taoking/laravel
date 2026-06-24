<?php

namespace App\Modules\Semantic\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Requests\StoreMetricRequest;
use App\Modules\Semantic\Requests\UpdateMetricRequest;
use App\Modules\Semantic\Requests\ValidateFormulaRequest;
use App\Modules\Semantic\Resources\MetricDependencyResource;
use App\Modules\Semantic\Resources\MetricResource;
use App\Modules\Semantic\Resources\MetricUsageResource;
use App\Modules\Semantic\Resources\MetricVersionResource;
use App\Modules\Semantic\Services\MetricFormulaParser;
use App\Modules\Semantic\Services\MetricImpactAnalysisService;
use App\Modules\Semantic\Services\MetricService;
use App\Modules\Semantic\Services\SemanticLayerAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricController extends Controller
{
    public function index(Request $request, MetricService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $metrics = $service->paginate($request->only(['dataset_id', 'category_id', 'status']), $pageSize, $request->user());

        return ApiResponse::paginated(
            $metrics,
            MetricResource::collection($metrics->items())->resolve($request),
        );
    }

    public function store(StoreMetricRequest $request, MetricService $service): JsonResponse
    {
        $metric = $service->create($request->validated(), $request->user());

        return ApiResponse::created((new MetricResource($metric))->resolve($request));
    }

    public function show(Metric $metric, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewMetric($metric, $request->user());
        $metric->load(['category', 'dataset', 'dependencies.dependsOnMetric']);

        return ApiResponse::success((new MetricResource($metric))->resolve($request));
    }

    public function update(UpdateMetricRequest $request, Metric $metric, MetricService $service): JsonResponse
    {
        $metric = $service->update($metric, $request->validated(), $request->user());

        return ApiResponse::success((new MetricResource($metric))->resolve($request));
    }

    public function destroy(Metric $metric, MetricService $service, Request $request): JsonResponse
    {
        $service->delete($metric, $request->user(), $request->boolean('force'));

        return ApiResponse::noContent();
    }

    public function activate(Metric $metric, MetricService $service, Request $request): JsonResponse
    {
        return ApiResponse::success((new MetricResource($service->transition($metric, 'active', $request->user())))->resolve($request));
    }

    public function deprecate(Metric $metric, MetricService $service, Request $request): JsonResponse
    {
        return ApiResponse::success((new MetricResource($service->transition($metric, 'deprecated', $request->user())))->resolve($request));
    }

    public function archive(Metric $metric, MetricService $service, Request $request): JsonResponse
    {
        return ApiResponse::success((new MetricResource($service->transition($metric, 'archived', $request->user())))->resolve($request));
    }

    public function versions(Metric $metric, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewMetric($metric, $request->user());

        return ApiResponse::success(MetricVersionResource::collection($metric->versions)->resolve($request));
    }

    public function dependencies(Metric $metric, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewMetric($metric, $request->user());
        $dependencies = $metric->dependencies()->with('dependsOnMetric')->get();

        return ApiResponse::success(MetricDependencyResource::collection($dependencies)->resolve($request));
    }

    public function usages(Metric $metric, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewMetric($metric, $request->user());

        return ApiResponse::success(MetricUsageResource::collection($metric->usages()->latest('id')->get())->resolve($request));
    }

    public function impact(Metric $metric, MetricImpactAnalysisService $service, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewMetric($metric, $request->user());

        return ApiResponse::success($service->analyze($metric));
    }

    public function validateFormula(ValidateFormulaRequest $request, MetricFormulaParser $parser, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $payload = $request->validated();
        $authorizer->assertCanManageDataset(Dataset::query()->findOrFail($payload['dataset_id']), $request->user());
        $codes = Metric::query()
            ->where('dataset_id', $payload['dataset_id'])
            ->where('status', 'active')
            ->pluck('code')
            ->all();

        try {
            return ApiResponse::success($parser->validate($payload['formula'], $codes));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::success([
                'valid' => false,
                'dependencies' => [],
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
