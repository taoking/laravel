<?php

namespace App\Modules\Import\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Import\Models\ImportTask;
use App\Modules\Import\Requests\StoreImportTaskRequest;
use App\Modules\Import\Resources\ImportTaskResource;
use App\Modules\Import\Services\ImportTaskService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportTaskController extends Controller
{
    public function index(Request $request, ImportTaskService $importTaskService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $tasks = $importTaskService->paginate($pageSize);

        return ApiResponse::paginated(
            $tasks,
            ImportTaskResource::collection($tasks->items())->resolve($request),
        );
    }

    public function store(StoreImportTaskRequest $request, ImportTaskService $importTaskService): JsonResponse
    {
        $task = $importTaskService->create($request->validated(), $request->user());

        return ApiResponse::created((new ImportTaskResource($task))->resolve($request));
    }

    public function show(ImportTask $importTask, Request $request, ImportTaskService $importTaskService): JsonResponse
    {
        $task = $importTaskService->show($importTask);

        return ApiResponse::success((new ImportTaskResource($task))->resolve($request));
    }

    public function retry(ImportTask $importTask, Request $request, ImportTaskService $importTaskService): JsonResponse
    {
        $task = $importTaskService->retry($importTask);

        return ApiResponse::success((new ImportTaskResource($task))->resolve($request));
    }

    public function destroy(ImportTask $importTask, ImportTaskService $importTaskService): JsonResponse
    {
        $importTaskService->delete($importTask);

        return ApiResponse::noContent();
    }
}
