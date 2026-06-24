<?php

namespace App\Modules\Query\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chart\Models\Chart;
use App\Modules\Chart\Services\ChartConfigValidator;
use App\Modules\Chart\Services\ChartQueryBuilder;
use App\Modules\Query\Services\QueryOrchestrator;
use App\Support\Auth\AdminAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueryDebugController extends Controller
{
    public function debug(Request $request, QueryOrchestrator $orchestrator, ChartConfigValidator $chartConfigValidator, ChartQueryBuilder $chartQueryBuilder, AdminAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertAdmin($request->user());

        $payload = $request->validate([
            'dataset_id' => ['required_without:chart_id', 'integer', 'exists:datasets,id'],
            'chart_id' => ['nullable', 'integer', 'exists:charts,id'],
            'dimensions' => ['nullable', 'array'],
            'metrics' => ['nullable', 'array'],
            'semantic_dimensions' => ['nullable', 'array'],
            'semantic_metrics' => ['nullable', 'array'],
            'raw_fields' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'sorts' => ['nullable', 'array'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'use_cache' => ['nullable', 'boolean'],
        ]);
        $context = [
            'request_source' => 'query_debug',
            'chart_id' => $payload['chart_id'] ?? null,
        ];

        if (isset($payload['chart_id'])) {
            $chart = Chart::query()->with('dataset.fields')->findOrFail($payload['chart_id']);
            $chartConfigValidator->validate($chart->dataset, $chart->chart_type, $chart->config_json);
            $payload = $chartQueryBuilder->build($chart->dataset_id, $chart->config_json, [
                'filters' => $payload['filters'] ?? [],
                'sorts' => $payload['sorts'] ?? [],
                'limit' => $payload['limit'] ?? null,
                'offset' => $payload['offset'] ?? null,
                'use_cache' => $payload['use_cache'] ?? false,
            ]);
            $context['chart_id'] = $chart->id;
        }

        return ApiResponse::success($orchestrator->debug($payload, $request->user(), $context));
    }

    public function explain(Request $request, QueryOrchestrator $orchestrator, AdminAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertAdmin($request->user());

        $payload = $request->validate([
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'dimensions' => ['nullable', 'array'],
            'metrics' => ['nullable', 'array'],
            'semantic_dimensions' => ['nullable', 'array'],
            'semantic_metrics' => ['nullable', 'array'],
            'raw_fields' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'sorts' => ['nullable', 'array'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        return ApiResponse::success([
            ...$orchestrator->debug($payload, $request->user(), ['request_source' => 'query_explain']),
            'execute' => false,
        ]);
    }
}
