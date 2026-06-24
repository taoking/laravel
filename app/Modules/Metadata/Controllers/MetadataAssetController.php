<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Resources\MetadataAssetResource;
use App\Modules\Metadata\Resources\MetadataTagResource;
use App\Modules\Metadata\Resources\MetadataUsageStatResource;
use App\Modules\Metadata\Services\MetadataAssetService;
use App\Modules\Metadata\Services\MetadataTagService;
use App\Modules\Metadata\Services\MetadataUsageStatService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataAssetController extends Controller
{
    public function index(Request $request, MetadataAssetService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $assets = $service->paginate($request->only([
            'asset_type',
            'keyword',
            'tag',
            'status',
            'owner_id',
            'data_source_id',
            'dataset_id',
        ]), $pageSize, $request->user());

        return ApiResponse::paginated(
            $assets,
            MetadataAssetResource::collection($assets->items())->resolve($request),
        );
    }

    public function show(string $assetType, int $assetId, Request $request, MetadataAssetService $service): JsonResponse
    {
        $asset = $service->find($assetType, $assetId, $request->user());

        return ApiResponse::success((new MetadataAssetResource($asset))->resolve($request));
    }

    public function update(string $assetType, int $assetId, Request $request, MetadataAssetService $service): JsonResponse
    {
        $payload = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'tags_json' => ['nullable', 'array'],
        ]);

        $asset = $service->update($assetType, $assetId, $payload, $request->user());

        return ApiResponse::success((new MetadataAssetResource($asset))->resolve($request));
    }

    public function archive(string $assetType, int $assetId, Request $request, MetadataAssetService $service): JsonResponse
    {
        $asset = $service->archive($assetType, $assetId, $request->user());

        return ApiResponse::success((new MetadataAssetResource($asset))->resolve($request));
    }

    public function attachTag(string $assetType, int $assetId, Request $request, MetadataTagService $service): JsonResponse
    {
        $payload = $request->validate([
            'tag_id' => ['nullable', 'integer', 'exists:metadata_tags,id'],
            'tag' => ['nullable', 'string', 'max:100'],
        ]);
        $tag = $payload['tag_id'] ?? $payload['tag'] ?? null;

        if ($tag === null) {
            abort(422, 'tag_id or tag is required.');
        }

        $service->attach($assetType, $assetId, $tag, $request->user());

        return ApiResponse::success(MetadataTagResource::collection($service->assetTags($assetType, $assetId, $request->user()))->resolve($request));
    }

    public function detachTag(string $assetType, int $assetId, string $tag, Request $request, MetadataTagService $service): JsonResponse
    {
        $service->detach($assetType, $assetId, $tag, $request->user());

        return ApiResponse::success(MetadataTagResource::collection($service->assetTags($assetType, $assetId, $request->user()))->resolve($request));
    }

    public function usageStats(string $assetType, int $assetId, Request $request, MetadataUsageStatService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $stats = $service->paginate([
            'asset_type' => $assetType,
            'asset_id' => $assetId,
        ], $pageSize, $request->user());

        return ApiResponse::paginated(
            $stats,
            MetadataUsageStatResource::collection($stats->items())->resolve($request),
        );
    }
}
