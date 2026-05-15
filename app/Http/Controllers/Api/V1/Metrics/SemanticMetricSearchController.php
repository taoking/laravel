<?php

namespace App\Http\Controllers\Api\V1\Metrics;

use App\Domains\Metrics\Services\SemanticMetricSearchService;
use App\Http\Controllers\Controller;
use App\Http\Resources\MetricResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemanticMetricSearchController extends Controller
{
    public function __invoke(Request $request, SemanticMetricSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ]);

        $results = collect($search->search(
            query: $validated['q'],
            limit: (int) ($validated['limit'] ?? 10),
        ))->map(fn (array $result): array => [
            'metric' => MetricResource::make($result['metric'])->resolve(),
            'score' => $result['score'],
            'matched_terms' => $result['matched_terms'],
            'engine' => 'local-token-vector',
        ])->values()->all();

        return ApiResponse::success([
            'query' => $validated['q'],
            'results' => $results,
        ]);
    }
}
