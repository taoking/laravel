<?php

namespace App\Modules\Acceleration\Services;

use App\Models\User;
use App\Modules\Acceleration\Jobs\BuildAccelerationTableJob;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationTask;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccelerationProfileService
{
    public function __construct(
        private readonly AccelerationSchemaService $schemaService,
        private readonly AccelerationDriverManager $driverManager,
    ) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return AccelerationProfile::query()
            ->with(['dataset', 'tasks' => fn ($query) => $query->limit(1)])
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor = null): AccelerationProfile
    {
        $dataset = Dataset::query()->with('fields')->findOrFail($payload['dataset_id']);
        $profile = AccelerationProfile::query()->create([
            'dataset_id' => $dataset->id,
            'name' => $payload['name'] ?? $dataset->name.' Acceleration',
            'engine_type' => $payload['engine_type'] ?? config('bi_acceleration.default_engine', 'clickhouse'),
            'mode' => $payload['mode'] ?? 'detail_table',
            'status' => $payload['status'] ?? 'disabled',
            'source_connection_id' => $payload['source_connection_id'] ?? $dataset->data_source_id,
            'target_connection_id' => $payload['target_connection_id'] ?? null,
            'target_database' => $payload['target_database'] ?? config('bi_acceleration.clickhouse.database'),
            'target_table' => $this->targetTable($dataset, $payload['target_table'] ?? null),
            'refresh_type' => $payload['refresh_type'] ?? 'manual',
            'refresh_interval_minutes' => $payload['refresh_interval_minutes'] ?? null,
            'config_json' => $payload['config_json'] ?? [],
        ]);

        $this->schemaService->syncColumns($profile, $payload['columns'] ?? []);

        return $profile->refresh()->load(['dataset', 'columns', 'tasks']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(AccelerationProfile $profile, array $payload): AccelerationProfile
    {
        if (array_key_exists('target_table', $payload)) {
            $this->assertIdentifier((string) $payload['target_table'], 'target_table');
        }

        if (array_key_exists('target_database', $payload) && $payload['target_database'] !== null) {
            $this->assertIdentifier((string) $payload['target_database'], 'target_database');
        }

        $profile->fill(collect($payload)->except(['columns'])->all());
        $profile->version++;
        $profile->save();

        if (array_key_exists('columns', $payload)) {
            $this->schemaService->syncColumns($profile, $payload['columns'] ?? []);
        }

        return $profile->refresh()->load(['dataset', 'columns', 'tasks']);
    }

    public function delete(AccelerationProfile $profile): void
    {
        $profile->delete();
    }

    public function test(AccelerationProfile $profile): bool
    {
        return $this->driverManager->driver($profile)->testConnection();
    }

    public function build(AccelerationProfile $profile, ?User $actor = null, string $taskType = 'full_sync'): AccelerationTask
    {
        $profile->forceFill([
            'status' => 'building',
            'last_error_message' => null,
        ])->save();

        $task = $profile->tasks()->create([
            'task_type' => $taskType,
            'status' => 'pending',
            'created_by' => $actor?->id,
        ]);

        BuildAccelerationTableJob::dispatch($task->id);

        return $task->refresh()->load('profile');
    }

    public function disable(AccelerationProfile $profile): AccelerationProfile
    {
        $profile->forceFill([
            'status' => 'disabled',
            'version' => $profile->version + 1,
        ])->save();

        return $profile->refresh()->load(['dataset', 'columns', 'tasks']);
    }

    public function activate(AccelerationProfile $profile): AccelerationProfile
    {
        $profile->forceFill([
            'status' => 'active',
            'version' => $profile->version + 1,
        ])->save();

        return $profile->refresh()->load(['dataset', 'columns', 'tasks']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{profile: AccelerationProfile, task: AccelerationTask}
     */
    public function buildForDataset(Dataset $dataset, array $payload, ?User $actor): array
    {
        $profile = isset($payload['profile_id'])
            ? AccelerationProfile::query()->where('dataset_id', $dataset->id)->findOrFail($payload['profile_id'])
            : AccelerationProfile::query()->where('dataset_id', $dataset->id)->latest('id')->first();

        if (! $profile instanceof AccelerationProfile) {
            $profile = $this->create([
                ...$payload,
                'dataset_id' => $dataset->id,
                'name' => $payload['name'] ?? $dataset->name.' ClickHouse Detail',
                'engine_type' => $payload['engine_type'] ?? 'clickhouse',
                'mode' => $payload['mode'] ?? 'detail_table',
            ], $actor);
        } elseif (array_key_exists('columns', $payload) || array_key_exists('config_json', $payload)) {
            $profile = $this->update($profile, $payload);
        }

        return [
            'profile' => $profile,
            'task' => $this->build($profile, $actor),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function columnPreview(Dataset $dataset): array
    {
        return $this->schemaService->preview($dataset);
    }

    private function targetTable(Dataset $dataset, ?string $targetTable): string
    {
        if ($targetTable !== null && $targetTable !== '') {
            $this->assertIdentifier($targetTable, 'target_table');

            return $targetTable;
        }

        return 'dataset_'.$dataset->id.'_detail_'.Str::lower(Str::random(8));
    }

    private function assertIdentifier(string $identifier, string $field): void
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw ValidationException::withMessages([
                $field => ["Identifier [{$identifier}] is not allowed."],
            ]);
        }
    }
}
