<?php

namespace App\Modules\Semantic\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Semantic\Models\MetricCategory;
use App\Modules\Semantic\Requests\StoreMetricCategoryRequest;
use App\Modules\Semantic\Requests\UpdateMetricCategoryRequest;
use App\Modules\Semantic\Resources\MetricCategoryResource;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 100), 1), 100);
        $categories = MetricCategory::query()
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($pageSize);

        return ApiResponse::paginated(
            $categories,
            MetricCategoryResource::collection($categories->items())->resolve($request),
        );
    }

    public function store(StoreMetricCategoryRequest $request): JsonResponse
    {
        $category = MetricCategory::query()->create($request->validated());

        return ApiResponse::created((new MetricCategoryResource($category))->resolve($request));
    }

    public function show(MetricCategory $metricCategory, Request $request): JsonResponse
    {
        return ApiResponse::success((new MetricCategoryResource($metricCategory))->resolve($request));
    }

    public function update(UpdateMetricCategoryRequest $request, MetricCategory $metricCategory): JsonResponse
    {
        $metricCategory->fill($request->validated());
        $metricCategory->save();

        return ApiResponse::success((new MetricCategoryResource($metricCategory->refresh()))->resolve($request));
    }

    public function destroy(MetricCategory $metricCategory): JsonResponse
    {
        $metricCategory->delete();

        return ApiResponse::noContent();
    }
}
