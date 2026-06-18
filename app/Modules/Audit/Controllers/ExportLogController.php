<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Resources\ExportLogResource;
use App\Modules\Audit\Services\ExportLogService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportLogController extends Controller
{
    public function index(Request $request, ExportLogService $exportLogService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $logs = $exportLogService->paginate($pageSize, $request->only(['user_id', 'status']));

        return ApiResponse::paginated(
            $logs,
            ExportLogResource::collection($logs->items())->resolve($request),
        );
    }
}
