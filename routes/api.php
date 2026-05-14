<?php

use App\Http\Controllers\Api\V1\AccessController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Imports\ExportTaskController;
use App\Http\Controllers\Api\V1\Imports\ImportTaskController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\Metrics\DimensionController;
use App\Http\Controllers\Api\V1\Metrics\MetricCategoryController;
use App\Http\Controllers\Api\V1\Metrics\MetricController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\Security\SignedEchoController;
use App\Http\Controllers\Api\V1\Security\UrlSafetyController;
use App\Http\Controllers\Api\V1\UserController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

// 学习要点：API 路由用于稳定对外契约，后续所有接口都挂在 /api/v1 下。
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', function () {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => 'metrics-platform-api',
            'version' => 'v1',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('health');

    Route::post('/security/signed-echo', SignedEchoController::class)
        ->middleware('signed.api')
        ->name('security.signed-echo');

    Route::middleware('auth:web')->group(function (): void {
        Route::get('/me', [AccessController::class, 'me'])->name('me');
        Route::get('/permissions', [AccessController::class, 'permissions'])->name('permissions');
        Route::get('/permissions/catalog', [AccessController::class, 'catalog'])
            ->middleware('permission:access.roles.view')
            ->name('permissions.catalog');

        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:access.users.view')
            ->name('users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:access.users.manage')
            ->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:access.users.manage')
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:access.users.manage')
            ->name('users.destroy');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:access.roles.view')
            ->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:access.roles.manage')
            ->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:access.roles.manage')
            ->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:access.roles.manage')
            ->name('roles.destroy');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->middleware('permission:access.roles.manage')
            ->name('roles.permissions.update');

        Route::put('/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:access.menus.manage')
            ->name('menus.update');

        Route::get('/metrics', [MetricController::class, 'index'])
            ->middleware(['permission:metrics.view', 'throttle:metrics-query'])
            ->name('metrics.index');
        Route::post('/metrics', [MetricController::class, 'store'])
            ->middleware('permission:metrics.manage')
            ->name('metrics.store');
        Route::get('/metrics/{metric}', [MetricController::class, 'show'])
            ->middleware('permission:metrics.view')
            ->name('metrics.show');
        Route::put('/metrics/{metric}', [MetricController::class, 'update'])
            ->middleware('permission:metrics.manage')
            ->name('metrics.update');
        Route::delete('/metrics/{metric}', [MetricController::class, 'destroy'])
            ->middleware('permission:metrics.manage')
            ->name('metrics.destroy');

        Route::get('/metric-categories', [MetricCategoryController::class, 'index'])
            ->middleware('permission:metrics.view')
            ->name('metric-categories.index');
        Route::get('/dimensions/regions', [DimensionController::class, 'regions'])
            ->middleware('permission:metrics.view')
            ->name('dimensions.regions');
        Route::get('/dimensions/frequencies', [DimensionController::class, 'frequencies'])
            ->middleware('permission:metrics.view')
            ->name('dimensions.frequencies');

        Route::get('/imports', [ImportTaskController::class, 'index'])
            ->middleware('permission:imports.view')
            ->name('imports.index');
        Route::post('/imports', [ImportTaskController::class, 'store'])
            ->middleware('permission:imports.manage')
            ->name('imports.store');
        Route::get('/imports/{import}', [ImportTaskController::class, 'show'])
            ->middleware('permission:imports.view')
            ->name('imports.show');
        Route::post('/imports/{import}/retry', [ImportTaskController::class, 'retry'])
            ->middleware('permission:imports.manage')
            ->name('imports.retry');

        Route::post('/exports', [ExportTaskController::class, 'store'])
            ->middleware('permission:exports.manage')
            ->name('exports.store');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view')
            ->name('audit-logs.index');

        Route::post('/security/url-check', UrlSafetyController::class)
            ->middleware('permission:audit.view')
            ->name('security.url-check');
    });
});
