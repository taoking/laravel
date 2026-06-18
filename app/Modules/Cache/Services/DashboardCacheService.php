<?php

namespace App\Modules\Cache\Services;

use App\Modules\Dashboard\Models\Dashboard;
use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    public function __construct(private readonly CacheKeyBuilder $keyBuilder) {}

    public function forget(Dashboard|int $dashboard): void
    {
        $dashboardId = $dashboard instanceof Dashboard ? (int) $dashboard->id : $dashboard;

        Cache::forget($this->keyBuilder->dashboardLayout($dashboardId));
    }
}
