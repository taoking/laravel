<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Services\MetadataAuthorizer;
use App\Modules\Metadata\Services\MetadataSyncService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetadataSyncController extends Controller
{
    public function __invoke(Request $request, MetadataSyncService $service, MetadataAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $payload = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(['all', 'data_source', 'dataset', 'metrics', 'charts', 'dashboards'])],
            'data_source_id' => ['nullable', 'integer', 'exists:data_sources,id'],
            'dataset_id' => ['nullable', 'integer', 'exists:datasets,id'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($service->sync($payload));
    }
}
