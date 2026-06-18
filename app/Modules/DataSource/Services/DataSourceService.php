<?php

namespace App\Modules\DataSource\Services;

use App\Models\User;
use App\Modules\DataSource\Models\DataSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DataSourceService
{
    public function __construct(
        private readonly DataSourcePasswordEncryptor $passwordEncryptor,
        private readonly DataSourceMetadataService $metadataService,
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
        return DB::transaction(function () use ($payload, $actor): DataSource {
            $payload['type'] ??= 'mysql';
            $payload['port'] ??= 3306;
            $payload['charset'] ??= 'utf8mb4';
            $payload['timezone'] ??= '+00:00';
            $payload['status'] ??= 'active';
            $payload['created_by'] = $actor?->id;
            $payload['updated_by'] = $actor?->id;

            $password = Arr::pull($payload, 'password', null);
            $payload['password_encrypted'] = $this->passwordEncryptor->encrypt($password);

            return DataSource::query()->create($payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(DataSource $dataSource, array $payload, ?User $actor): DataSource
    {
        return DB::transaction(function () use ($dataSource, $payload, $actor): DataSource {
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
    }

    public function delete(DataSource $dataSource): void
    {
        DB::transaction(function () use ($dataSource): void {
            $this->metadataService->forget($dataSource);
            $dataSource->fields()->delete();
            $dataSource->tables()->delete();
            $dataSource->delete();
        });
    }
}
