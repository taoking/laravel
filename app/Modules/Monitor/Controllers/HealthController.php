<?php

namespace App\Modules\Monitor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitor\Services\HealthCheckService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function index(HealthCheckService $healthCheckService): JsonResponse
    {
        return ApiResponse::success($healthCheckService->all());
    }

    public function database(HealthCheckService $healthCheckService): JsonResponse
    {
        return ApiResponse::success($healthCheckService->database());
    }

    public function redis(HealthCheckService $healthCheckService): JsonResponse
    {
        return ApiResponse::success($healthCheckService->redis());
    }

    public function storage(HealthCheckService $healthCheckService): JsonResponse
    {
        return ApiResponse::success($healthCheckService->storage());
    }

    public function queue(HealthCheckService $healthCheckService): JsonResponse
    {
        return ApiResponse::success($healthCheckService->queue());
    }
}
