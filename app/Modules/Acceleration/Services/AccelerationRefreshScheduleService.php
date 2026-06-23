<?php

namespace App\Modules\Acceleration\Services;

use App\Models\User;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationRefreshSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AccelerationRefreshScheduleService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $pageSize = 20): LengthAwarePaginator
    {
        return AccelerationRefreshSchedule::query()
            ->with('lastTask')
            ->when(isset($filters['target_type']), fn ($query) => $query->where('target_type', $filters['target_type']))
            ->when(isset($filters['target_id']), fn ($query) => $query->where('target_id', $filters['target_id']))
            ->when(isset($filters['enabled']), fn ($query) => $query->where('enabled', (bool) $filters['enabled']))
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor = null): AccelerationRefreshSchedule
    {
        $this->assertTargetExists((string) $payload['target_type'], (int) $payload['target_id']);
        $refreshType = (string) ($payload['refresh_type'] ?? 'manual');
        $enabled = (bool) ($payload['enabled'] ?? true);

        $schedule = AccelerationRefreshSchedule::query()->create([
            'target_type' => (string) $payload['target_type'],
            'target_id' => (int) $payload['target_id'],
            'refresh_type' => $refreshType,
            'cron_expression' => $payload['cron_expression'] ?? null,
            'enabled' => $enabled,
            'next_run_at' => $payload['next_run_at'] ?? ($enabled ? $this->nextRunAt($refreshType, now()) : null),
            'created_by' => $actor?->id,
        ]);

        return $schedule->refresh()->load('lastTask');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(AccelerationRefreshSchedule $schedule, array $payload): AccelerationRefreshSchedule
    {
        $targetType = (string) ($payload['target_type'] ?? $schedule->target_type);
        $targetId = (int) ($payload['target_id'] ?? $schedule->target_id);
        $this->assertTargetExists($targetType, $targetId);
        $refreshType = (string) ($payload['refresh_type'] ?? $schedule->refresh_type);
        $enabled = array_key_exists('enabled', $payload) ? (bool) $payload['enabled'] : $schedule->enabled;

        $schedule->fill([
            ...collect($payload)->only(['cron_expression'])->all(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'refresh_type' => $refreshType,
            'enabled' => $enabled,
            'next_run_at' => array_key_exists('next_run_at', $payload)
                ? $payload['next_run_at']
                : ($enabled ? ($schedule->next_run_at ?? $this->nextRunAt($refreshType, now())) : null),
        ]);
        $schedule->save();

        return $schedule->refresh()->load('lastTask');
    }

    public function delete(AccelerationRefreshSchedule $schedule): void
    {
        $schedule->delete();
    }

    public function enable(AccelerationRefreshSchedule $schedule): AccelerationRefreshSchedule
    {
        $schedule->forceFill([
            'enabled' => true,
            'next_run_at' => $schedule->next_run_at ?? $this->nextRunAt($schedule->refresh_type, now()),
        ])->save();

        return $schedule->refresh()->load('lastTask');
    }

    public function disable(AccelerationRefreshSchedule $schedule): AccelerationRefreshSchedule
    {
        $schedule->forceFill(['enabled' => false])->save();

        return $schedule->refresh()->load('lastTask');
    }

    /**
     * @return Collection<int, AccelerationRefreshSchedule>
     */
    public function dueSchedules()
    {
        if (! (bool) config('bi_acceleration.refresh.enabled', true)) {
            return AccelerationRefreshSchedule::query()->whereRaw('1 = 0')->get();
        }

        return AccelerationRefreshSchedule::query()
            ->where('enabled', true)
            ->where('refresh_type', '!=', 'manual')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();
    }

    public function markDispatched(AccelerationRefreshSchedule $schedule, ?int $taskId): AccelerationRefreshSchedule
    {
        $schedule->forceFill([
            'last_run_at' => now(),
            'next_run_at' => $this->nextRunAt($schedule->refresh_type, now()),
            'last_task_id' => $taskId,
            'last_status' => 'dispatched',
            'last_error_message' => null,
        ])->save();

        return $schedule->refresh();
    }

    public function markFailed(AccelerationRefreshSchedule $schedule, string $message): AccelerationRefreshSchedule
    {
        $schedule->forceFill([
            'last_run_at' => now(),
            'next_run_at' => $this->nextRunAt($schedule->refresh_type, now()),
            'last_status' => 'failed',
            'last_error_message' => $message,
        ])->save();

        return $schedule->refresh();
    }

    public function nextRunAt(string $refreshType, Carbon $from): ?Carbon
    {
        return match ($refreshType) {
            'hourly' => $from->copy()->addHour(),
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            default => null,
        };
    }

    private function assertTargetExists(string $targetType, int $targetId): void
    {
        $exists = match ($targetType) {
            'detail_profile' => AccelerationProfile::query()
                ->whereKey($targetId)
                ->where('mode', 'detail_table')
                ->exists(),
            'aggregate_definition' => AccelerationAggregateDefinition::query()->whereKey($targetId)->exists(),
            default => false,
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                'target_id' => ["Refresh target [{$targetType}:{$targetId}] does not exist."],
            ]);
        }
    }
}
