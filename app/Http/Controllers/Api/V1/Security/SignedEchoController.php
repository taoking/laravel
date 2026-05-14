<?php

namespace App\Http\Controllers\Api\V1\Security;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignedEchoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'payload' => $request->all(),
        ]);
    }
}
