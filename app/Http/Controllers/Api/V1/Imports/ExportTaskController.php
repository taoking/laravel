<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Domains\Imports\Models\ExportTask;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Imports\ExportTaskResource;
use App\Jobs\ProcessMetricExportJob;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = ExportTask::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ExportTaskResource::collection($tasks->getCollection())->resolve(),
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
            'type' => ['sometimes', Rule::in(['metrics'])],
            'filters' => ['sometimes', 'array'],
            'filters.keyword' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filters.status' => ['sometimes', 'nullable', Rule::in(['active', 'disabled'])],
            'filters.category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'filters.category_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'filters.region_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'filters.frequency_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'filters.date_from' => ['sometimes', 'nullable', 'date'],
            'filters.date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:filters.date_from'],
            'idempotency_key' => ['sometimes', 'string', 'max:120'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? (string) Str::uuid());

        $filters = $this->normalizedFilters($validated['filters'] ?? []);

        $task = ExportTask::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $request->user()->id,
                'type' => $validated['type'] ?? 'metrics',
                'filters' => $filters,
                'status' => 'pending',
                'disk' => config('filesystems.default', 'local'),
            ],
        );

        if ($task->wasRecentlyCreated) {
            ProcessMetricExportJob::dispatch($task->id);

            AuditEvent::dispatch('export.created', ExportTask::class, $task->id, [
                'type' => $task->type,
                'filters' => $filters,
            ], $request);
        }

        return ApiResponse::success([
            'export_task' => ExportTaskResource::make($task->refresh())->resolve(),
        ], $task->wasRecentlyCreated ? 'Accepted.' : 'Already accepted.', $task->wasRecentlyCreated ? 202 : 200);
    }

    public function show(Request $request, ExportTask $export): JsonResponse
    {
        $this->authorizeOwner($request, $export);

        return ApiResponse::success([
            'export_task' => ExportTaskResource::make($export)->resolve(),
        ]);
    }

    public function download(Request $request, ExportTask $export): StreamedResponse
    {
        $this->authorizeOwner($request, $export);

        if ($export->status !== 'completed' || ! $export->path) {
            abort(409, 'Export file is not ready.');
        }

        $disk = Storage::disk($export->disk);

        if (! $disk->exists($export->path)) {
            abort(404, 'Export file was not found.');
        }

        $export->forceFill(['downloaded_at' => now()])->save();

        AuditEvent::dispatch('export.downloaded', ExportTask::class, $export->id, [
            'path' => $export->path,
            'file_size' => $export->file_size,
        ], $request);

        return $disk->download($export->path, basename($export->path), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizedFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function authorizeOwner(Request $request, ExportTask $task): void
    {
        if ((int) $task->user_id !== (int) $request->user()->id) {
            throw new AuthorizationException('Forbidden.');
        }
    }
}
