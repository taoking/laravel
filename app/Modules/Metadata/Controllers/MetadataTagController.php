<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Models\MetadataTag;
use App\Modules\Metadata\Resources\MetadataTagResource;
use App\Modules\Metadata\Services\MetadataTagService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataTagController extends Controller
{
    public function index(Request $request, MetadataTagService $service): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 50), 1), 100);
        $tags = $service->paginate($pageSize);

        return ApiResponse::paginated(
            $tags,
            MetadataTagResource::collection($tags->items())->resolve($request),
        );
    }

    public function store(Request $request, MetadataTagService $service): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:metadata_tags,name'],
            'color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $tag = $service->create($payload, $request->user());

        return ApiResponse::created((new MetadataTagResource($tag))->resolve($request));
    }

    public function update(MetadataTag $tag, Request $request, MetadataTagService $service): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:100', 'unique:metadata_tags,name,'.$tag->id],
            'color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $tag = $service->update($tag, $payload, $request->user());

        return ApiResponse::success((new MetadataTagResource($tag))->resolve($request));
    }

    public function destroy(MetadataTag $tag, Request $request, MetadataTagService $service): JsonResponse
    {
        $service->delete($tag, $request->user());

        return ApiResponse::noContent();
    }
}
