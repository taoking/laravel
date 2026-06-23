<?php

namespace App\Modules\DataSource\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\OlapMaterializedViewService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;

class OlapMaterializedViewController extends Controller
{
    public function index(DataSource $dataSource, OlapMaterializedViewService $service): JsonResponse
    {
        return ApiResponse::success($service->list($dataSource));
    }

    public function show(DataSource $dataSource, string $name, OlapMaterializedViewService $service): JsonResponse
    {
        $view = $service->show($dataSource, $name);

        abort_if($view === null, 404);

        return ApiResponse::success($view);
    }

    public function refresh(DataSource $dataSource, string $name, OlapMaterializedViewService $service): JsonResponse
    {
        return ApiResponse::success($service->refresh($dataSource, $name));
    }
}
