<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Requests\StoreAccelerationProfileRequest;
use App\Modules\Acceleration\Requests\UpdateAccelerationProfileRequest;
use App\Modules\Acceleration\Resources\AccelerationProfileResource;
use App\Modules\Acceleration\Resources\AccelerationTaskResource;
use App\Modules\Acceleration\Services\AccelerationProfileService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationProfileController extends Controller
{
    public function index(Request $request, AccelerationProfileService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $profiles = $service->paginate($pageSize);

        return ApiResponse::paginated(
            $profiles,
            AccelerationProfileResource::collection($profiles->items())->resolve($request),
        );
    }

    public function store(StoreAccelerationProfileRequest $request, AccelerationProfileService $service): JsonResponse
    {
        $profile = $service->create($request->validated(), $request->user());

        return ApiResponse::created((new AccelerationProfileResource($profile))->resolve($request));
    }

    public function show(AccelerationProfile $profile, Request $request): JsonResponse
    {
        $profile->load(['dataset', 'columns', 'tasks']);

        return ApiResponse::success((new AccelerationProfileResource($profile))->resolve($request));
    }

    public function update(UpdateAccelerationProfileRequest $request, AccelerationProfile $profile, AccelerationProfileService $service): JsonResponse
    {
        $profile = $service->update($profile, $request->validated());

        return ApiResponse::success((new AccelerationProfileResource($profile))->resolve($request));
    }

    public function destroy(AccelerationProfile $profile, AccelerationProfileService $service): JsonResponse
    {
        $service->delete($profile);

        return ApiResponse::noContent();
    }

    public function test(AccelerationProfile $profile, AccelerationProfileService $service): JsonResponse
    {
        return ApiResponse::success(['ok' => $service->test($profile)]);
    }

    public function build(AccelerationProfile $profile, Request $request, AccelerationProfileService $service): JsonResponse
    {
        $task = $service->build($profile, $request->user());

        return ApiResponse::success((new AccelerationTaskResource($task))->resolve($request));
    }

    public function refresh(AccelerationProfile $profile, Request $request, AccelerationProfileService $service): JsonResponse
    {
        $task = $service->build($profile, $request->user(), 'full_sync');

        return ApiResponse::success((new AccelerationTaskResource($task))->resolve($request));
    }

    public function disable(AccelerationProfile $profile, Request $request, AccelerationProfileService $service): JsonResponse
    {
        $profile = $service->disable($profile);

        return ApiResponse::success((new AccelerationProfileResource($profile))->resolve($request));
    }

    public function activate(AccelerationProfile $profile, Request $request, AccelerationProfileService $service): JsonResponse
    {
        $profile = $service->activate($profile);

        return ApiResponse::success((new AccelerationProfileResource($profile))->resolve($request));
    }
}
