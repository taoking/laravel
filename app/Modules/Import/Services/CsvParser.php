<?php

namespace App\Modules\Import\Services;

use RuntimeException;

class CsvParser
{
    /**
     * @param  resource  $stream
     * @return array{headers: list<string>, rows: list<array<string, mixed>>}
     */
    public function parse(mixed $stream): array
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('The CSV stream is invalid.');
        }

        $headers = fgetcsv($stream);

        if ($headers === false) {
            throw new RuntimeException('The CSV file is empty.');
        }

        $headers = $this->normalizeHeaders($headers);
        $rows = [];

        while (($values = fgetcsv($stream)) !== false) {
            if ($this->isBlankRow($values)) {
                continue;
            }

            $values = array_pad($values, count($headers), null);
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = $this->normalizeCell($values[$index] ?? null);
            }

            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<mixed>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $seen = [];

        return array_map(function (mixed $header, int $index) use (&$seen): string {
            $name = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header));
            $name = $name !== '' ? $name : 'column_'.($index + 1);
            $base = $name;
            $suffix = 2;

            while (isset($seen[$name])) {
                $name = $base.'_'.$suffix;
                $suffix++;
            }

            $seen[$name] = true;

            return $name;
        }, $headers, array_keys($headers));
    }

    /**
     * @param  list<mixed>  $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }
}
