<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\OperationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class OperationLogService
{
    public function record(Request $request, ?Response $response, int $elapsedMs, ?int $fallbackStatus = null): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $resource = $this->resource($request);

        OperationLog::query()->create([
            'tenant_id' => $user?->organization_id,
            'user_id' => $user?->id,
            'action' => $request->method().' '.$request->path(),
            'resource_type' => $resource['type'],
            'resource_id' => $resource['id'],
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_ip' => $request->ip(),
            'request_user_agent' => $request->userAgent(),
            'request_payload_json' => $this->payload($request),
            'response_code' => $response?->getStatusCode() ?? $fallbackStatus ?? 0,
            'elapsed_ms' => $elapsedMs,
            'created_at' => now(),
        ]);
    }

    public function paginate(int $pageSize, array $filters = []): LengthAwarePaginator
    {
        return OperationLog::query()
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['resource_type']), fn ($query) => $query->where('resource_type', $filters['resource_type']))
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @return array{type: string|null, id: int|null}
     */
    private function resource(Request $request): array
    {
        $segment = $request->segment(2);
        $resourceId = $request->segment(3);

        return [
            'type' => $segment !== null ? str_replace('-', '_', trim($segment)) : null,
            'id' => is_numeric($resourceId) ? (int) $resourceId : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return $this->sanitize($request->except(['password', 'password_confirmation', 'token']));
    }

    private function sanitize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
            ];
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->sanitize($item))
                ->all();
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }
}
