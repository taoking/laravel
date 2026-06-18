<?php

namespace App\Modules\Import\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class ExcelParser
{
    /**
     * @param  resource  $stream
     * @return array{headers: list<string>, rows: list<array<string, mixed>>}
     */
    public function parse(mixed $stream): array
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('The Excel stream is invalid.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'import_xlsx_');

        if ($temporaryFile === false) {
            throw new RuntimeException('Failed to create a temporary Excel file.');
        }

        file_put_contents($temporaryFile, stream_get_contents($stream));

        try {
            return $this->parseFile($temporaryFile);
        } finally {
            @unlink($temporaryFile);
        }
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, mixed>>}
     */
    private function parseFile(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Excel file cannot be opened.');
        }

        try {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheetXml === false) {
                throw new RuntimeException('The Excel file does not contain a first worksheet.');
            }

            $sharedStrings = $this->sharedStrings($zip);
            $worksheet = simplexml_load_string($sheetXml);

            if (! $worksheet instanceof SimpleXMLElement) {
                throw new RuntimeException('The Excel worksheet XML is invalid.');
            }

            return $this->rows($worksheet, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);

        if (! $shared instanceof SimpleXMLElement) {
            return [];
        }

        $namespace = $this->defaultNamespace($shared);
        $strings = [];

        foreach ($shared->children($namespace)->si as $item) {
            $texts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';

            foreach ($texts as $text) {
                $value .= (string) $text;
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array{headers: list<string>, rows: list<array<string, mixed>>}
     */
    private function rows(SimpleXMLElement $worksheet, array $sharedStrings): array
    {
        $namespace = $this->defaultNamespace($worksheet);
        $sheetData = $worksheet->children($namespace)->sheetData;
        $rawRows = [];

        foreach ($sheetData->children($namespace)->row as $row) {
            $cells = [];

            foreach ($row->children($namespace)->c as $cell) {
                $reference = (string) $cell['r'];
                $index = $this->columnIndex($reference);
                $cells[$index] = $this->cellValue($cell, $sharedStrings, $namespace);
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $rawRows[] = $this->denseRow($cells);
        }

        if ($rawRows === []) {
            throw new RuntimeException('The Excel file is empty.');
        }

        $headers = $this->normalizeHeaders(array_shift($rawRows) ?? []);
        $rows = [];

        foreach ($rawRows as $rawRow) {
            if ($this->isBlankRow($rawRow)) {
                continue;
            }

            $rawRow = array_pad($rawRow, count($headers), null);
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = $this->normalizeCell($rawRow[$index] ?? null);
            }

            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, string|null>  $cells
     * @return list<string|null>
     */
    private function denseRow(array $cells): array
    {
        $max = max(array_keys($cells));
        $row = [];

        for ($index = 0; $index <= $max; $index++) {
            $row[] = $cells[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param  list<mixed>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $seen = [];

        return array_map(function (mixed $header, int $index) use (&$seen): string {
            $name = trim((string) $header);
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

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings, string $namespace): ?string
    {
        $type = (string) $cell['t'];
        $children = $cell->children($namespace);
        $rawValue = isset($children->v) ? (string) $children->v : null;

        if ($type === 's' && $rawValue !== null) {
            return $sharedStrings[(int) $rawValue] ?? '';
        }

        if ($type === 'b' && $rawValue !== null) {
            return $rawValue === '1' ? 'true' : 'false';
        }

        if ($type === 'inlineStr') {
            $texts = $cell->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';

            foreach ($texts as $text) {
                $value .= (string) $text;
            }

            return $value;
        }

        return $rawValue;
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    private function defaultNamespace(SimpleXMLElement $element): string
    {
        $namespaces = $element->getNamespaces();

        return $namespaces[''] ?? '';
    }
}
