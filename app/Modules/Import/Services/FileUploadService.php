<?php

namespace App\Modules\Import\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileUploadService
{
    /**
     * @return array{disk: string, path: string, name: string, type: string, size: int}
     */
    public function store(UploadedFile $file): array
    {
        $disk = (string) config('filesystems.import_disk', 'minio');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'csv');
        $directory = 'imports/'.now()->format('Y/m/d');
        $filename = (string) Str::uuid().'.'.$extension;

        $path = $file->storeAs($directory, $filename, ['disk' => $disk]);

        if ($path === false) {
            throw new RuntimeException('Failed to store import file.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'type' => $extension === 'txt' ? 'csv' : $extension,
            'size' => (int) ($file->getSize() ?: 0),
        ];
    }

    public function delete(string $path): void
    {
        Storage::disk((string) config('filesystems.import_disk', 'minio'))->delete($path);
    }
}
