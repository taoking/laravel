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
            ->when($request->filled('acceleration_hit'), fn ($query) => $query->where('acceleration_hit', $request->boolean('acceleration_hit')))
            ->when($request->filled('fallback_used'), fn ($query) => $query->where('fallback_used', $request->boolean('fallback_used')))
            ->when($request->filled('detail_fallback_used'), fn ($query) => $query->where('detail_fallback_used', $request->boolean('detail_fallback_used')))
            ->when($request->filled('semantic_layer_used'), fn ($query) => $query->where('semantic_layer_used', $request->boolean('semantic_layer_used')))
            ->when($request->filled('engine_type'), fn ($query) => $query->where('engine_type', $request->string('engine_type')->toString()))
            ->when($request->filled('data_source_type'), fn ($query) => $query->where('data_source_type', $request->string('data_source_type')->toString()))
            ->when($request->filled('acceleration_engine'), fn ($query) => $query->where('acceleration_engine', $request->string('acceleration_engine')->toString()))
            ->when($request->filled('acceleration_mode'), fn ($query) => $query->where('acceleration_mode', $request->string('acceleration_mode')->toString()))
            ->when($request->filled('aggregate_definition_id'), fn ($query) => $query->where('aggregate_definition_id', $request->integer('aggregate_definition_id')))
            ->latest('id')
            ->paginate($pageSize);

        return ApiResponse::paginated(
            $logs,
            QueryLogResource::collection($logs->items())->resolve($request),
        );
    }
}
