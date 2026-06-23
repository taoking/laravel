<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Services\AccelerationBenefitReportService;
use App\Modules\Acceleration\Services\AccelerationManagementAuthorizer;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationBenefitReportController extends Controller
{
    public function index(Request $request, AccelerationBenefitReportService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());

        return ApiResponse::success($service->report(
            days: max($request->integer('days', 1), 1),
            date: $request->filled('date') ? $request->string('date')->toString() : null,
        ));
    }

    public function dataset(Dataset $dataset, Request $request, AccelerationBenefitReportService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());

        return ApiResponse::success($service->report(
            days: max($request->integer('days', 1), 1),
            date: $request->filled('date') ? $request->string('date')->toString() : null,
            datasetId: (int) $dataset->id,
        ));
    }

    public function chart(Chart $chart, Request $request, AccelerationBenefitReportService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());

        return ApiResponse::success($service->report(
            days: max($request->integer('days', 1), 1),
            date: $request->filled('date') ? $request->string('date')->toString() : null,
            chartId: (int) $chart->id,
        ));
    }
}
