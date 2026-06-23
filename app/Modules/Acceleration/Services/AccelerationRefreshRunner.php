<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationRefreshSchedule;
use App\Modules\Acceleration\Models\AccelerationTask;
use Throwable;

class AccelerationRefreshRunner
{
    public function __construct(
        private readonly AccelerationRefreshScheduleService $scheduleService,
        private readonly AccelerationProfileService $profileService,
        private readonly AggregateDefinitionService $aggregateDefinitionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function runDue(bool $dryRun = false): array
    {
        $due = $this->scheduleService->dueSchedules();
        $dispatched = 0;
        $failed = 0;
        $items = [];

        foreach ($due as $schedule) {
            if ($dryRun) {
                $items[] = [
                    'schedule_id' => $schedule->id,
                    'target_type' => $schedule->target_type,
                    'target_id' => $schedule->target_id,
                    'status' => 'due',
                ];

                continue;
            }

            try {
                $task = $this->dispatchSchedule($schedule);
                $this->scheduleService->markDispatched($schedule, $task?->id);
                $dispatched++;
                $items[] = [
                    'schedule_id' => $schedule->id,
                    'task_id' => $task?->id,
                    'status' => 'dispatched',
                ];
            } catch (Throwable $exception) {
                $this->scheduleService->markFailed($schedule, $exception->getMessage());
                $failed++;
                $items[] = [
                    'schedule_id' => $schedule->id,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'due_count' => $due->count(),
            'dispatched_count' => $dispatched,
            'failed_count' => $failed,
            'dry_run' => $dryRun,
            'items' => $items,
        ];
    }

    public function runSchedule(AccelerationRefreshSchedule $schedule): ?AccelerationTask
    {
        $task = $this->dispatchSchedule($schedule);
        $this->scheduleService->markDispatched($schedule, $task?->id);

        return $task;
    }

    private function dispatchSchedule(AccelerationRefreshSchedule $schedule): ?AccelerationTask
    {
        if ($schedule->target_type === 'detail_profile') {
            $profile = AccelerationProfile::query()
                ->whereKey($schedule->target_id)
                ->where('mode', 'detail_table')
                ->firstOrFail();

            return $this->profileService->build($profile, taskType: 'full_sync');
        }

        if ($schedule->target_type === 'aggregate_definition') {
            $definition = AccelerationAggregateDefinition::query()->findOrFail($schedule->target_id);

            return $this->aggregateDefinitionService->refresh($definition);
        }

        throw new \RuntimeException("Unsupported refresh target type [{$schedule->target_type}].");
    }
}
