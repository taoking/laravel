<?php

namespace App\Modules\Dataset\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Dataset\Requests\PreviewDatasetRequest;
use App\Modules\Dataset\Requests\StoreDatasetRequest;
use App\Modules\Dataset\Requests\UpdateDatasetFieldRequest;
use App\Modules\Dataset\Requests\UpdateDatasetRequest;
use App\Modules\Dataset\Resources\DatasetFieldResource;
use App\Modules\Dataset\Resources\DatasetResource;
use App\Modules\Dataset\Services\DatasetPreviewService;
use App\Modules\Dataset\Services\DatasetService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function index(Request $request, DatasetService $datasetService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $datasets = $datasetService->paginate($pageSize);

        return ApiResponse::paginated(
            $datasets,
            DatasetResource::collection($datasets->items())->resolve($request),
        );
    }

    public function store(StoreDatasetRequest $request, DatasetService $datasetService): JsonResponse
    {
        $dataset = $datasetService->create($request->validated(), $request->user());

        return ApiResponse::created((new DatasetResource($dataset))->resolve($request));
    }

    public function show(Dataset $dataset, Request $request): JsonResponse
    {
        $dataset->load(['dataSource', 'tables', 'fields'])->loadCount(['tables', 'fields']);

        return ApiResponse::success((new DatasetResource($dataset))->resolve($request));
    }

    public function update(UpdateDatasetRequest $request, Dataset $dataset, DatasetService $datasetService): JsonResponse
    {
        $dataset = $datasetService->update($dataset, $request->validated(), $request->user());

        return ApiResponse::success((new DatasetResource($dataset))->resolve($request));
    }

    public function destroy(Dataset $dataset, DatasetService $datasetService): JsonResponse
    {
        $datasetService->delete($dataset);

        return ApiResponse::noContent();
    }

    public function syncFields(Dataset $dataset, DatasetService $datasetService): JsonResponse
    {
        return ApiResponse::success($datasetService->syncFields($dataset));
    }

    public function fields(Dataset $dataset, Request $request): JsonResponse
    {
        $fields = $dataset->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(DatasetFieldResource::collection($fields)->resolve($request));
    }

    public function updateField(UpdateDatasetFieldRequest $request, Dataset $dataset, DatasetField $field, DatasetService $datasetService): JsonResponse
    {
        $field = $datasetService->updateField($dataset, $field, $request->validated());

        return ApiResponse::success((new DatasetFieldResource($field))->resolve($request));
    }

    public function preview(PreviewDatasetRequest $request, Dataset $dataset, DatasetPreviewService $previewService): JsonResponse
    {
        return ApiResponse::success($previewService->preview($dataset, $request->validated()));
    }
}
