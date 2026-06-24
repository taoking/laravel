<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Resources\MetadataUsageStatResource;
use App\Modules\Metadata\Services\MetadataUsageStatService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataUsageStatController extends Controller
{
    public function index(Request $request, MetadataUsageStatService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $stats = $service->paginate($request->only([
            'asset_type',
            'asset_id',
            'usage_date',
            'high_slow',
            'low_frequency',
        ]), $pageSize, $request->user());

        return ApiResponse::success([
            'items' => MetadataUsageStatResource::collection($stats->items())->resolve($request),
            'pagination' => [
                'page' => $stats->currentPage(),
                'page_size' => $stats->perPage(),
                'total' => $stats->total(),
            ],
            'summary' => $service->summary($request->user(), min(max($request->integer('days', 30), 1), 365)),
        ]);
    }
}
