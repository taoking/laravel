<?php

namespace App\Http\Controllers\Api\V1\Metrics;

use App\Domains\Metrics\Models\MetricCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\MetricCategoryResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MetricCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = MetricCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(MetricCategoryResource::collection($categories)->resolve());
    }
}
