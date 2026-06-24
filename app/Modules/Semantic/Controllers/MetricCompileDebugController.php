<?php

namespace App\Modules\Semantic\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Semantic\Services\SemanticQueryCompiler;
use App\Support\Auth\AdminAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MetricCompileDebugController extends Controller
{
    public function __invoke(Request $request, SemanticQueryCompiler $compiler, AdminAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertAdmin($request->user());

        $payload = $request->validate([
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*' => ['string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*' => ['string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
        ]);

        try {
            $plan = $compiler->compile([
                'dataset_id' => $payload['dataset_id'],
                'semantic_metrics' => collect($payload['metrics'])->map(fn (string $code): array => ['metric_code' => $code])->all(),
                'semantic_dimensions' => collect($payload['dimensions'] ?? [])->map(fn (string $code): array => ['dimension_code' => $code])->all(),
            ]);

            return ApiResponse::success([
                'base_metrics' => array_values($plan->queryPayload['metrics'] ?? []),
                'computed_metrics' => collect($plan->compoundFormulas)
                    ->map(fn (string $formula, string $code): array => [
                        'metric_code' => $code,
                        'formula' => $formula,
                    ])
                    ->values()
                    ->all(),
                'dependencies' => $plan->dependencyMetricCodes,
                'metric_versions' => $plan->metricVersions,
                'semantic_metrics' => $plan->semanticMetrics,
                'semantic_dimensions' => $plan->semanticDimensions,
                'errors' => [],
            ]);
        } catch (Throwable $exception) {
            return ApiResponse::success([
                'base_metrics' => [],
                'computed_metrics' => [],
                'dependencies' => [],
                'metric_versions' => [],
                'errors' => [$exception->getMessage()],
            ]);
        }
    }
}
