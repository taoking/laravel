<?php

namespace App\Http\Middleware;

use App\Modules\Audit\Services\OperationLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordOperationLog
{
    public function __construct(private readonly OperationLogService $operationLogService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);
            $this->operationLogService->record($request, $response, $this->elapsedMs($startedAt));

            return $response;
        } catch (Throwable $exception) {
            $this->operationLogService->record($request, null, $this->elapsedMs($startedAt), 500);

            throw $exception;
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
