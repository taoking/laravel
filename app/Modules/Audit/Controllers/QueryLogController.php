<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Resources\QueryLogResource;
use App\Modules\Query\Models\QueryLog;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueryLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $logs = QueryLog::query()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('dataset_id'), fn ($query) => $query->where('dataset_id', $request->integer('dataset_id')))
            ->when($request->filled('chart_id'), fn ($query) => $query->where('chart_id', $request->integer('chart_id')))
            ->when($request->filled('dashboard_id'), fn ($query) => $query->where('dashboard_id', $request->integer('dashboard_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate($pageSize);

        return ApiResponse::paginated(
            $logs,
            QueryLogResource::collection($logs->items())->resolve($request),
        );
    }
}
