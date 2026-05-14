<?php

namespace App\Http\Controllers\Api\V1\Metrics;

use App\Domains\Metrics\Models\Frequency;
use App\Domains\Metrics\Models\Region;
use App\Http\Controllers\Controller;
use App\Http\Resources\FrequencyResource;
use App\Http\Resources\RegionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DimensionController extends Controller
{
    public function regions(): JsonResponse
    {
        $regions = Region::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(RegionResource::collection($regions)->resolve());
    }

    public function frequencies(): JsonResponse
    {
        $frequencies = Frequency::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(FrequencyResource::collection($frequencies)->resolve());
    }
}
