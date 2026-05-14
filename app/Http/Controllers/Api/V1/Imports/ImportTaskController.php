<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Domains\Files\Models\UploadedFile as UploadedFileRecord;
use App\Domains\Imports\Models\ImportTask;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Imports\ImportTaskResource;
use App\Jobs\ProcessMetricImportJob;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImportTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = ImportTask::query()
            ->withCount('failures')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ImportTaskResource::collection($tasks->getCollection())->resolve(),
            meta: [
                'current_page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'idempotency_key' => ['sometimes', 'string', 'max:120'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? (string) Str::uuid());

        $existing = ImportTask::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return ApiResponse::success([
                'import_task' => ImportTaskResource::make($existing->load('failures'))->resolve(),
            ], 'Already accepted.');
        }

        $file = $validated['file'];
        $path = $file->store('imports');
        $disk = config('filesystems.default', 'local');

        UploadedFileRecord::query()->create([
            'user_id' => $request->user()->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'visibility' => 'private',
        ]);

        $task = ImportTask::query()->create([
            'user_id' => $request->user()->id,
            'idempotency_key' => $idempotencyKey,
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'status' => 'pending',
        ]);

        ProcessMetricImportJob::dispatch($task->id);

        AuditEvent::dispatch('import.created', ImportTask::class, $task->id, [
            'original_name' => $task->original_name,
        ], $request);

        return ApiResponse::success([
            'import_task' => ImportTaskResource::make($task->refresh()->load('failures'))->resolve(),
        ], 'Accepted.', 202);
    }

    public function show(ImportTask $import): JsonResponse
    {
        return ApiResponse::success([
            'import_task' => ImportTaskResource::make($import->load('failures'))->resolve(),
        ]);
    }

    public function retry(ImportTask $import): JsonResponse
    {
        $import->failures()->delete();
        $import->forceFill([
            'status' => 'pending',
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'error_message' => null,
            'failure_type' => null,
            'last_failed_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'compensated_at' => now(),
            'compensation_reason' => 'manual retry endpoint',
        ])->save();

        ProcessMetricImportJob::dispatch($import->id);

        AuditEvent::dispatch('import.retried', ImportTask::class, $import->id, [], request());

        return ApiResponse::success([
            'import_task' => ImportTaskResource::make($import->refresh()->load('failures'))->resolve(),
        ], 'Retried.');
    }
}
