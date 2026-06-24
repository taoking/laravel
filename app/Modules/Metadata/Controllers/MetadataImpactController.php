<?php

namespace App\Modules\Metadata\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metadata\Services\MetadataImpactAnalysisService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetadataImpactController extends Controller
{
    public function analyze(Request $request, MetadataImpactAnalysisService $service): JsonResponse
    {
        $payload = $request->validate([
            'asset_type' => ['required', 'string', 'max:100'],
            'asset_id' => ['required', 'integer', 'min:1'],
            'change_type' => ['required', 'string', Rule::in(MetadataImpactAnalysisService::CHANGE_TYPES)],
        ]);

        return ApiResponse::success($service->analyze(
            $payload['asset_type'],
            (int) $payload['asset_id'],
            $payload['change_type'],
            $request->user(),
        ));
    }
}
