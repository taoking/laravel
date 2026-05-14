<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.api_signature.secret');

        if (! $secret) {
            return ApiResponse::error('API signature secret is not configured.', 500);
        }

        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (! $timestamp || ! $nonce || ! $signature) {
            return ApiResponse::error('Missing signature headers.', 401);
        }

        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return ApiResponse::error('Signature timestamp expired.', 401);
        }

        $nonceKey = "security:nonce:{$nonce}";

        if (! Cache::add($nonceKey, true, 300)) {
            return ApiResponse::error('Replay request rejected.', 409);
        }

        $payload = implode('|', [
            strtoupper($request->method()),
            '/'.$request->path(),
            $timestamp,
            $nonce,
            $request->getContent(),
        ]);

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            Cache::forget($nonceKey);

            return ApiResponse::error('Invalid signature.', 401);
        }

        return $next($request);
    }
}
