<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Compilers\SqlCompiler;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use Throwable;

class QueryService
{
    public function __construct(
        private readonly QueryRequestValidator $validator,
        private readonly SqlCompiler $sqlCompiler,
        private readonly QueryCacheService $cacheService,
        private readonly QueryExecutor $executor,
        private readonly QueryLogService $logService,
        private readonly DataPermissionService $dataPermissionService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array{elapsed_ms: int, cached: bool, total: int}}
     */
    public function execute(array $payload, ?User $user, array $cacheContext = []): array
    {
        $query = QueryRequestDTO::fromArray($payload);
        $dataset = Dataset::query()
            ->with(['dataSource', 'fields'])
            ->findOrFail($query->datasetId);

        $this->dataPermissionService->assertCanAccessDataset($dataset, $user);
        $this->validator->validate($dataset, $query, $user);

        $compiledQuery = $this->sqlCompiler->compile($dataset, $query, $user);

        if ($query->useCache) {
            $cached = $this->cacheService->get($compiledQuery, $user, $cacheContext);

            if ($cached !== null) {
                $this->logService->success($dataset, $user, $compiledQuery, 0, count($cached['rows']), true, $cacheContext);

                return [
                    'columns' => $cached['columns'],
                    'rows' => $cached['rows'],
                    'meta' => [
                        'elapsed_ms' => 0,
                        'cached' => true,
                        'total' => count($cached['rows']),
                    ],
                ];
            }
        }

        try {
            $execution = $this->executor->execute($dataset, $compiledQuery);
        } catch (Throwable $exception) {
            $this->logService->failure($dataset, $user, $compiledQuery, $exception, context: $cacheContext);

            throw $exception;
        }

        $result = [
            'columns' => $compiledQuery->columns,
            'rows' => $execution->rows,
        ];

        if ($query->useCache) {
            $this->cacheService->put($compiledQuery, $result, $user, $cacheContext);
        }

        $this->logService->success($dataset, $user, $compiledQuery, $execution->elapsedMs, count($execution->rows), false, $cacheContext);

        return [
            ...$result,
            'meta' => [
                'elapsed_ms' => $execution->elapsedMs,
                'cached' => false,
                'total' => count($execution->rows),
            ],
        ];
    }
}
