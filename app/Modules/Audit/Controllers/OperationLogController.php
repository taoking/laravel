<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Resources\OperationLogResource;
use App\Modules\Audit\Services\OperationLogService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationLogController extends Controller
{
    public function index(Request $request, OperationLogService $operationLogService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $logs = $operationLogService->paginate($pageSize, $request->only(['user_id', 'resource_type']));

        return ApiResponse::paginated(
            $logs,
            OperationLogResource::collection($logs->items())->resolve($request),
        );
    }
}
