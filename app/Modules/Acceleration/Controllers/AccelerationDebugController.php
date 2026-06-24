<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Services\AccelerationDecisionPipeline;
use App\Modules\Acceleration\Services\AccelerationQueryRouter;
use App\Modules\DataPermission\DTO\PermissionContext;
use App\Modules\DataPermission\Services\PermissionCompiler;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use App\Modules\Semantic\Services\SemanticQueryCompiler;
use App\Support\Auth\AdminAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationDebugController extends Controller
{
    public function __invoke(Request $request, SemanticQueryCompiler $semanticCompiler, PermissionCompiler $permissionCompiler, QueryRequestValidator $validator, AccelerationQueryRouter $router, AccelerationDecisionPipeline $pipeline, AdminAuthorizer $authorizer): JsonResponse
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
        $semanticUsed = $semanticCompiler->usesSemanticLayer($payload);
        $resolvedPayload = $semanticUsed ? $semanticCompiler->compile($payload)->queryPayload : $payload;
        $query = QueryRequestDTO::fromArray($resolvedPayload);
        $dataset = Dataset::query()->with(['dataSource', 'fields'])->findOrFail($query->datasetId);
        $permission = $permissionCompiler->compile(new PermissionContext($dataset, $request->user(), 'acceleration_debug'));
        if (! $permission->resourceAllowed) {
            throw new AuthorizationException($permission->deniedReason ?? 'This action is unauthorized.');
        }

        $validator->validate($dataset, $query, $request->user());
        $plan = $router->plan($dataset, $query, $request->user(), $permission->rowFilters, $permission, 'acceleration_debug', $semanticUsed);
        $decision = $pipeline->decide($plan);

        return ApiResponse::success([
            'candidates' => array_map(fn ($candidate): array => $candidate->toArray(), $decision->candidates),
            'selected' => $decision->accelerationMode,
            'decision' => $decision->toArray(),
            'logical_plan_hash' => $plan->hash(),
        ]);
    }
}
