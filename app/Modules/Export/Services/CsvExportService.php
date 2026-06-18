<?php

namespace App\Modules\Export\Services;

class CsvExportService
{
    /**
     * @param  list<array{name: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function build(array $columns, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        $fieldNames = array_map(fn (array $column): string => $column['name'], $columns);
        fputcsv($stream, array_map(fn (array $column): string => $column['label'] ?: $column['name'], $columns));

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $field): mixed => $this->stringify($row[$field] ?? null), $fieldNames));
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content !== false ? $content : '';
    }

    private function stringify(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }
}
