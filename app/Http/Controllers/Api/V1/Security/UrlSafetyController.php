<?php

namespace App\Http\Controllers\Api\V1\Security;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Security\UrlSafetyInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrlSafetyController extends Controller
{
    public function __invoke(Request $request, UrlSafetyInspector $inspector): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $result = $inspector->inspect($validated['url']);

        if (! $result['allowed']) {
            return ApiResponse::error('Unsafe URL.', 422, [
                'url' => [$result['reason']],
            ]);
        }

        return ApiResponse::success([
            'allowed' => true,
            'url' => $validated['url'],
            'host' => $result['host'],
            'resolved_ips' => $result['resolved_ips'],
        ], 'URL is allowed.');
    }
}
