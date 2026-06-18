<?php

namespace App\Modules\Dataset\Services;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Validation\ValidationException;

class DatasetPreviewService
{
    public function __construct(private readonly DataSourceConnectionFactory $connectionFactory) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, limit: int}
     */
    public function preview(Dataset $dataset, array $payload = []): array
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

        if (! IdentifierGuard::isSafe($dataset->main_table)) {
            throw ValidationException::withMessages([
                'main_table' => ['The table name is not allowed.'],
            ]);
        }

        foreach ($fields as $field) {
            if ($field->table_name !== $dataset->main_table || ! IdentifierGuard::isSafe($field->field_name)) {
                throw ValidationException::withMessages([
                    'fields' => ['The selected fields are not allowed.'],
                ]);
            }
        }

        $columns = $fields->pluck('field_name')->values()->all();
        $selectSql = collect($columns)
            ->map(fn (string $fieldName): string => $this->quoteIdentifier($fieldName))
            ->implode(', ');
        $sql = sprintf('select %s from %s limit %d', $selectSql, $this->quoteIdentifier($dataset->main_table), $limit);

        $connection = $this->connectionFactory->make($dataset->dataSource);

        try {
            $rows = collect($connection->select($sql))
                ->map(fn (object|array $row): array => (array) $row)
                ->values()
                ->all();
        } finally {
            $this->connectionFactory->disconnect($dataset->dataSource);
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'limit' => $limit,
        ];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.$identifier.'`';
    }
}
