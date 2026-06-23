<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Requests\BuildDatasetAccelerationRequest;
use App\Modules\Acceleration\Resources\AccelerationProfileResource;
use App\Modules\Acceleration\Resources\AccelerationTaskResource;
use App\Modules\Acceleration\Services\AccelerationProfileService;
use App\Modules\Dataset\Models\Dataset;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetAccelerationController extends Controller
{
    public function index(Dataset $dataset, Request $request): JsonResponse
    {
        $profiles = AccelerationProfile::query()
            ->with(['columns', 'tasks'])
            ->where('dataset_id', $dataset->id)
            ->latest('id')
            ->get();

        return ApiResponse::success(AccelerationProfileResource::collection($profiles)->resolve($request));
    }

    public function build(Dataset $dataset, BuildDatasetAccelerationRequest $request, AccelerationProfileService $service): JsonResponse
    {
        $result = $service->buildForDataset($dataset, $request->validated(), $request->user());

        return ApiResponse::success([
            'profile' => (new AccelerationProfileResource($result['profile']->load(['columns', 'tasks'])))->resolve($request),
            'task' => (new AccelerationTaskResource($result['task']))->resolve($request),
        ]);
    }

    public function columns(Dataset $dataset, Request $request, AccelerationProfileService $service): JsonResponse
    {
        return ApiResponse::success($service->columnPreview($dataset));
    }
}
