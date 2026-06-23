<?php

namespace App\Modules\Query\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chart\Models\Chart;
use App\Modules\Chart\Requests\ChartDataRequest;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Requests\ExplainDatasetRequest;
use App\Modules\Query\Services\QueryExplainService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;

class QueryExplainController extends Controller
{
    public function dataset(ExplainDatasetRequest $request, Dataset $dataset, QueryExplainService $service): JsonResponse
    {
        return ApiResponse::success($service->dataset($dataset, $request->validated(), $request->user()));
    }

    public function chart(ChartDataRequest $request, Chart $chart, QueryExplainService $service): JsonResponse
    {
        return ApiResponse::success($service->chart($chart, $request->validated(), $request->user()));
    }
}
