<?php

namespace App\Modules\Query\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Query\Requests\ExecuteQueryRequest;
use App\Modules\Query\Services\QueryService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;

class QueryController extends Controller
{
    public function execute(ExecuteQueryRequest $request, QueryService $queryService): JsonResponse
    {
        return ApiResponse::success($queryService->execute($request->validated(), $request->user()));
    }
}
