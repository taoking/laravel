<?php

namespace App\Http\Middleware;

use App\Domains\Operations\Models\OperationLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordOperationLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        if ($request->is('api/*')) {
            $this->record($request, $response, $startedAt);
        }

        return $response;
    }

    private function record(Request $request, Response $response, float $startedAt): void
    {
        try {
            if (! Schema::hasTable('operation_logs')) {
                return;
            }

            OperationLog::query()->create([
                'user_id' => $request->user()?->id,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'ip_address' => $request->ip(),
                'trace_id' => $request->attributes->get('trace_id'),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable) {
            report('Failed to write operation log.');
        }
    }
}
