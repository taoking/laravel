<?php

namespace App\Modules\Dataset\Services;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Services\QueryOrchestrator;
use Illuminate\Validation\ValidationException;

class DatasetPreviewService
{
    public function __construct(private readonly QueryOrchestrator $queryOrchestrator) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, limit: int}
     */
    public function preview(Dataset $dataset, array $payload = [], ?User $actor = null): array
    {
        $dataset->loadMissing(['dataSource', 'fields']);
        $limit = min(max((int) ($payload['limit'] ?? 100), 1), 100);
        $requestedFields = $payload['fields'] ?? [];
        $availableFields = $dataset->fields->pluck('field_name')->all();

        if ($requestedFields !== [] && array_diff($requestedFields, $availableFields) !== []) {
            throw ValidationException::withMessages([
                'fields' => ['The selected fields are not part of this dataset.'],
            ]);
        }

        $fields = $dataset->fields
            ->where('is_visible', true)
            ->when($requestedFields !== [], fn ($collection) => $collection->whereIn('field_name', $requestedFields))
            ->sortBy('sort_order')
            ->values();

        if ($fields->isEmpty()) {
            throw ValidationException::withMessages([
                'fields' => ['No visible fields are available for preview.'],
            ]);
        }

        $columns = $fields->pluck('field_name')->values()->all();
        $result = $this->queryOrchestrator->execute([
            'dataset_id' => $dataset->id,
            'raw_fields' => $columns,
            'limit' => $limit,
            'use_cache' => false,
        ], $actor, [
            'request_source' => 'dataset_preview',
        ]);

        return [
            'columns' => $columns,
            'rows' => $result['rows'],
            'limit' => $limit,
        ];
    }
}
