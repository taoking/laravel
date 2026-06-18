<?php

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
use App\Modules\Export\Controllers\ExportTaskController;
use App\Modules\Import\Controllers\ImportTaskController;
use App\Modules\Monitor\Controllers\HealthController;
use App\Modules\Monitor\Controllers\MetricsController;
use App\Modules\Permission\Controllers\PermissionController;
use App\Modules\Permission\Controllers\RoleController;
use App\Modules\Query\Controllers\QueryController;
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
    Route::get('data-sources/{data_source}/tables', [DataSourceController::class, 'tables']);
    Route::get('data-sources/{data_source}/tables/{table}/fields', [DataSourceController::class, 'fields']);
    Route::apiResource('data-sources', DataSourceController::class);

    Route::post('datasets/{dataset}/sync-fields', [DatasetController::class, 'syncFields']);
    Route::get('datasets/{dataset}/fields', [DatasetController::class, 'fields']);
    Route::put('datasets/{dataset}/fields/{field}', [DatasetController::class, 'updateField']);
    Route::post('datasets/{dataset}/preview', [DatasetController::class, 'preview']);
    Route::apiResource('datasets', DatasetController::class);

    Route::post('query/execute', [QueryController::class, 'execute']);

    Route::post('charts/preview', [ChartController::class, 'preview']);
    Route::post('charts/{chart}/data', [ChartController::class, 'data']);
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

    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
});
