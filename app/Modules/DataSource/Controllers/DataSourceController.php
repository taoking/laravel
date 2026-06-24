<?php

namespace App\Modules\DataSource\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\DataSource\Requests\StoreDataSourceRequest;
use App\Modules\DataSource\Requests\TableFieldsRequest;
use App\Modules\DataSource\Requests\UpdateDataSourceRequest;
use App\Modules\DataSource\Resources\DataSourceFieldResource;
use App\Modules\DataSource\Resources\DataSourceResource;
use App\Modules\DataSource\Resources\DataSourceTableResource;
use App\Modules\DataSource\Services\DataSourceMetadataService;
use App\Modules\DataSource\Services\DataSourceService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    public function index(Request $request, DataSourceService $dataSourceService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $dataSources = $dataSourceService->paginate($pageSize);

        return ApiResponse::paginated(
            $dataSources,
            DataSourceResource::collection($dataSources->items())->resolve($request),
        );
    }

    public function store(StoreDataSourceRequest $request, DataSourceService $dataSourceService): JsonResponse
    {
        $dataSource = $dataSourceService->create($request->validated(), $request->user());

        return ApiResponse::created((new DataSourceResource($dataSource))->resolve($request));
    }

    public function show(DataSource $dataSource, Request $request): JsonResponse
    {
        $dataSource->loadCount(['tables', 'fields']);

        return ApiResponse::success((new DataSourceResource($dataSource))->resolve($request));
    }

    public function update(UpdateDataSourceRequest $request, DataSource $dataSource, DataSourceService $dataSourceService): JsonResponse
    {
        $dataSource = $dataSourceService->update($dataSource, $request->validated(), $request->user());

        return ApiResponse::success((new DataSourceResource($dataSource))->resolve($request));
    }

    public function destroy(DataSource $dataSource, Request $request, DataSourceService $dataSourceService): JsonResponse
    {
        $dataSourceService->delete($dataSource, $request->user(), $request->boolean('force'));

        return ApiResponse::noContent();
    }

    public function test(DataSource $dataSource, DataSourceMetadataService $metadataService): JsonResponse
    {
        return ApiResponse::success($metadataService->test($dataSource));
    }

    public function sync(DataSource $dataSource, DataSourceMetadataService $metadataService): JsonResponse
    {
        return ApiResponse::success($metadataService->sync($dataSource));
    }

    public function tables(DataSource $dataSource, Request $request, DataSourceMetadataService $metadataService): JsonResponse
    {
        $tables = $metadataService->tables($dataSource);

        return ApiResponse::success(
            DataSourceTableResource::collection(
                collect($tables)->mapInto(DataSourceTable::class),
            )->resolve($request),
        );
    }

    public function databases(DataSource $dataSource, DataSourceMetadataService $metadataService): JsonResponse
    {
        return ApiResponse::success($metadataService->databases($dataSource));
    }

    public function views(DataSource $dataSource, Request $request, DataSourceMetadataService $metadataService): JsonResponse
    {
        $views = $metadataService->views($dataSource);

        return ApiResponse::success(
            DataSourceTableResource::collection(
                collect($views)->mapInto(DataSourceTable::class),
            )->resolve($request),
        );
    }

    public function fields(TableFieldsRequest $request, DataSource $dataSource, string $table, DataSourceMetadataService $metadataService): JsonResponse
    {
        $fields = $metadataService->fields($dataSource, $table);

        return ApiResponse::success(
            DataSourceFieldResource::collection(
                collect($fields)->mapInto(DataSourceField::class),
            )->resolve($request),
        );
    }

    public function preview(Request $request, DataSource $dataSource, string $table, DataSourceMetadataService $metadataService): JsonResponse
    {
        $limit = min(max($request->integer('limit', 100), 1), 1000);

        return ApiResponse::success($metadataService->preview($dataSource, $table, $limit));
    }
}
