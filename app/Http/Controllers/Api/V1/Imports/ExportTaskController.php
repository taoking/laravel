<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Domains\Imports\Models\ExportTask;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Imports\ExportTaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExportTaskController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['metrics'])],
            'filters' => ['sometimes', 'array'],
            'idempotency_key' => ['sometimes', 'string', 'max:120'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? (string) Str::uuid());

        $task = ExportTask::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $request->user()->id,
                'type' => $validated['type'] ?? 'metrics',
                'filters' => $validated['filters'] ?? [],
                'status' => 'pending',
                'disk' => config('filesystems.default', 'local'),
            ],
        );

        if ($task->wasRecentlyCreated) {
            AuditEvent::dispatch('export.created', ExportTask::class, $task->id, [
                'type' => $task->type,
            ], $request);
        }

        return ApiResponse::success([
            'export_task' => ExportTaskResource::make($task)->resolve(),
        ], $task->wasRecentlyCreated ? 'Accepted.' : 'Already accepted.', $task->wasRecentlyCreated ? 202 : 200);
    }
}
