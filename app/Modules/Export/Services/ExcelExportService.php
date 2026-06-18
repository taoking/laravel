<?php

namespace App\Modules\Export\Services;

use RuntimeException;
use ZipArchive;

class ExcelExportService
{
    /**
     * @param  list<array{name: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function build(array $columns, array $rows): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'export_xlsx_');

        if ($temporaryFile === false) {
            throw new RuntimeException('Failed to create a temporary Excel file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryFile);

            throw new RuntimeException('Failed to create the Excel archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($columns, $rows));
        $zip->close();

        $content = file_get_contents($temporaryFile);
        @unlink($temporaryFile);

        if ($content === false) {
            throw new RuntimeException('Failed to read the Excel archive.');
        }

        return $content;
    }

    /**
     * @param  list<array{name: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function sheet(array $columns, array $rows): string
    {
        $fieldNames = array_map(fn (array $column): string => $column['name'], $columns);
        $sheetRows = [$this->row(1, array_map(fn (array $column): mixed => $column['label'] ?: $column['name'], $columns))];
        $rowNumber = 2;

        foreach ($rows as $row) {
            $sheetRows[] = $this->row($rowNumber, array_map(fn (string $field): mixed => $row[$field] ?? null, $fieldNames));
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  list<mixed>  $values
     */
    private function row(int $rowNumber, array $values): string
    {
        $cells = [];

        foreach ($values as $index => $value) {
            $cells[] = $this->cell($this->columnName($index).$rowNumber, $value);
        }

        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function cell(string $reference, mixed $value): string
    {
        return '<c r="'.$reference.'" t="inlineStr"><is><t>'.$this->escape($this->stringify($value)).'</t></is></c>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - $mod, 26);
        }

        return $name;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private function rootRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Export" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function workbookRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
    }
}
