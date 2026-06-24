<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Resources\MetadataAssetResource;
use App\Modules\Metadata\Services\MetadataSearchService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataSearchController extends Controller
{
    public function __invoke(Request $request, MetadataSearchService $service): JsonResponse
    {
        $payload = $request->validate([
            'keyword' => ['nullable', 'string', 'max:200'],
            'asset_type' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $assets = $service->search($payload, $request->user());

        return ApiResponse::success(MetadataAssetResource::collection($assets)->resolve($request));
    }
}
