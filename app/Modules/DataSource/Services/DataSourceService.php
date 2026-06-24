<?php

namespace App\Modules\DataSource\Services;

use App\Models\User;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\Metadata\Services\MetadataChangeGuardService;
use App\Modules\Metadata\Services\MetadataLifecycleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DataSourceService
{
    public function __construct(
        private readonly DataSourcePasswordEncryptor $passwordEncryptor,
        private readonly DataSourceMetadataService $metadataService,
        private readonly MetadataLifecycleService $metadataLifecycleService,
        private readonly MetadataChangeGuardService $metadataChangeGuardService,
    ) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return DataSource::query()
            ->withCount(['tables', 'fields'])
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): DataSource
    {
        $dataSource = DB::transaction(function () use ($payload, $actor): DataSource {
            $payload['type'] ??= 'mysql';
            $payload['port'] ??= $this->defaultPort((string) $payload['type']);
            $payload['charset'] ??= 'utf8mb4';
            $payload['timezone'] ??= '+00:00';
            $payload['status'] ??= 'active';
            $payload['created_by'] = $actor?->id;
            $payload['updated_by'] = $actor?->id;

            $password = Arr::pull($payload, 'password', null);
            $payload['password_encrypted'] = $this->passwordEncryptor->encrypt($password);

            return DataSource::query()->create($payload);
        });

        $this->metadataLifecycleService->sync('data_source', (int) $dataSource->id);

        return $dataSource;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(DataSource $dataSource, array $payload, ?User $actor): DataSource
    {
        $updatedDataSource = DB::transaction(function () use ($dataSource, $payload, $actor): DataSource {
            if (array_key_exists('password', $payload)) {
                $password = Arr::pull($payload, 'password');

                if ($password !== null && $password !== '') {
                    $payload['password_encrypted'] = $this->passwordEncryptor->encrypt($password);
                }
            }

            $payload['updated_by'] = $actor?->id;

            $dataSource->fill($payload);
            $dataSource->save();

            $this->metadataService->forget($dataSource);

            return $dataSource->refresh()->loadCount(['tables', 'fields']);
        });

        $this->metadataLifecycleService->sync('data_source', (int) $updatedDataSource->id);

        return $updatedDataSource;
    }

    public function delete(DataSource $dataSource, ?User $actor = null, bool $force = false): void
    {
        $assetId = (int) $dataSource->id;
        $this->metadataChangeGuardService->guardDelete('data_source', $assetId, $actor, $force);

        DB::transaction(function () use ($dataSource): void {
            $this->metadataService->forget($dataSource);
            $dataSource->fields()->delete();
            $dataSource->tables()->delete();
            $dataSource->delete();
        });

        $this->metadataLifecycleService->archive('data_source', $assetId);
    }

    private function defaultPort(string $type): int
    {
        return match ($type) {
            'starrocks', 'doris' => 9030,
            default => 3306,
        };
    }
}
