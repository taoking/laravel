<?php

use App\Modules\Acceleration\Controllers\AccelerationAggregateController;
use App\Modules\Acceleration\Controllers\AccelerationBenefitReportController;
use App\Modules\Acceleration\Controllers\AccelerationProfileController;
use App\Modules\Acceleration\Controllers\AccelerationRecommendationController;
use App\Modules\Acceleration\Controllers\AccelerationRefreshScheduleController;
use App\Modules\Acceleration\Controllers\AccelerationTaskController;
use App\Modules\Acceleration\Controllers\DatasetAccelerationAggregateController;
use App\Modules\Acceleration\Controllers\DatasetAccelerationController;
use App\Modules\Audit\Controllers\ExportLogController;
use App\Modules\Audit\Controllers\LoginLogController;
use App\Modules\Audit\Controllers\OperationLogController;
use App\Modules\Audit\Controllers\QueryLogController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Chart\Controllers\ChartController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\DataPermission\Controllers\ColumnPermissionRuleController;
use App\Modules\DataPermission\Controllers\DataPermissionRuleController;
use App\Modules\DataPermission\Controllers\ResourcePermissionController;
use App\Modules\Dataset\Controllers\DatasetController;
use App\Modules\DataSource\Controllers\DataSourceController;
use App\Modules\DataSource\Controllers\OlapMaterializedViewController;
use App\Modules\Export\Controllers\ExportTaskController;
use App\Modules\Import\Controllers\ImportTaskController;
use App\Modules\Metadata\Controllers\MetadataAssetController;
use App\Modules\Metadata\Controllers\MetadataImpactController;
use App\Modules\Metadata\Controllers\MetadataLineageController;
use App\Modules\Metadata\Controllers\MetadataSearchController;
use App\Modules\Metadata\Controllers\MetadataSyncController;
use App\Modules\Metadata\Controllers\MetadataTagController;
use App\Modules\Metadata\Controllers\MetadataUsageStatController;
use App\Modules\Monitor\Controllers\HealthController;
use App\Modules\Monitor\Controllers\MetricsController;
use App\Modules\Permission\Controllers\PermissionController;
use App\Modules\Permission\Controllers\RoleController;
use App\Modules\Query\Controllers\QueryController;
use App\Modules\Query\Controllers\QueryExplainController;
use App\Modules\Semantic\Controllers\DatasetSemanticController;
use App\Modules\Semantic\Controllers\DimensionController;
use App\Modules\Semantic\Controllers\MetricCategoryController;
use App\Modules\Semantic\Controllers\MetricController;
use App\Modules\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::get('share/dashboards/{token}', [DashboardController::class, 'publicShow']);

Route::get('health', [HealthController::class, 'index']);
Route::get('health/database', [HealthController::class, 'database']);
Route::get('health/redis', [HealthController::class, 'redis']);
Route::get('health/storage', [HealthController::class, 'storage']);
Route::get('health/queue', [HealthController::class, 'queue']);
Route::get('metrics', MetricsController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('data-sources/{data_source}/test', [DataSourceController::class, 'test']);
    Route::post('data-sources/{data_source}/sync', [DataSourceController::class, 'sync']);
    Route::get('data-sources/{data_source}/databases', [DataSourceController::class, 'databases']);
    Route::get('data-sources/{data_source}/tables', [DataSourceController::class, 'tables']);
    Route::get('data-sources/{data_source}/views', [DataSourceController::class, 'views']);
    Route::post('data-sources/{data_source}/tables/{table}/preview', [DataSourceController::class, 'preview']);
    Route::get('data-sources/{data_source}/tables/{table}/fields', [DataSourceController::class, 'fields']);
    Route::get('data-sources/{data_source}/materialized-views', [OlapMaterializedViewController::class, 'index']);
    Route::get('data-sources/{data_source}/materialized-views/{name}', [OlapMaterializedViewController::class, 'show']);
    Route::post('data-sources/{data_source}/materialized-views/{name}/refresh', [OlapMaterializedViewController::class, 'refresh']);
    Route::apiResource('data-sources', DataSourceController::class);

    Route::post('datasets/{dataset}/sync-fields', [DatasetController::class, 'syncFields']);
    Route::get('datasets/{dataset}/fields', [DatasetController::class, 'fields']);
    Route::put('datasets/{dataset}/fields/{field}', [DatasetController::class, 'updateField']);
    Route::post('datasets/{dataset}/preview', [DatasetController::class, 'preview']);
    Route::post('datasets/{dataset}/explain', [QueryExplainController::class, 'dataset']);
    Route::get('datasets/{dataset}/dimensions', [DimensionController::class, 'dataset']);
    Route::post('datasets/{dataset}/dimensions/init-from-fields', [DimensionController::class, 'initFromFields']);
    Route::get('datasets/{dataset}/metrics', [DatasetSemanticController::class, 'metrics']);
    Route::post('datasets/{dataset}/metrics/init-from-fields', [DatasetSemanticController::class, 'initMetricsFromFields']);
    Route::get('datasets/{dataset}/semantic-layer', [DatasetSemanticController::class, 'semanticLayer']);
    Route::get('datasets/{dataset}/acceleration', [DatasetAccelerationController::class, 'index']);
    Route::post('datasets/{dataset}/acceleration/build', [DatasetAccelerationController::class, 'build']);
    Route::get('datasets/{dataset}/acceleration/columns', [DatasetAccelerationController::class, 'columns']);
    Route::get('datasets/{dataset}/acceleration/aggregates', [DatasetAccelerationAggregateController::class, 'index']);
    Route::post('datasets/{dataset}/acceleration/aggregates', [DatasetAccelerationAggregateController::class, 'store']);
    Route::apiResource('datasets', DatasetController::class);

    Route::post('query/execute', [QueryController::class, 'execute']);

    Route::post('semantic-metrics/validate-formula', [MetricController::class, 'validateFormula']);
    Route::post('semantic-metrics/{metric}/activate', [MetricController::class, 'activate']);
    Route::post('semantic-metrics/{metric}/deprecate', [MetricController::class, 'deprecate']);
    Route::post('semantic-metrics/{metric}/archive', [MetricController::class, 'archive']);
    Route::get('semantic-metrics/{metric}/versions', [MetricController::class, 'versions']);
    Route::get('semantic-metrics/{metric}/dependencies', [MetricController::class, 'dependencies']);
    Route::get('semantic-metrics/{metric}/usages', [MetricController::class, 'usages']);
    Route::get('semantic-metrics/{metric}/impact', [MetricController::class, 'impact']);
    Route::apiResource('semantic-metrics', MetricController::class)->parameters([
        'semantic-metrics' => 'metric',
    ]);
    Route::apiResource('metric-categories', MetricCategoryController::class)->parameters([
        'metric-categories' => 'metricCategory',
    ]);
    Route::apiResource('dimensions', DimensionController::class);

    Route::prefix('acceleration')->group(function (): void {
        Route::get('tasks', [AccelerationTaskController::class, 'index']);
        Route::get('tasks/{task}', [AccelerationTaskController::class, 'show']);
        Route::get('benefit-report', [AccelerationBenefitReportController::class, 'index']);
        Route::get('benefit-report/datasets/{dataset}', [AccelerationBenefitReportController::class, 'dataset']);
        Route::get('benefit-report/charts/{chart}', [AccelerationBenefitReportController::class, 'chart']);
        Route::post('recommendations/generate', [AccelerationRecommendationController::class, 'generate']);
        Route::post('recommendations/{recommendation}/accept', [AccelerationRecommendationController::class, 'accept']);
        Route::post('recommendations/{recommendation}/reject', [AccelerationRecommendationController::class, 'reject']);
        Route::get('recommendations', [AccelerationRecommendationController::class, 'index']);
        Route::get('recommendations/{recommendation}', [AccelerationRecommendationController::class, 'show']);
        Route::post('refresh-schedules/{schedule}/enable', [AccelerationRefreshScheduleController::class, 'enable']);
        Route::post('refresh-schedules/{schedule}/disable', [AccelerationRefreshScheduleController::class, 'disable']);
        Route::post('refresh-schedules/{schedule}/run-now', [AccelerationRefreshScheduleController::class, 'runNow']);
        Route::apiResource('refresh-schedules', AccelerationRefreshScheduleController::class)->parameters([
            'refresh-schedules' => 'schedule',
        ]);
        Route::post('aggregates/{aggregate}/build', [AccelerationAggregateController::class, 'build']);
        Route::post('aggregates/{aggregate}/refresh', [AccelerationAggregateController::class, 'refresh']);
        Route::post('aggregates/{aggregate}/disable', [AccelerationAggregateController::class, 'disable']);
        Route::post('aggregates/{aggregate}/activate', [AccelerationAggregateController::class, 'activate']);
        Route::apiResource('aggregates', AccelerationAggregateController::class);
        Route::post('profiles/{profile}/test', [AccelerationProfileController::class, 'test']);
        Route::post('profiles/{profile}/build', [AccelerationProfileController::class, 'build']);
        Route::post('profiles/{profile}/refresh', [AccelerationProfileController::class, 'refresh']);
        Route::post('profiles/{profile}/disable', [AccelerationProfileController::class, 'disable']);
        Route::post('profiles/{profile}/activate', [AccelerationProfileController::class, 'activate']);
        Route::apiResource('profiles', AccelerationProfileController::class);
    });

    Route::post('charts/preview', [ChartController::class, 'preview']);
    Route::post('charts/{chart}/data', [ChartController::class, 'data']);
    Route::post('charts/{chart}/explain', [QueryExplainController::class, 'chart']);
    Route::apiResource('charts', ChartController::class);

    Route::post('dashboards/{dashboard}/widgets', [DashboardController::class, 'storeWidget']);
    Route::put('dashboards/{dashboard}/widgets/{widget}', [DashboardController::class, 'updateWidget']);
    Route::delete('dashboards/{dashboard}/widgets/{widget}', [DashboardController::class, 'destroyWidget']);
    Route::post('dashboards/{dashboard}/data', [DashboardController::class, 'data']);
    Route::post('dashboards/{dashboard}/share', [DashboardController::class, 'share']);
    Route::apiResource('dashboards', DashboardController::class);

    Route::post('import-tasks/{import_task}/retry', [ImportTaskController::class, 'retry']);
    Route::apiResource('import-tasks', ImportTaskController::class)->only(['index', 'store', 'show', 'destroy']);

    Route::get('export-tasks/{export_task}/download', [ExportTaskController::class, 'download']);
    Route::post('export-tasks/{export_task}/retry', [ExportTaskController::class, 'retry']);
    Route::apiResource('export-tasks', ExportTaskController::class)->only(['index', 'store', 'show']);

    Route::apiResource('resource-permissions', ResourcePermissionController::class);
    Route::apiResource('data-permission-rules', DataPermissionRuleController::class);
    Route::apiResource('column-permission-rules', ColumnPermissionRuleController::class);

    Route::get('operation-logs', [OperationLogController::class, 'index']);
    Route::get('login-logs', [LoginLogController::class, 'index']);
    Route::get('export-logs', [ExportLogController::class, 'index']);
    Route::get('query-logs', [QueryLogController::class, 'index']);

    Route::prefix('metadata')->group(function (): void {
        Route::get('assets', [MetadataAssetController::class, 'index']);
        Route::get('assets/{assetType}/{assetId}', [MetadataAssetController::class, 'show']);
        Route::put('assets/{assetType}/{assetId}', [MetadataAssetController::class, 'update']);
        Route::post('assets/{assetType}/{assetId}/archive', [MetadataAssetController::class, 'archive']);
        Route::post('assets/{assetType}/{assetId}/tags', [MetadataAssetController::class, 'attachTag']);
        Route::delete('assets/{assetType}/{assetId}/tags/{tag}', [MetadataAssetController::class, 'detachTag']);
        Route::get('assets/{assetType}/{assetId}/usage-stats', [MetadataAssetController::class, 'usageStats']);
        Route::get('search', MetadataSearchController::class);
        Route::get('lineage/{assetType}/{assetId}/upstream', [MetadataLineageController::class, 'upstream']);
        Route::get('lineage/{assetType}/{assetId}/downstream', [MetadataLineageController::class, 'downstream']);
        Route::get('lineage/{assetType}/{assetId}/graph', [MetadataLineageController::class, 'graph']);
        Route::post('lineage/{assetType}/{assetId}/sync', [MetadataLineageController::class, 'sync']);
        Route::post('impact/analyze', [MetadataImpactController::class, 'analyze']);
        Route::get('usage-stats', [MetadataUsageStatController::class, 'index']);
        Route::post('sync', MetadataSyncController::class);
        Route::apiResource('tags', MetadataTagController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
});
