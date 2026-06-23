<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Requests\StoreAggregateDefinitionRequest;
use App\Modules\Acceleration\Requests\UpdateAggregateDefinitionRequest;
use App\Modules\Acceleration\Resources\AccelerationTaskResource;
use App\Modules\Acceleration\Resources\AggregateDefinitionResource;
use App\Modules\Acceleration\Services\AggregateDefinitionService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationAggregateController extends Controller
{
    public function index(Request $request, AggregateDefinitionService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $definitions = $service->paginate($pageSize);

        return ApiResponse::paginated(
            $definitions,
            AggregateDefinitionResource::collection($definitions->items())->resolve($request),
        );
    }

    public function store(StoreAggregateDefinitionRequest $request, AggregateDefinitionService $service): JsonResponse
    {
        $definition = $service->create($request->validated(), $request->user());

        return ApiResponse::created((new AggregateDefinitionResource($definition))->resolve($request));
    }

    public function show(AccelerationAggregateDefinition $aggregate, Request $request): JsonResponse
    {
        $aggregate->load(['dataset', 'detailProfile', 'aggregateProfile', 'columns']);

        return ApiResponse::success((new AggregateDefinitionResource($aggregate))->resolve($request));
    }

    public function update(UpdateAggregateDefinitionRequest $request, AccelerationAggregateDefinition $aggregate, AggregateDefinitionService $service): JsonResponse
    {
        $aggregate = $service->update($aggregate, $request->validated());

        return ApiResponse::success((new AggregateDefinitionResource($aggregate))->resolve($request));
    }

    public function destroy(AccelerationAggregateDefinition $aggregate, AggregateDefinitionService $service): JsonResponse
    {
        $service->delete($aggregate);

        return ApiResponse::noContent();
    }

    public function build(AccelerationAggregateDefinition $aggregate, Request $request, AggregateDefinitionService $service): JsonResponse
    {
        $task = $service->build($aggregate, $request->user());

        return ApiResponse::success((new AccelerationTaskResource($task))->resolve($request));
    }

    public function refresh(AccelerationAggregateDefinition $aggregate, Request $request, AggregateDefinitionService $service): JsonResponse
    {
        $task = $service->refresh($aggregate, $request->user());

        return ApiResponse::success((new AccelerationTaskResource($task))->resolve($request));
    }

    public function activate(AccelerationAggregateDefinition $aggregate, Request $request, AggregateDefinitionService $service): JsonResponse
    {
        $aggregate = $service->activate($aggregate);

        return ApiResponse::success((new AggregateDefinitionResource($aggregate))->resolve($request));
    }

    public function disable(AccelerationAggregateDefinition $aggregate, Request $request, AggregateDefinitionService $service): JsonResponse
    {
        $aggregate = $service->disable($aggregate);

        return ApiResponse::success((new AggregateDefinitionResource($aggregate))->resolve($request));
    }
}
