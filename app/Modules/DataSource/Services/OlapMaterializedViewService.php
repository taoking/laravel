<?php

namespace App\Modules\DataSource\Services;

use App\Modules\DataSource\Models\DataSource;
use Illuminate\Validation\ValidationException;

class OlapMaterializedViewService
{
    public function __construct(private readonly DataSourceMetadataService $metadataService) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(DataSource $dataSource): array
    {
        $this->assertOlapSource($dataSource);

        return $this->metadataService->materializedViews($dataSource);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(DataSource $dataSource, string $name): ?array
    {
        $this->assertOlapSource($dataSource);

        return $this->metadataService->materializedView($dataSource, $name);
    }

    /**
     * @return array{refreshed: bool, message: string}
     */
    public function refresh(DataSource $dataSource, string $name): array
    {
        $this->assertOlapSource($dataSource);

        return $this->metadataService->refreshMaterializedView($dataSource, $name);
    }

    private function assertOlapSource(DataSource $dataSource): void
    {
        if (! in_array($dataSource->type, ['starrocks', 'doris'], true)) {
            throw ValidationException::withMessages([
                'data_source' => ['Materialized view metadata is supported for StarRocks and Doris data sources.'],
            ]);
        }
    }
}
