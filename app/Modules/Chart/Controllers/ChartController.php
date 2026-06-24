<?php

namespace App\Modules\Chart\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chart\Models\Chart;
use App\Modules\Chart\Requests\ChartDataRequest;
use App\Modules\Chart\Requests\PreviewChartRequest;
use App\Modules\Chart\Requests\StoreChartRequest;
use App\Modules\Chart\Requests\UpdateChartRequest;
use App\Modules\Chart\Resources\ChartResource;
use App\Modules\Chart\Services\ChartDataService;
use App\Modules\Chart\Services\ChartService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function index(Request $request, ChartService $chartService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $charts = $chartService->paginate($pageSize);

        return ApiResponse::paginated(
            $charts,
            ChartResource::collection($charts->items())->resolve($request),
        );
    }

    public function store(StoreChartRequest $request, ChartService $chartService): JsonResponse
    {
        $chart = $chartService->create($request->validated(), $request->user());

        return ApiResponse::created((new ChartResource($chart))->resolve($request));
    }

    public function show(Chart $chart, Request $request): JsonResponse
    {
        $chart->load('dataset');

        return ApiResponse::success((new ChartResource($chart))->resolve($request));
    }

    public function update(UpdateChartRequest $request, Chart $chart, ChartService $chartService): JsonResponse
    {
        $chart = $chartService->update($chart, $request->validated(), $request->user());

        return ApiResponse::success((new ChartResource($chart))->resolve($request));
    }

    public function destroy(Chart $chart, Request $request, ChartService $chartService): JsonResponse
    {
        $chartService->delete($chart, $request->user(), $request->boolean('force'));

        return ApiResponse::noContent();
    }

    public function data(ChartDataRequest $request, Chart $chart, ChartDataService $chartDataService): JsonResponse
    {
        return ApiResponse::success($chartDataService->data($chart, $request->validated(), $request->user()));
    }

    public function preview(PreviewChartRequest $request, ChartDataService $chartDataService): JsonResponse
    {
        return ApiResponse::success($chartDataService->preview($request->validated(), $request->user()));
    }
}
