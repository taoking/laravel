<?php

namespace App\Domains\Imports\Readers;

use App\Domains\Imports\Models\ImportTask;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

class MetricImportReader
{
    public function rows(ImportTask $task): LazyCollection
    {
        return $this->extension($task) === 'xlsx'
            ? $this->xlsxRows($task)
            : $this->csvRows($task);
    }

    private function csvRows(ImportTask $task): LazyCollection
    {
        return LazyCollection::make(function () use ($task) {
            [$path, $cleanup] = $this->localPath($task);
            $handle = @fopen($path, 'rb');

            if (! is_resource($handle)) {
                $this->cleanup($path, $cleanup);

                throw new RuntimeException("Unable to open import file [{$path}].");
            }

            try {
                while (($row = fgetcsv($handle)) !== false) {
                    yield $this->normalizeRow($row);
                }
            } finally {
                fclose($handle);
                $this->cleanup($path, $cleanup);
            }
        });
    }

    private function xlsxRows(ImportTask $task): LazyCollection
    {
        return LazyCollection::make(function () use ($task) {
            [$path, $cleanup] = $this->localPath($task);
            $reader = new Reader;

            try {
                $reader->open($path);

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        yield $this->normalizeRow($row->toArray());
                    }

                    break;
                }
            } finally {
                $reader->close();
                $this->cleanup($path, $cleanup);
            }
        });
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function localPath(ImportTask $task): array
    {
        $disk = Storage::disk($task->disk);
        $path = $disk->path($task->path);

        if (is_file($path)) {
            return [$path, false];
        }

        $stream = $disk->readStream($task->path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to open import file [{$task->path}].");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'metric-import-');

        if ($tempPath === false) {
            fclose($stream);

            throw new RuntimeException('Unable to create temporary import file.');
        }

        $target = @fopen($tempPath, 'wb');

        if (! is_resource($target)) {
            fclose($stream);
            @unlink($tempPath);

            throw new RuntimeException("Unable to open temporary import file [{$tempPath}].");
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($stream);
            fclose($target);
        }

        return [$tempPath, true];
    }

    /**
     * @param  list<mixed>  $row
     * @return list<string|null>
     */
    private function normalizeRow(array $row): array
    {
        return array_map(fn (mixed $value): ?string => $this->normalizeCell($value), array_values($row));
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof DateInterval) {
            return $value->format('%y-%m-%d %h:%i:%s');
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (TextRun|string $part): string => $part instanceof TextRun ? $part->text : (string) $part)
                ->implode('');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function cleanup(string $path, bool $cleanup): void
    {
        if ($cleanup && is_file($path)) {
            @unlink($path);
        }
    }

    private function extension(ImportTask $task): string
    {
        return strtolower(pathinfo($task->original_name ?: $task->path, PATHINFO_EXTENSION));
    }
}
