<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Requests\StoreAggregateDefinitionRequest;
use App\Modules\Acceleration\Resources\AggregateDefinitionResource;
use App\Modules\Acceleration\Services\AggregateDefinitionService;
use App\Modules\Dataset\Models\Dataset;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetAccelerationAggregateController extends Controller
{
    public function index(Dataset $dataset, Request $request, AggregateDefinitionService $service): JsonResponse
    {
        $definitions = $service->forDataset($dataset);

        return ApiResponse::success(AggregateDefinitionResource::collection($definitions)->resolve($request));
    }

    public function store(Dataset $dataset, StoreAggregateDefinitionRequest $request, AggregateDefinitionService $service): JsonResponse
    {
        $definition = $service->create([
            ...$request->validated(),
            'dataset_id' => $dataset->id,
        ], $request->user(), $dataset);

        return ApiResponse::created((new AggregateDefinitionResource($definition))->resolve($request));
    }
}
