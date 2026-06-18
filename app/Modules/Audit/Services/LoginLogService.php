<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\LoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoginLogService
{
    public function record(?User $user, ?string $ip, ?string $userAgent, string $status, ?string $message = null): void
    {
        LoginLog::query()->create([
            'tenant_id' => $user?->organization_id,
            'user_id' => $user?->id,
            'login_ip' => $ip,
            'user_agent' => $userAgent,
            'status' => $status,
            'message' => $message,
            'created_at' => now(),
        ]);
    }

    public function paginate(int $pageSize, array $filters = []): LengthAwarePaginator
    {
        return LoginLog::query()
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate($pageSize);
    }
}
