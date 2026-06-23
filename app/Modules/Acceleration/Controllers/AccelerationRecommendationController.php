<?php

namespace App\Modules\Acceleration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acceleration\Models\AccelerationRecommendation;
use App\Modules\Acceleration\Requests\AcceptRecommendationRequest;
use App\Modules\Acceleration\Requests\GenerateRecommendationRequest;
use App\Modules\Acceleration\Requests\RejectRecommendationRequest;
use App\Modules\Acceleration\Resources\AccelerationRecommendationResource;
use App\Modules\Acceleration\Resources\AccelerationTaskResource;
use App\Modules\Acceleration\Resources\AggregateDefinitionResource;
use App\Modules\Acceleration\Services\AccelerationManagementAuthorizer;
use App\Modules\Acceleration\Services\AccelerationRecommendationService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccelerationRecommendationController extends Controller
{
    public function index(Request $request, AccelerationRecommendationService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $recommendations = $service->listRecommendations($request->only([
            'dataset_id',
            'chart_id',
            'recommendation_type',
            'status',
            'priority',
        ]), $pageSize);

        return ApiResponse::paginated(
            $recommendations,
            AccelerationRecommendationResource::collection($recommendations->items())->resolve($request),
        );
    }

    public function generate(GenerateRecommendationRequest $request, AccelerationRecommendationService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $payload = $request->validated();
        $result = $service->generateRecommendations(
            days: (int) ($payload['days'] ?? config('bi_acceleration.recommendation.analysis_days', 7)),
            datasetId: isset($payload['dataset_id']) ? (int) $payload['dataset_id'] : null,
            dryRun: (bool) ($payload['dry_run'] ?? false),
        );

        return ApiResponse::success($result);
    }

    public function show(AccelerationRecommendation $recommendation, Request $request, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $recommendation->load(['dataset', 'chart', 'createdAggregateDefinition']);

        return ApiResponse::success((new AccelerationRecommendationResource($recommendation))->resolve($request));
    }

    public function accept(AcceptRecommendationRequest $request, AccelerationRecommendation $recommendation, AccelerationRecommendationService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $result = $service->acceptRecommendation(
            $recommendation,
            $request->user(),
            (bool) ($request->validated()['build'] ?? true),
        );

        return ApiResponse::success([
            'recommendation' => (new AccelerationRecommendationResource($result['recommendation']))->resolve($request),
            'aggregate_definition' => (new AggregateDefinitionResource($result['aggregate_definition']))->resolve($request),
            'task' => $result['task'] !== null ? (new AccelerationTaskResource($result['task']))->resolve($request) : null,
        ]);
    }

    public function reject(RejectRecommendationRequest $request, AccelerationRecommendation $recommendation, AccelerationRecommendationService $service, AccelerationManagementAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertCanManage($request->user());
        $recommendation = $service->rejectRecommendation($recommendation, $request->user(), $request->validated()['reason'] ?? null);

        return ApiResponse::success((new AccelerationRecommendationResource($recommendation))->resolve($request));
    }
}
