<?php

namespace App\Modules\Import\Services;

use Illuminate\Support\Str;
use RuntimeException;

class ImportSchemaInferService
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{original_name: string, field_name: string, data_type: string, normalized_type: string, ordinal_position: int}>
     */
    public function infer(array $headers, array $rows): array
    {
        if ($headers === []) {
            throw new RuntimeException('The import file must contain a header row.');
        }

        $usedFieldNames = [];
        $schema = [];

        foreach ($headers as $index => $header) {
            $normalizedType = $this->inferType(array_column($rows, $header));
            $fieldName = $this->fieldName($header, $index, $usedFieldNames);

            $schema[] = [
                'original_name' => $header,
                'field_name' => $fieldName,
                'data_type' => $this->dataType($normalizedType),
                'normalized_type' => $normalizedType,
                'ordinal_position' => $index + 1,
            ];
        }

        return $schema;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function inferType(array $values): string
    {
        $values = array_values(array_filter($values, fn (mixed $value): bool => trim((string) $value) !== ''));

        if ($values === []) {
            return 'string';
        }

        if ($this->all($values, fn (string $value): bool => preg_match('/\A-?\d+\z/', $value) === 1)) {
            return 'integer';
        }

        if ($this->all($values, fn (string $value): bool => is_numeric($value))) {
            return 'decimal';
        }

        if ($this->all($values, fn (string $value): bool => in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'y', 'n'], true))) {
            return 'boolean';
        }

        if ($this->all($values, fn (string $value): bool => $this->looksLikeDateTime($value))) {
            return 'datetime';
        }

        return 'string';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function all(array $values, callable $callback): bool
    {
        foreach ($values as $value) {
            if (! $callback(trim((string) $value))) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeDateTime(string $value): bool
    {
        if (preg_match('/\A\d+\z/', $value) === 1) {
            return false;
        }

        if (preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $value) !== 1) {
            return false;
        }

        return strtotime($value) !== false;
    }

    private function dataType(string $normalizedType): string
    {
        return match ($normalizedType) {
            'integer' => 'bigint',
            'decimal' => 'decimal(20,6)',
            'boolean' => 'boolean',
            'datetime' => 'datetime',
            default => 'text',
        };
    }

    /**
     * @param  array<string, true>  $usedFieldNames
     */
    private function fieldName(string $header, int $index, array &$usedFieldNames): string
    {
        $fieldName = Str::of($header)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->toString();

        if ($fieldName === '') {
            $fieldName = 'column_'.($index + 1);
        }

        if (preg_match('/\A[0-9]/', $fieldName) === 1) {
            $fieldName = 'col_'.$fieldName;
        }

        $base = $fieldName;
        $suffix = 2;

        while (isset($usedFieldNames[$fieldName])) {
            $fieldName = $base.'_'.$suffix;
            $suffix++;
        }

        $usedFieldNames[$fieldName] = true;

        return $fieldName;
    }
}
