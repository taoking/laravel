<?php

namespace App\Modules\Query\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Query\Requests\ExecuteQueryRequest;
use App\Modules\Query\Services\QueryOrchestrator;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;

class QueryController extends Controller
{
    public function execute(ExecuteQueryRequest $request, QueryOrchestrator $queryOrchestrator): JsonResponse
    {
        return ApiResponse::success($queryOrchestrator->execute($request->validated(), $request->user(), [
            'request_source' => 'api',
        ]));
    }
}
