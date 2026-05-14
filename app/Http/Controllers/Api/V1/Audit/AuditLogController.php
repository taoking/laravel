<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Domains\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->when($request->string('action')->toString(), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->integer('user_id'), fn ($query, int $userId) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: AuditLogResource::collection($logs->getCollection())->resolve(),
            meta: [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        );
    }
}
