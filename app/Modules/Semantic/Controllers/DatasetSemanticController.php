<?php

namespace App\Modules\Semantic\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Resources\DimensionResource;
use App\Modules\Semantic\Resources\MetricResource;
use App\Modules\Semantic\Services\MetricService;
use App\Modules\Semantic\Services\SemanticLayerAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetSemanticController extends Controller
{
    public function metrics(Dataset $dataset, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewDataset($dataset, $request->user());

        $query = Metric::query()
            ->with('category')
            ->where('dataset_id', $dataset->id)
            ->orderBy('id');

        if (! $authorizer->canManageDataset($dataset, $request->user())) {
            $query->where('status', 'active');
        }

        $metrics = $query->get();

        return ApiResponse::success(MetricResource::collection($metrics)->resolve($request));
    }

    public function initMetricsFromFields(Dataset $dataset, MetricService $service, Request $request): JsonResponse
    {
        return ApiResponse::success(MetricResource::collection($service->initFromFields($dataset, $request->user()))->resolve($request));
    }

    public function semanticLayer(Dataset $dataset, Request $request, SemanticLayerAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanViewDataset($dataset, $request->user());

        $metricStatuses = $authorizer->canManageDataset($dataset, $request->user())
            ? ['active', 'deprecated']
            : ['active'];
        $metrics = Metric::query()
            ->with('category')
            ->where('dataset_id', $dataset->id)
            ->whereIn('status', $metricStatuses)
            ->orderBy('id')
            ->get();
        $dimensions = Dimension::query()
            ->where('dataset_id', $dataset->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        return ApiResponse::success([
            'dataset_id' => $dataset->id,
            'metrics' => MetricResource::collection($metrics)->resolve($request),
            'dimensions' => DimensionResource::collection($dimensions)->resolve($request),
        ]);
    }
}
