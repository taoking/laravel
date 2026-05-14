<?php

use App\Domains\Access\Models\Role;
use App\Domains\Metrics\Models\Metric;
use App\Http\Controllers\AuthSessionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 学习要点：routes/web.php 定义面向浏览器的 Web 路由。
// 新骨架通过 bootstrap/app.php 的 withRouting(web: ...) 加载本文件。
// 面试时可以把 Route Facade 解释为“静态语法代理容器中的 router 服务”。
Route::get('/', function (Request $request) {
    return redirect()->route($request->user() ? 'admin.dashboard' : 'login');
});

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->middleware('guest')->name('login');

Route::post('/login', [AuthSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('login.store');

Route::post('/logout', [AuthSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin', function () {
        $summary = Cache::remember('dashboard:summary', 60, fn () => [
            'users' => User::query()->count(),
            'roles' => Role::query()->count(),
            'metrics' => Metric::query()->count(),
            'jobs' => 0,
        ]);

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'stages' => [
                ['name' => 'Phase 1', 'area' => 'Scaffold', 'status' => 'done'],
                ['name' => 'Phase 2', 'area' => 'Auth and RBAC', 'status' => 'in_progress'],
                ['name' => 'Phase 3', 'area' => 'Metrics and Import', 'status' => 'planned'],
                ['name' => 'Phase 4', 'area' => 'Cache and Queue', 'status' => 'planned'],
            ],
        ]);
    })->name('admin.dashboard');

    Route::get('/admin/users', fn () => Inertia::render('Access/Users'))->name('admin.users');
    Route::get('/admin/roles', fn () => Inertia::render('Access/Roles'))->name('admin.roles');
    Route::get('/admin/menus', fn () => Inertia::render('Access/Menus'))->name('admin.menus');
    Route::get('/admin/metrics', fn () => Inertia::render('Metrics/Index'))->name('admin.metrics');
    Route::get('/admin/imports', fn () => Inertia::render('Imports/Index'))->name('admin.imports');
    Route::get('/admin/audit-logs', fn () => Inertia::render('Audit/Index'))->name('admin.audit-logs');
});

Route::get('/docs/api', function () {
    return view('docs.api');
})->name('docs.api');

Route::get('/docs/openapi.yaml', function () {
    return response(file_get_contents(public_path('docs/openapi.yaml')), 200, [
        'Content-Type' => 'application/yaml',
    ]);
})->name('docs.openapi');
