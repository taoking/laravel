<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Services\MetadataAssetService;
use App\Modules\Metadata\Services\MetadataGraphService;
use App\Modules\Metadata\Services\MetadataLineageService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataLineageController extends Controller
{
    public function upstream(string $assetType, int $assetId, Request $request, MetadataAssetService $assetService, MetadataLineageService $service): JsonResponse
    {
        $assetService->find($assetType, $assetId, $request->user());
        $depth = min(max($request->integer('depth', 3), 1), 5);

        return ApiResponse::success($service->upstream($assetType, $assetId, $depth));
    }

    public function downstream(string $assetType, int $assetId, Request $request, MetadataAssetService $assetService, MetadataLineageService $service): JsonResponse
    {
        $assetService->find($assetType, $assetId, $request->user());
        $depth = min(max($request->integer('depth', 3), 1), 5);

        return ApiResponse::success($service->downstream($assetType, $assetId, $depth));
    }

    public function graph(string $assetType, int $assetId, Request $request, MetadataAssetService $assetService, MetadataGraphService $service): JsonResponse
    {
        $assetService->find($assetType, $assetId, $request->user());
        $depth = min(max($request->integer('depth', 3), 1), 5);

        return ApiResponse::success($service->graph($assetType, $assetId, $depth));
    }

    public function sync(string $assetType, int $assetId, Request $request, MetadataAssetService $assetService, MetadataLineageService $service): JsonResponse
    {
        $assetService->find($assetType, $assetId, $request->user());
        $count = $service->syncAsset($assetType, $assetId);

        return ApiResponse::success([
            'asset_type' => $assetType,
            'asset_id' => $assetId,
            'relations_synced' => $count,
        ]);
    }
}
