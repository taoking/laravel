<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Access\Models\Role;
use App\Domains\Imports\Models\ImportTask;
use App\Domains\Metrics\Models\Metric;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardSummaryService
{
    public const string CACHE_KEY = 'dashboard:summary';

    public const int TTL_SECONDS = 60;

    /**
     * @return array{users: int, roles: int, metrics: int, import_tasks: int}
     */
    public function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, fn (): array => [
            'users' => User::query()->count(),
            'roles' => Role::query()->count(),
            'metrics' => Metric::query()->count(),
            'import_tasks' => ImportTask::query()->count(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
