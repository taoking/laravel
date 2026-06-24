<?php

namespace App\Modules\Semantic\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Requests\StoreDimensionRequest;
use App\Modules\Semantic\Requests\UpdateDimensionRequest;
use App\Modules\Semantic\Resources\DimensionResource;
use App\Modules\Semantic\Services\DimensionService;
use App\Modules\Semantic\Services\SemanticLayerAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DimensionController extends Controller
{
    public function index(Request $request, DimensionService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $dimensions = $service->paginate($request->only(['dataset_id', 'status']), $pageSize, $request->user());

        return ApiResponse::paginated(
            $dimensions,
            DimensionResource::collection($dimensions->items())->resolve($request),
        );
    }

    public function store(StoreDimensionRequest $request, DimensionService $service): JsonResponse
    {
        $dimension = $service->create($request->validated(), $request->user());

        return ApiResponse::created((new DimensionResource($dimension))->resolve($request));
    }

    public function show(Dimension $dimension, Request $request): JsonResponse
    {
        return ApiResponse::success((new DimensionResource($dimension))->resolve($request));
    }

    public function update(UpdateDimensionRequest $request, Dimension $dimension, DimensionService $service): JsonResponse
    {
        $dimension = $service->update($dimension, $request->validated(), $request->user());

        return ApiResponse::success((new DimensionResource($dimension))->resolve($request));
    }

    public function destroy(Dimension $dimension, Request $request, DimensionService $service): JsonResponse
    {
        $service->delete($dimension, $request->user(), $request->boolean('force'));

        return ApiResponse::noContent();
    }

    public function dataset(Dataset $dataset, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewDataset($dataset, $request->user());

        $query = Dimension::query()
            ->where('dataset_id', $dataset->id)
            ->orderBy('id');

        if (! $authorizer->canManageDataset($dataset, $request->user())) {
            $query->where('status', 'active');
        }

        $dimensions = $query->get();

        return ApiResponse::success(DimensionResource::collection($dimensions)->resolve($request));
    }

    public function initFromFields(Dataset $dataset, DimensionService $service, Request $request): JsonResponse
    {
        return ApiResponse::success(DimensionResource::collection($service->initFromFields($dataset, $request->user()))->resolve($request));
    }
}
