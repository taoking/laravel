<?php

namespace App\Modules\Monitor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitor\Services\MetricsService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(MetricsService $metricsService): Response
    {
        return response($metricsService->prometheus(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }
}
