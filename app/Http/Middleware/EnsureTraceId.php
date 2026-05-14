<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureTraceId
{
    public const HEADER = 'X-Trace-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->headers->get(self::HEADER) ?: (string) Str::uuid();

        $request->attributes->set('trace_id', $traceId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $traceId);

        return $response;
    }
}
