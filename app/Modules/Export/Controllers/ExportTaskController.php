<?php

namespace App\Modules\Export\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Export\Models\ExportTask;
use App\Modules\Export\Requests\StoreExportTaskRequest;
use App\Modules\Export\Resources\ExportTaskResource;
use App\Modules\Export\Services\ExportTaskService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportTaskController extends Controller
{
    public function index(Request $request, ExportTaskService $exportTaskService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $tasks = $exportTaskService->paginate($pageSize, $request->user());

        return ApiResponse::paginated(
            $tasks,
            ExportTaskResource::collection($tasks->items())->resolve($request),
        );
    }

    public function store(StoreExportTaskRequest $request, ExportTaskService $exportTaskService): JsonResponse
    {
        $task = $exportTaskService->create($request->validated(), $request->user());

        return ApiResponse::created((new ExportTaskResource($task))->resolve($request));
    }

    public function show(ExportTask $exportTask, Request $request, ExportTaskService $exportTaskService): JsonResponse
    {
        $task = $exportTaskService->show($exportTask, $request->user());

        return ApiResponse::success((new ExportTaskResource($task))->resolve($request));
    }

    public function retry(ExportTask $exportTask, Request $request, ExportTaskService $exportTaskService): JsonResponse
    {
        $task = $exportTaskService->retry($exportTask, $request->user());

        return ApiResponse::success((new ExportTaskResource($task))->resolve($request));
    }

    public function download(ExportTask $exportTask, Request $request, ExportTaskService $exportTaskService): Response
    {
        $file = $exportTaskService->download($exportTask, $request->user());
        $fileName = str_replace('"', '', $file['file_name']);

        return response($file['content'], 200, [
            'Content-Type' => $file['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }
}
