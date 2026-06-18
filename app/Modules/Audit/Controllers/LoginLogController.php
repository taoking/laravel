<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Resources\LoginLogResource;
use App\Modules\Audit\Services\LoginLogService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function index(Request $request, LoginLogService $loginLogService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $logs = $loginLogService->paginate($pageSize, $request->only(['user_id', 'status']));

        return ApiResponse::paginated(
            $logs,
            LoginLogResource::collection($logs->items())->resolve($request),
        );
    }
}
