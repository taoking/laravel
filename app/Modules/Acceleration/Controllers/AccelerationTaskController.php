<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Models\AccelerationTask;
use App\Modules\Acceleration\Resources\AccelerationTaskResource;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $tasks = AccelerationTask::query()
            ->with('profile')
            ->latest('id')
            ->paginate($pageSize);

        return ApiResponse::paginated(
            $tasks,
            AccelerationTaskResource::collection($tasks->items())->resolve($request),
        );
    }

    public function show(AccelerationTask $task, Request $request): JsonResponse
    {
        $task->load('profile');

        return ApiResponse::success((new AccelerationTaskResource($task))->resolve($request));
    }
}
