<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Models\AccelerationRefreshSchedule;
use App\Modules\Acceleration\Requests\StoreRefreshScheduleRequest;
use App\Modules\Acceleration\Requests\UpdateRefreshScheduleRequest;
use App\Modules\Acceleration\Resources\AccelerationRefreshScheduleResource;
use App\Modules\Acceleration\Resources\AccelerationTaskResource;
use App\Modules\Acceleration\Services\AccelerationManagementAuthorizer;
use App\Modules\Acceleration\Services\AccelerationRefreshRunner;
use App\Modules\Acceleration\Services\AccelerationRefreshScheduleService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationRefreshScheduleController extends Controller
{
    public function index(Request $request, AccelerationRefreshScheduleService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $schedules = $service->paginate($request->only(['target_type', 'target_id', 'enabled']), $pageSize);

        return ApiResponse::paginated(
            $schedules,
            AccelerationRefreshScheduleResource::collection($schedules->items())->resolve($request),
        );
    }

    public function store(StoreRefreshScheduleRequest $request, AccelerationRefreshScheduleService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $schedule = $service->create($request->validated(), $request->user());

        return ApiResponse::created((new AccelerationRefreshScheduleResource($schedule))->resolve($request));
    }

    public function show(AccelerationRefreshSchedule $schedule, Request $request, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $schedule->load('lastTask');

        return ApiResponse::success((new AccelerationRefreshScheduleResource($schedule))->resolve($request));
    }

    public function update(UpdateRefreshScheduleRequest $request, AccelerationRefreshSchedule $schedule, AccelerationRefreshScheduleService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $schedule = $service->update($schedule, $request->validated());

        return ApiResponse::success((new AccelerationRefreshScheduleResource($schedule))->resolve($request));
    }

    public function destroy(AccelerationRefreshSchedule $schedule, AccelerationRefreshScheduleService $service, Request $request, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $service->delete($schedule);

        return ApiResponse::noContent();
    }

    public function enable(AccelerationRefreshSchedule $schedule, Request $request, AccelerationRefreshScheduleService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $schedule = $service->enable($schedule);

        return ApiResponse::success((new AccelerationRefreshScheduleResource($schedule))->resolve($request));
    }

    public function disable(AccelerationRefreshSchedule $schedule, Request $request, AccelerationRefreshScheduleService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $schedule = $service->disable($schedule);

        return ApiResponse::success((new AccelerationRefreshScheduleResource($schedule))->resolve($request));
    }

    public function runNow(AccelerationRefreshSchedule $schedule, Request $request, AccelerationRefreshRunner $runner, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $task = $runner->runSchedule($schedule);

        return ApiResponse::success([
            'schedule' => (new AccelerationRefreshScheduleResource($schedule->refresh()->load('lastTask')))->resolve($request),
            'task' => $task !== null ? (new AccelerationTaskResource($task))->resolve($request) : null,
        ]);
    }
}
